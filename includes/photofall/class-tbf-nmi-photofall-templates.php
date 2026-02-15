<?php
/**
 * File: includes/photofall/class-tbf-nmi-photofall-templates.php
 * Version: 4.0.0
 *
 * Template loader + public assets for Photofall.
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_PhotoFall_Templates {

  public static function init() {
    add_action('template_redirect', [__CLASS__, 'maybe_render'], 0);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 20);
  }

  public static function is_photofall_request() {
    $kind = sanitize_key((string)get_query_var('tbf_pf_kind'));
    return in_array($kind, ['archive','image','video'], true);
  }

  public static function assets() {
    if ( ! self::is_photofall_request() ) return;

    $settings = class_exists('TBF_NMI_Plugin') ? TBF_NMI_Plugin::instance()->get_settings() : [];
    if ( empty($settings['photofall_enabled']) ) return;

    wp_enqueue_style('tbf-photofall-public', TBF_NMI_URL . 'assets/css/photofall-public.css', [], TBF_NMI_VER);
    wp_enqueue_script('tbf-photofall-public', TBF_NMI_URL . 'assets/js/photofall-public.js', ['jquery'], TBF_NMI_VER, true);

    $route  = sanitize_key((string)get_query_var('tbf_pf_route'));
    $page   = max(1, (int)get_query_var('tbf_pf_page'));
    $blogId = (int)get_query_var('tbf_pf_blog_id');
    $year   = (int)get_query_var('tbf_pf_year');
    $month  = (int)get_query_var('tbf_pf_month');
    $tag    = sanitize_title((string)get_query_var('tbf_pf_tag'));

    wp_localize_script('tbf-photofall-public', 'TBF_PHOTOFALL', [
      'rest' => rest_url('tbf-photofall/v1'),
      'nonce' => wp_create_nonce('wp_rest'),
      'route' => $route ?: 'root',
      'page' => $page,
      'blogId' => $blogId,
      'year' => $year,
      'month' => $month,
      'tag' => $tag,
      'pageSize' => isset($settings['photofall_page_size']) ? (int)$settings['photofall_page_size'] : 96,
    ]);
  }

  public static function maybe_render() {
    if ( ! self::is_photofall_request() ) return;

    $settings = class_exists('TBF_NMI_Plugin') ? TBF_NMI_Plugin::instance()->get_settings() : [];
    if ( empty($settings['photofall_enabled']) ) self::send_404();

    $public = ! empty($settings['photofall_public']);
    if ( ! $public && ! is_user_logged_in() ) {
      auth_redirect();
      exit;
    }

    $kind = sanitize_key((string)get_query_var('tbf_pf_kind'));

    status_header(200);
    nocache_headers();

    // Use your theme header/footer for consistent branding
    get_header();

    $tpl = '';
    if ( $kind === 'archive' ) $tpl = TBF_NMI_DIR . 'templates/photofall-archive.php';
    if ( $kind === 'image' )   $tpl = TBF_NMI_DIR . 'templates/photofall-image.php';
    if ( $kind === 'video' )   $tpl = TBF_NMI_DIR . 'templates/photofall-video.php';

    if ( $tpl && file_exists($tpl) ) {
      include $tpl;
    } else {
      echo '<div class="tbf-photofall"><div class="tbf-photofall__wrap"><p>Template missing.</p></div></div>';
    }

    get_footer();
    exit;
  }

  private static function send_404() {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    echo 'Not found';
    exit;
  }
}
