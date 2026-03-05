<?php
/**
 * File: includes/class-tbfbkm-featured-media.php
 * Version: 6.6.2 (Always-Return Placeholder ID + Remote Featured Image Persist)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Featured_Media {

  public static function register() {
    add_action('init', [__CLASS__, 'register_meta'], 99);
    add_filter('post_thumbnail_html', [__CLASS__, 'override_thumbnail_html'], 99, 5);

    // AJAX endpoint used by assets/js/gutenberg-sidebar.js
    add_action('wp_ajax_tbfbkm_set_featured_remote', [__CLASS__, 'ajax_set_featured_remote']);
  }

  public static function register_meta() {
    $args_url = [
      'type'              => 'string',
      'single'            => true,
      'show_in_rest'      => true,
      'sanitize_callback' => function($value) {
        return sanitize_text_field(wp_unslash($value));
      }
    ];

    $args_str = [
      'type'              => 'string',
      'single'            => true,
      'show_in_rest'      => true,
      'sanitize_callback' => 'sanitize_text_field'
    ];

    $post_types = get_post_types(['public' => true], 'names');
    foreach ($post_types as $pt) {
      register_post_meta($pt, '_tbfbkm_featured_url',  $args_url);
      register_post_meta($pt, '_tbfbkm_featured_mime', $args_str);
      register_post_meta($pt, '_tbfbkm_featured_type', $args_str);
    }
  }

  /**
   * Frontend/editor rendering override:
   * If a remote featured URL is stored, render it instead of the placeholder attachment.
   */
  public static function override_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
    $url = get_post_meta($post_id, '_tbfbkm_featured_url', true);

    if ( ! empty($url) ) {
      $alt = get_the_title($post_id);
      $url = html_entity_decode($url);

      // IMPORTANT:
      // - No crossorigin attribute (can trigger CDN blocking)
      // - referrerpolicy no-referrer helps with hotlink protections
      return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" class="wp-post-image tbfbkm-remote-featured" loading="lazy" decoding="async" referrerpolicy="no-referrer" />';
    }

    return $html;
  }

  /**
   * AJAX: Save remote featured URL meta and ALWAYS return a placeholder attachment ID
   * so Gutenberg will persist featured_media instead of reverting.
   */
  public static function ajax_set_featured_remote() {
    // Basic nonce check (matches tbfbkm_nonce used in your Gutenberg sidebar JS)
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ( ! wp_verify_nonce($nonce, 'tbfbkm_nonce') ) {
      wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if ( ! $post_id || ! get_post($post_id) ) {
      wp_send_json_error(['message' => 'Invalid post_id.']);
    }

    if ( ! current_user_can('edit_post', $post_id) ) {
      wp_send_json_error(['message' => 'Insufficient permissions.']);
    }

    $url  = isset($_POST['url'])  ? sanitize_text_field(wp_unslash($_POST['url']))  : '';
    $mime = isset($_POST['mime']) ? sanitize_text_field(wp_unslash($_POST['mime'])) : '';
    $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';

    $url = html_entity_decode($url);
    $url = esc_url_raw($url);

    // Only allow http/https (block javascript:, data:, etc.)
    $scheme = wp_parse_url($url, PHP_URL_SCHEME);
    if ( empty($url) || ! in_array($scheme, ['http', 'https'], true) ) {
      wp_send_json_error(['message' => 'Invalid URL scheme.']);
    }

    // Store the meta no matter what (even if the remote host blocks HEAD/GET).
    update_post_meta($post_id, '_tbfbkm_featured_url',  $url);
    update_post_meta($post_id, '_tbfbkm_featured_mime', $mime);
    update_post_meta($post_id, '_tbfbkm_featured_type', $type);

    // Ensure we have a real attachment ID to set as featured_media
    $placeholder_id = self::ensure_placeholder_attachment();

    if ( $placeholder_id ) {
      set_post_thumbnail($post_id, $placeholder_id);
      wp_send_json_success([
        'placeholder_id' => (int) $placeholder_id,
        'saved_url'      => $url,
      ]);
    }

    // Worst case: meta is still saved, but Gutenberg won't have a featured_media ID.
    // Returning error here prevents false success.
    wp_send_json_error([
      'message'    => 'Could not create placeholder attachment.',
      'saved_url'  => $url,
    ]);
  }

  /**
   * Creates (once) a tiny placeholder PNG attachment and stores its ID in an option.
   * Returns attachment ID.
   */
  private static function ensure_placeholder_attachment() {
    $existing = (int) get_option('tbfbkm_placeholder_id', 0);
    if ( $existing && get_post($existing) ) {
      return $existing;
    }

    // Tiny 1x1 transparent PNG
    $png_base64 =
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQImWNgYGBgAAAABQAB' .
      'JzQnCgAAAABJRU5ErkJggg==';

    $bits = base64_decode($png_base64);
    if ( ! $bits ) {
      return 0;
    }

    $upload = wp_upload_bits('tbfbkm-placeholder.png', null, $bits);
    if ( ! empty($upload['error']) || empty($upload['file']) ) {
      return 0;
    }

    $filetype = wp_check_filetype($upload['file'], null);
    $attachment = [
      'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/png',
      'post_title'     => 'TBFBKM Placeholder',
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    if ( ! $attach_id || is_wp_error($attach_id) ) {
      return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    if ( is_array($attach_data) ) {
      wp_update_attachment_metadata($attach_id, $attach_data);
    }

    update_option('tbfbkm_placeholder_id', (int) $attach_id);

    return (int) $attach_id;
  }
}
