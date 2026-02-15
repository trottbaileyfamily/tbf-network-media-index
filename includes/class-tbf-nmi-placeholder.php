<?php
/*
 * File: includes/class-tbf-nmi-placeholder.php
 * Version: 1.0.27
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Placeholder {

  const OPT_KEY = 'tbf_nmi_placeholder_attachment_id';

  public static function init() {
    // Only needs to run in admin
    if ( is_admin() ) {
      add_action('admin_init', [__CLASS__, 'ensure_placeholder']);
    }
  }

  public static function get_id() {
    $id = (int) get_option(self::OPT_KEY, 0);
    return $id > 0 ? $id : 0;
  }

  public static function ensure_placeholder() {
    $existing = self::get_id();
    if ( $existing && get_post($existing) ) {
      return;
    }

    // Create a tiny 1x1 PNG (transparent) in uploads
    $upload = wp_upload_bits('tbf-nmi-placeholder.png', null, self::tiny_png_bytes());
    if ( ! empty($upload['error']) || empty($upload['file']) ) {
      return;
    }

    $file = $upload['file'];
    $type = wp_check_filetype($file, null);

    $attachment = [
      'post_mime_type' => $type['type'] ?: 'image/png',
      'post_title'     => 'TBF NMI Placeholder',
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $file);
    if ( is_wp_error($attach_id) || ! $attach_id ) {
      return;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata($attach_id, $file);
    if ( is_array($meta) ) {
      wp_update_attachment_metadata($attach_id, $meta);
    }

    update_option(self::OPT_KEY, (int)$attach_id, false);
  }

  /**
   * Returns binary PNG bytes (1x1 transparent).
   */
  private static function tiny_png_bytes() {
    // A valid 1x1 transparent PNG (binary)
    return base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII='
    );
  }
}
