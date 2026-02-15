<?php
/**
 * File: includes/class-tbf-nmi-featured-media.php
 * Version: 4.2.4
 *
 * Remote (FIFU-style) featured media:
 * - Stores remote URL + type/mime on the post.
 * - Keeps WP/Gutenberg stable by using a placeholder attachment ID in _thumbnail_id.
 *
 * Porto/Elementor compatibility (robust):
 * - Elementor/Porto often fetch featured image via raw post meta (_thumbnail_id),
 *   then resolves URL via attachment functions (placeholder ID -> placeholder.png).
 * - We hook get_post_metadata for _thumbnail_id to capture post context on every request,
 *   then swap placeholder attachment URL/sizes to the remote featured URL reliably.
 */

if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Featured_Media {

  private const META_URL  = '_tbf_nmi_featured_url';
  private const META_MIME = '_tbf_nmi_featured_mime';
  private const META_TYPE = '_tbf_nmi_featured_type'; // image|video|audio|file

  /**
   * Request-scoped context: placeholder_id => remote_url
   * Populated when the theme/builder asks for _thumbnail_id on a post that has remote featured meta.
   */
  private static $ctx = [];

  /**
   * Avoid recursion when reading post meta.
   */
  private static $meta_guard = false;

  /**
   * Small in-request cache for remote meta reads.
   * [post_id => ['url'=>..., 'type'=>..., 'mime'=>...]]
   */
  private static $remote_cache = [];

  public static function register() {
    // Capture context reliably even when builder reads meta directly
    add_filter('get_post_metadata', [__CLASS__, 'filter_get_post_metadata'], 1, 4);

    // Standard flows
    add_action('set_post_thumbnail', [__CLASS__, 'on_set_post_thumbnail'], 10, 3);
    add_action('save_post', [__CLASS__, 'ensure_placeholder_thumbnail_on_save'], 9999, 2);

    // Theme/front-end output paths
    add_filter('post_thumbnail_html', [__CLASS__, 'filter_post_thumbnail_html'], 10, 5);
    add_filter('admin_post_thumbnail_html', [__CLASS__, 'filter_admin_post_thumbnail_html'], 10, 3);
    add_filter('get_the_post_thumbnail_url', [__CLASS__, 'filter_post_thumbnail_url'], 10, 3);

    // Attachment-id based paths (Porto/Elementor frequently use these)
    add_filter('wp_get_attachment_url', [__CLASS__, 'filter_attachment_url'], 10, 2);
    add_filter('wp_get_attachment_image_src', [__CLASS__, 'filter_attachment_image_src'], 10, 4);
    add_filter('image_downsize', [__CLASS__, 'filter_image_downsize'], 10, 3);

    // Prevent srcset/sizes noise + add owl-lazy data-src
    add_filter('wp_calculate_image_srcset', [__CLASS__, 'disable_srcset_for_remote_featured'], 10, 5);
    add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'filter_attachment_image_attributes'], 10, 3);
  }

  /**
   * When a proxy attachment is selected as featured, store remote meta and force placeholder.
   */
  public static function on_set_post_thumbnail($post_id, $thumbnail_id, $previous_thumbnail_id) {
    $post_id = (int)$post_id;
    $thumbnail_id = (int)$thumbnail_id;
    if ($post_id <= 0) return;

    if ($thumbnail_id <= 0) {
      self::clear_featured_meta($post_id);
      return;
    }

    if ((string) get_post_meta($thumbnail_id, '_tbf_nmi_is_proxy', true) !== '1') return;

    $remote = (string) get_post_meta($thumbnail_id, '_tbf_nmi_proxy_url', true);
    if ($remote === '') return;

    $mime = (string) get_post_mime_type($thumbnail_id);
    if ($mime === '') $mime = 'application/octet-stream';

    $type = self::type_from_mime($mime);

    update_post_meta($post_id, self::META_URL, esc_url_raw($remote));
    update_post_meta($post_id, self::META_MIME, sanitize_text_field($mime));
    update_post_meta($post_id, self::META_TYPE, $type);

    // clear request cache for this post
    unset(self::$remote_cache[$post_id]);

    self::force_placeholder_thumbnail_id($post_id);
  }

  public static function ensure_placeholder_thumbnail_on_save($post_id, $post) {
    $post_id = (int)$post_id;
    if ($post_id <= 0) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

    $remote = self::get_remote_meta($post_id);
    if ($remote['url'] === '' || $remote['type'] !== 'image') return;

    $thumbId = (int) get_post_meta($post_id, '_thumbnail_id', true);
    if ($thumbId > 0) return;

    self::force_placeholder_thumbnail_id($post_id);
  }

  /**
   * CRITICAL: capture context even when theme/builder uses raw meta reads for _thumbnail_id.
   * If remote featured meta exists, we:
   * - store ctx[placeholder_id] = remote_url for this request
   * - return placeholder id as the meta value (stability)
   */
  public static function filter_get_post_metadata($value, $object_id, $meta_key, $single) {
    if (self::$meta_guard) return $value;

    if ($meta_key !== '_thumbnail_id') return $value;

    $post_id = (int)$object_id;
    if ($post_id <= 0) return $value;

    $remote = self::get_remote_meta($post_id);
    if ($remote['url'] === '' || $remote['type'] !== 'image') return $value;

    $pid = self::placeholder_id();
    if ($pid <= 0) return $value;

    self::$ctx[$pid] = $remote['url'];

    // Force WP to treat featured id as placeholder (no REST deletions / stable editor)
    if ($single) return (string)$pid;
    return [(string)$pid];
  }

  public static function filter_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
    $post_id = (int)$post_id;
    if ($post_id <= 0) return $html;

    $remote = self::get_remote_meta($post_id);
    if ($remote['url'] === '') return $html;

    return self::render_remote_featured($remote['url'], $remote['type'], $attr, false);
  }

  public static function filter_admin_post_thumbnail_html($content, $post_id, $thumbnail_id) {
    $post_id = (int)$post_id;
    if ($post_id <= 0) return $content;

    $remote = self::get_remote_meta($post_id);
    if ($remote['url'] === '') return $content;

    $preview =
      '<div class="tbf-nmi-featured-preview" style="margin:0 0 10px 0;">' .
        self::render_remote_featured($remote['url'], $remote['type'], [], true) .
      '</div>';

    return $preview . $content;
  }

  public static function filter_post_thumbnail_url($url, $post_id, $size) {
    $post_id = (int)$post_id;
    if ($post_id <= 0) return $url;

    $remote = self::get_remote_meta($post_id);
    if ($remote['url'] === '' || $remote['type'] !== 'image') return $url;

    return esc_url($remote['url']);
  }

  public static function filter_attachment_url($url, $attachment_id) {
    $attachment_id = (int)$attachment_id;
    $pid = self::placeholder_id();
    if ($pid <= 0 || $attachment_id !== $pid) return $url;

    $remote = self::$ctx[$pid] ?? '';
    return $remote !== '' ? esc_url($remote) : $url;
  }

  public static function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
    $attachment_id = (int)$attachment_id;
    $pid = self::placeholder_id();
    if ($pid <= 0 || $attachment_id !== $pid) return $image;

    $remote = self::$ctx[$pid] ?? '';
    if ($remote === '') return $image;

    return [esc_url($remote), 0, 0, false];
  }

  public static function filter_image_downsize($out, $attachment_id, $size) {
    $attachment_id = (int)$attachment_id;
    $pid = self::placeholder_id();
    if ($pid <= 0 || $attachment_id !== $pid) return $out;

    $remote = self::$ctx[$pid] ?? '';
    if ($remote === '') return $out;

    return [esc_url($remote), 0, 0, false];
  }

  public static function disable_srcset_for_remote_featured($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    $attachment_id = (int)$attachment_id;
    $pid = self::placeholder_id();
    if ($pid <= 0 || $attachment_id !== $pid) return $sources;

    $remote = self::$ctx[$pid] ?? '';
    if ($remote === '') return $sources;

    return false;
  }

  public static function filter_attachment_image_attributes($attr, $attachment, $size) {
    if (!is_array($attr)) return $attr;

    $aid = isset($attachment->ID) ? (int)$attachment->ID : 0;
    $pid = self::placeholder_id();
    if ($pid <= 0 || $aid !== $pid) return $attr;

    $remote = self::$ctx[$pid] ?? '';
    if ($remote === '') return $attr;

    $remote = esc_url($remote);
    $attr['src'] = $remote;

    $class = isset($attr['class']) ? (string)$attr['class'] : '';
    if (strpos($class, 'owl-lazy') !== false) {
      $attr['data-src'] = $remote;
    }

    if (isset($attr['data-original'])) $attr['data-original'] = $remote;

    unset($attr['srcset'], $attr['sizes']);
    return $attr;
  }

  private static function get_remote_meta($post_id) {
    $post_id = (int)$post_id;
    if ($post_id <= 0) return ['url' => '', 'type' => '', 'mime' => ''];

    if (isset(self::$remote_cache[$post_id])) return self::$remote_cache[$post_id];

    // Avoid recursion if meta filters call back into this.
    self::$meta_guard = true;

    // Use direct SQL read for reliability (bypasses meta filters).
    global $wpdb;
    $pm = $wpdb->postmeta;

    $keys = [self::META_URL, self::META_TYPE, self::META_MIME];
    $placeholders = implode(',', array_fill(0, count($keys), '%s'));
    $sql = "SELECT meta_key, meta_value FROM {$pm} WHERE post_id = %d AND meta_key IN ($placeholders)";
    $params = array_merge([$post_id], $keys);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

    $map = [];
    foreach ((array)$rows as $r) $map[(string)$r['meta_key']] = (string)$r['meta_value'];

    $url  = isset($map[self::META_URL]) ? (string)$map[self::META_URL] : '';
    $type = isset($map[self::META_TYPE]) ? (string)$map[self::META_TYPE] : '';
    $mime = isset($map[self::META_MIME]) ? (string)$map[self::META_MIME] : '';

    $url = $url ? esc_url_raw($url) : '';
    $type = $type ? sanitize_key($type) : '';
    $mime = $mime ? sanitize_text_field($mime) : '';

    if ($type === '' && $mime !== '') $type = self::type_from_mime($mime);

    self::$meta_guard = false;

    self::$remote_cache[$post_id] = ['url' => $url, 'type' => $type ?: 'image', 'mime' => $mime];
    return self::$remote_cache[$post_id];
  }

  private static function placeholder_id() {
    if (!class_exists('TBF_NMI_Placeholder')) return 0;
    return (int) TBF_NMI_Placeholder::get_id();
  }

  private static function force_placeholder_thumbnail_id($post_id) {
    $post_id = (int)$post_id;
    if ($post_id <= 0) return;

    $pid = self::placeholder_id();
    if ($pid <= 0) return;

    update_post_meta($post_id, '_thumbnail_id', $pid);
    clean_post_cache($post_id);
  }

  private static function clear_featured_meta($post_id) {
    $post_id = (int)$post_id;
    delete_post_meta($post_id, self::META_URL);
    delete_post_meta($post_id, self::META_MIME);
    delete_post_meta($post_id, self::META_TYPE);
    unset(self::$remote_cache[$post_id]);
  }

  private static function type_from_mime($mime) {
    $mime = (string)$mime;
    if (strpos($mime, 'image/') === 0) return 'image';
    if (strpos($mime, 'video/') === 0) return 'video';
    if (strpos($mime, 'audio/') === 0) return 'audio';
    return 'file';
  }

  private static function render_remote_featured($url, $type, $attr, $isAdmin) {
    $url = esc_url($url);

    if ($type === 'video') {
      $attrs = $isAdmin ? ' controls style="max-width:100%;height:auto;"' : ' controls';
      return '<video src="' . $url . '"' . $attrs . '></video>';
    }

    if ($type === 'audio') {
      return '<audio src="' . $url . '" controls style="width:100%;"></audio>';
    }

    $alt = '';
    if (is_array($attr) && isset($attr['alt'])) $alt = (string)$attr['alt'];
    $alt = esc_attr($alt);

    $style = $isAdmin ? ' style="display:block;max-width:100%;height:auto;"' : '';

    return '<img src="' . $url . '" alt="' . $alt . '" loading="lazy" decoding="async"' . $style . ' />';
  }
}

TBF_NMI_Featured_Media::register();
