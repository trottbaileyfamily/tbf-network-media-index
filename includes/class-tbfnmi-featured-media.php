<?php
/**
 * File: includes/class-tbfnmi-featured-media.php
 * Version: 6.5.0 (REST API Gutenberg Hard-Override)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Featured_Media {

  public static function register() {
    add_filter('image_downsize', [__CLASS__, 'filter_image_downsize'], 99, 3);
    add_filter('wp_get_attachment_url', [__CLASS__, 'filter_attachment_url'], 99, 2);
    add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'filter_attr'], 99, 3);
    add_filter('wp_calculate_image_srcset', [__CLASS__, 'disable_srcset'], 99, 5);
    add_filter('admin_post_thumbnail_html', [__CLASS__, 'filter_admin_preview'], 99, 3);
    add_filter('post_thumbnail_html', [__CLASS__, 'filter_post_thumbnail_html'], 99, 5);
    add_filter('wp_get_attachment_metadata', [__CLASS__, 'filter_metadata'], 99, 2);
    
    // NEW: Force Gutenberg's REST API to accept the GIF
    add_filter('rest_prepare_attachment', [__CLASS__, 'filter_rest_attachment'], 99, 3);
  }

  public static function get_proxy_url($id) {
      $id = (int)$id;
      if ($id <= 0) return false;

      $url = get_post_meta($id, '_tbfnmi_proxy_url', true);
      if (!$url) $url = get_post_meta($id, '_tbfnmi_origin_url', true); 
      
      if ($url) return esc_url_raw($url);

      $placeholder_id = (int) get_option('tbfnmi_placeholder_id', 0);
      if ($placeholder_id > 0 && $id === $placeholder_id) {
          $post_id = get_the_ID();
          if (!$post_id && isset($_REQUEST['post_id'])) $post_id = (int)$_REQUEST['post_id'];
          if (!$post_id && isset($_REQUEST['post'])) $post_id = (int)$_REQUEST['post'];

          if ($post_id > 0) {
              $remote_url = get_post_meta($post_id, '_tbfnmi_featured_url', true);
              if ($remote_url) return esc_url_raw($remote_url);
          }
      }

      return false;
  }

  // Intercept the REST API and feed Gutenberg the exact schema it expects
  public static function filter_rest_attachment($response, $post, $request) {
      $url = self::get_proxy_url($post->ID);
      if ($url) {
          $data = $response->get_data();
          $mime = get_post_mime_type($post->ID) ?: 'image/gif';
          $filename = basename(parse_url($url, PHP_URL_PATH));

          $mock_size = [
              'file' => $filename,
              'width' => 800,
              'height' => 600,
              'mime_type' => $mime,
              'source_url' => $url
          ];

          $data['media_details'] = [
              'width' => 800,
              'height' => 600,
              'file' => $filename,
              'sizes' => [
                  'full'      => $mock_size,
                  'thumbnail' => $mock_size,
                  'medium'    => $mock_size,
                  'large'     => $mock_size,
              ]
          ];
          
          $data['source_url'] = $url;
          $response->set_data($data);
      }
      return $response;
  }

  public static function filter_metadata($data, $post_id) {
      $url = self::get_proxy_url($post_id);
      if ( $url ) {
          if ( empty($data) || empty($data['width']) ) {
              $filename = basename(parse_url($url, PHP_URL_PATH));
              $mime = get_post_mime_type($post_id) ?: 'image/gif';
              
              return [
                  'width'  => 800,
                  'height' => 600,
                  'file'   => $filename,
                  'sizes'  => [
                      'full' => [
                          'file' => $filename,
                          'width' => 800,
                          'height' => 600,
                          'mime-type' => $mime,
                          'source_url' => $url
                      ]
                  ]
              ];
          }
      }
      return $data;
  }

  public static function filter_image_downsize($out, $id, $size) {
    $url = self::get_proxy_url($id);
    if ( ! $url ) return $out;
    return [$url, 800, 600, false];
  }

  public static function filter_attachment_url($url, $id) {
    $proxy_url = self::get_proxy_url($id);
    return $proxy_url ? $proxy_url : $url;
  }

  public static function filter_attr($attr, $attachment, $size) {
    $id = isset($attachment->ID) ? (int)$attachment->ID : 0;
    $url = self::get_proxy_url($id);
    
    if ( $url ) {
        $attr['src'] = $url;
        unset($attr['srcset'], $attr['sizes']);
    }
    return $attr;
  }

  public static function disable_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    return self::get_proxy_url($attachment_id) ? false : $sources;
  }

  public static function filter_admin_preview($content, $post_id, $thumbnail_id) {
    $url = self::get_proxy_url($thumbnail_id);
    
    if ( $url && (empty($content) || strpos($content, 'width="1"') !== false) ) {
        return '<p style="color:#2271b1;font-weight:bold;margin-bottom:5px;">Network Proxy Image</p><img src="' . esc_url($url) . '" style="max-width:100%;height:auto;display:block;" />';
    }
    return $content;
  }

  public static function filter_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
      $remote_url = get_post_meta($post_id, '_tbfnmi_featured_url', true);
      
      if ( $remote_url ) {
          $url = esc_url($remote_url);
          $alt = esc_attr(get_the_title($post_id));
          
          $class = 'attachment-post-thumbnail size-post-thumbnail wp-post-image tbfnmi-remote-thumb';
          if (is_array($attr) && isset($attr['class'])) {
              $class .= ' ' . esc_attr($attr['class']);
          }

          return sprintf(
              '<img src="%s" class="%s" alt="%s" loading="lazy" decoding="async" style="max-width:100%%; height:auto;" />',
              $url, $class, $alt
          );
      }
      return $html;
  }
}

TBFNMI_Featured_Media::register();