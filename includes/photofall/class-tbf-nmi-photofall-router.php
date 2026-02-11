<?php
/**
 * File: includes/photofall/class-tbf-nmi-photofall-router.php
 * Version: 4.0.6
 *
 * Photofall router (front-end only).
 * - Only active on the /1drop site (blog_id = 2)
 * - Keeps backward compatibility: ::register() exists because class-tbf-nmi-photofall.php calls it.
 *
 * Routes:
 *  /photo/
 *  /photo/i/{blog_id}/{attachment_id}/
 *  /photo/v/{blog_id}/{attachment_id}/
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_PhotoFall_Router {

  const TARGET_BLOG_ID = 2; // /1drop
  const BASE_SLUG = 'photo';

  /**
   * Backward compatible entry point.
   * Your class-tbf-nmi-photofall.php calls TBF_NMI_PhotoFall_Router::register()
   */
  public static function register() {
    self::boot();
  }

  /**
   * Newer entry point (same behavior).
   */
  public static function boot() {
    add_action('init', [__CLASS__, 'init_rewrites']);
    add_filter('query_vars', [__CLASS__, 'query_vars']);
    add_action('template_redirect', [__CLASS__, 'maybe_render']);
  }

  private static function is_target_site() {
    return function_exists('get_current_blog_id') && ((int) get_current_blog_id() === (int) self::TARGET_BLOG_ID);
  }

  public static function init_rewrites() {
    // Only register rewrite rules on /1drop.
    if ( ! self::is_target_site() ) return;

    add_rewrite_tag('%tbf_pf_page%', '([^&]+)');
    add_rewrite_tag('%tbf_pf_type%', '([^&]+)');
    add_rewrite_tag('%tbf_pf_blog%', '([0-9]+)');
    add_rewrite_tag('%tbf_pf_att%',  '([0-9]+)');

    // /1drop/photo/
    add_rewrite_rule(
      '^' . self::BASE_SLUG . '/?$',
      'index.php?tbf_pf_page=grid',
      'top'
    );

    // /1drop/photo/i/1/142487/  OR  /1drop/photo/v/1/123/
    // NOTE: in add_rewrite_rule replacement strings, $matches[] is evaluated by WP rewrite engine.
    add_rewrite_rule(
      '^' . self::BASE_SLUG . '/(i|v)/([0-9]+)/([0-9]+)/?$',
      'index.php?tbf_pf_page=item&tbf_pf_type=$matches[1]&tbf_pf_blog=$matches[2]&tbf_pf_att=$matches[3]',
      'top'
    );
  }

  public static function query_vars($vars) {
    $vars[] = 'tbf_pf_page';
    $vars[] = 'tbf_pf_type';
    $vars[] = 'tbf_pf_blog';
    $vars[] = 'tbf_pf_att';
    return $vars;
  }

  public static function maybe_render() {
    // Never run in admin, ajax, cron, or non-target sites.
    if ( is_admin() ) return;
    if ( defined('DOING_AJAX') && DOING_AJAX ) return;
    if ( defined('DOING_CRON') && DOING_CRON ) return;
    if ( ! self::is_target_site() ) return;

    $page = (string) get_query_var('tbf_pf_page');
    if ( $page === '' ) return;

    if ( $page === 'grid' ) {
      self::render_grid();
      exit;
    }

    if ( $page === 'item' ) {
      $type = (string) get_query_var('tbf_pf_type');
      $blog = (int) get_query_var('tbf_pf_blog');
      $att  = (int) get_query_var('tbf_pf_att');

      if ( $blog <= 0 || $att <= 0 ) {
        self::render_404('Image not found.');
        exit;
      }

      self::render_item($type, $blog, $att);
      exit;
    }

    // Unknown photofall page var: do nothing
    return;
  }

  private static function render_grid() {
    $tpl = plugin_dir_path(__FILE__) . 'templates/photofall-grid.php';

    status_header(200);
    nocache_headers();

    if ( file_exists($tpl) ) {
      include $tpl;
      return;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Photofall</title></head><body>';
    echo '<h1>Photofall</h1><p>Template missing: photofall-grid.php</p>';
    echo '</body></html>';
  }

  private static function render_item($type, $blogId, $attId) {
    $isVideo = ($type === 'v');

    if ( ! class_exists('TBF_NMI_PhotoFall_Query') ) {
      self::render_404('Image not found.');
      return;
    }

    $q = new TBF_NMI_PhotoFall_Query();
    $item = $q->get_item($blogId, $attId);

    if ( ! $item || empty($item['url_full']) ) {
      self::render_404('Image not found.');
      return;
    }

    $tpl = plugin_dir_path(__FILE__) . 'templates/photofall-item.php';

    status_header(200);
    nocache_headers();

    if ( file_exists($tpl) ) {
      // Provide $item and $isVideo to template
      include $tpl;
      return;
    }

    $title = esc_html($item['title'] ?: 'Photo');
    $url   = esc_url($item['url_full']);

    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . $title . '</title></head><body>';
    echo '<p><a href="' . esc_url(home_url('/' . self::BASE_SLUG . '/')) . '">← Back to Photofall</a></p>';

    if ( $isVideo ) {
      echo '<video controls style="max-width:100%;height:auto;" src="' . $url . '"></video>';
    } else {
      echo '<img style="max-width:100%;height:auto;" src="' . $url . '" alt="' . $title . '">';
    }

    echo '</body></html>';
  }

  private static function render_404($msg) {
    status_header(404);
    nocache_headers();
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Not found</title></head><body>';
    echo '<h1>Not found</h1><p>' . esc_html($msg) . '</p>';
    echo '</body></html>';
  }
}
