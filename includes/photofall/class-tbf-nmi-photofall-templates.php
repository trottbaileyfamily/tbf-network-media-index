<?php
/**
 * File: includes/photofall/class-tbf-nmi-photofall-templates.php
 * Version: 4.0.10
 *
 * Front-end template loader for Photofall.
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_PhotoFall_Templates {

  public static function init() {
    // Render page
    add_filter('template_include', [__CLASS__, 'template_include'], 99);

    // Assets
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_public_assets'], 20);
  }

  /**
   * Detect whether the current request is Photofall.
   * We rely on the router adding a query var OR matching the path.
   */
  private static function is_photofall_request() {
    // If router set a query var (preferred)
    $qv = get_query_var('tbf_photofall', null);
    if ( $qv !== null ) return true;

    // Fallback: path match (handles cases where router rewrites got wiped)
    $path = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    $path = strtok($path, '?'); // strip query
    $base = '/' . trim(TBF_NMI_PHOTOFALL_BASE, '/') . '/';

    // Also allow /1drop/photo/ specifically
    return (strpos($path, $base) !== false);
  }

  /**
   * Enqueue public JS/CSS ONLY on photofall requests.
   */
  public static function enqueue_public_assets() {
    if ( ! self::is_photofall_request() ) return;

    // Use your real existing file names
    $css = TBF_NMI_DIR . 'assets/css/photofall-public.css';
    if ( file_exists($css) ) {
      wp_enqueue_style('tbf-photofall-public', TBF_NMI_URL . 'assets/css/photofall-public.css', [], TBF_NMI_VER);
    }

    $js = TBF_NMI_DIR . 'assets/js/photofall-public.js';
    if ( file_exists($js) ) {
      wp_enqueue_script('tbf-photofall-public', TBF_NMI_URL . 'assets/js/photofall-public.js', [], TBF_NMI_VER, true);
    }

    // Config for JS
    $settings = class_exists('TBF_NMI_Plugin') ? TBF_NMI_Plugin::instance()->get_settings() : [];
    $pageSize = ! empty($settings['photofall_page_size']) ? (int)$settings['photofall_page_size'] : 24;

    wp_add_inline_script('tbf-photofall-public', 'window.TBF_PHOTOFALL = window.TBF_PHOTOFALL || {};', 'before');

    // IMPORTANT: apiBase must point to /1drop in your setup
    wp_add_inline_script('tbf-photofall-public', 'window.TBF_PHOTOFALL.apiBase = ' . wp_json_encode( home_url('/1drop/wp-json/tbf-photofall/v1') ) . ';', 'before');
    wp_add_inline_script('tbf-photofall-public', 'window.TBF_PHOTOFALL.pageSize = ' . wp_json_encode( max(6, min(200, $pageSize)) ) . ';', 'before');
    wp_add_inline_script('tbf-photofall-public', 'window.TBF_PHOTOFALL.placeholder = ' . wp_json_encode( home_url('/wp-content/uploads/2026/02/tbf-nmi-placeholder.png') ) . ';', 'before');
  }

  /**
   * Provide the actual template.
   * If template file is missing, render a built-in fallback instead of erroring.
   */
  public static function template_include($template) {
    if ( ! self::is_photofall_request() ) return $template;

    // Try expected template locations (some installs move templates)
    $candidates = [
      TBF_NMI_DIR . 'templates/photofall-grid.php',
      TBF_NMI_DIR . 'template/photofall-grid.php',
      TBF_NMI_DIR . 'includes/photofall/templates/photofall-grid.php',
    ];

    foreach ($candidates as $p) {
      if ( file_exists($p) ) {
        return $p;
      }
    }

    // Fallback: render a minimal template directly
    status_header(200);
    nocache_headers();

    add_filter('the_content', function($content){
      ob_start();
      ?>
      <div class="tbf-photofall-wrap">
        <div class="tbf-photofall-topbar">
          <h1 class="tbf-photofall-title">Photofall</h1>
          <input type="search" class="tbf-photofall-search" placeholder="Search photos..." data-photofall-search />
        </div>

        <div class="tbf-photofall-grid" data-photofall-grid></div>

        <div class="tbf-photofall-modal" data-photofall-modal style="display:none;">
          <div class="tbf-photofall-modal-inner">
            <button type="button" class="tbf-photofall-close" data-photofall-modal-close aria-label="Close">×</button>

            <div class="tbf-photofall-modal-media">
              <img data-photofall-modal-img alt="" style="display:none;max-width:100%;height:auto;" />
              <video data-photofall-modal-video style="display:none;max-width:100%;" controls></video>
            </div>

            <div class="tbf-photofall-modal-meta">
              <div class="tbf-photofall-modal-title" data-photofall-modal-title></div>
            </div>
          </div>
        </div>
      </div>
      <?php
      return ob_get_clean();
    });

    // Use the active theme’s page template, but content will be replaced by filter above
    return $template;
  }
}
