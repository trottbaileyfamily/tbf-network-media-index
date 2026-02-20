<?php
/*
 * File: includes/class-tbfnmi-placeholder.php
 * Version: 2.0.0
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Placeholder {

  const OPT_KEY = 'tbfnmi_placeholder_attachment_id';

  public static function init() {
    if ( is_admin() ) {
      add_action('admin_init', [__CLASS__, 'ensure_placeholder']);
    }
  }

  public static function get_id() {
    $id = (int) get_option(self::OPT_KEY, 0);
    return ($id > 0 && get_post($id)) ? $id : 0;
  }

  public static function ensure_placeholder() {
    $existing = self::get_id();
    if ( $existing ) return;

    $upload = wp_upload_bits('tbfnmi-placeholder.png', null, self::tiny_png_bytes());
    if ( ! empty($upload['error']) || empty($upload['file']) ) return;

    $file = $upload['file'];
    $type = wp_check_filetype($file, null);

    $attachment = [
      'post_mime_type' => $type['type'] ?: 'image/png',
      'post_title'     => 'TBF NMI Placeholder',
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $file);
    if ( is_wp_error($attach_id) || ! $attach_id ) return;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata($attach_id, $file);
    if ( is_array($meta) ) {
      wp_update_attachment_metadata($attach_id, $meta);
    }

    update_option(self::OPT_KEY, (int)$attach_id, false);
  }

  private static function tiny_png_bytes() {
    // 1x1 transparent PNG
    return base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII='
    );
  }
}
