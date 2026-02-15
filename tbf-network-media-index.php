<?php
/**
 * File: tbf-network-media-index.php
 *
 * Plugin Name:       TBF Network Media Index
 * Plugin URI:        https://trottbaileyfamily.com/tbf-network-media-index
 * Description:       Browse/insert media from any site in a multisite network without copying files + public Photofall at /photo/ with sitemaps for indexing.
 * Version:           4.2.4
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Trott Bailey Family, Kimroy Bailey, David Luis
 * Author URI:        https://trottbaileyfamily.com/
 * Network:           true
 * Text Domain:       tbf-network-media-index
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined('ABSPATH') ) exit;
define('TBF_NMI_VER', '4.2.4');
define('TBF_NMI_DIR', plugin_dir_path(__FILE__));
define('TBF_NMI_URL', plugin_dir_url(__FILE__));

/**
 * Public Photofall base route:
 * https://trottbaileyfamily.com/1drop/photo/
 *
 * This constant defines the route segment only (no domain).
 */
if ( ! defined('TBF_NMI_PHOTOFALL_BASE') ) {
  define('TBF_NMI_PHOTOFALL_BASE', 'photo');
}

/**
 * Graceful failure if required files are missing.
 */
function tbf_nmi_missing_files_notice() {
  if ( ! is_admin() ) return;
  echo '<div class="notice notice-error"><p><strong>TBF Network Media Index</strong> is missing required files.</p>';
  echo '<p>Re-upload the full plugin folder including <code>includes/</code>, <code>assets/</code>, and <code>templates/</code>.</p></div>';
}

/**
 * Core requirements (modal + proxy)
 */
$required = [
  'includes/class-tbf-nmi-admin.php',
  'includes/class-tbf-nmi-ajax.php',
  'includes/class-tbf-nmi-proxy.php',
  'includes/class-tbf-nmi-placeholder.php',
  'includes/class-tbf-nmi-visibility.php',
  'includes/class-tbf-nmi-featured-media.php'
];

foreach ( $required as $rel ) {
  if ( ! file_exists( TBF_NMI_DIR . $rel ) ) {
    add_action('admin_notices', 'tbf_nmi_missing_files_notice');
    add_action('network_admin_notices', 'tbf_nmi_missing_files_notice');
    return;
  }
}

/**
 * Load existing core pieces
 */
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-admin.php';
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-ajax.php';
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-proxy.php';
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-placeholder.php';
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-visibility.php';
require_once TBF_NMI_DIR . 'includes/class-tbf-nmi-featured-media.php';

/**
 * Load v4 Photofall + Indexer + SEO + REST (if present).
 */
$optional = [
  // Photofall
  'includes/photofall/class-tbf-nmi-photofall-settings.php',
  'includes/photofall/class-tbf-nmi-photofall-router.php',
  'includes/photofall/class-tbf-nmi-photofall-templates.php',
  'includes/photofall/class-tbf-nmi-photofall.php',
  'includes/photofall/class-tbf-nmi-photofall-query.php',

  // REST
  'includes/api/class-tbf-nmi-rest.php',

  // Indexer
  'includes/indexer/class-tbf-nmi-indexer.php',
  'includes/indexer/class-tbf-nmi-indexer-admin.php',
  'includes/indexer/class-tbf-nmi-indexer-ajax.php',

  // SEO
  'includes/seo/class-tbf-nmi-robots.php',
  'includes/seo/class-tbf-nmi-seo-meta.php',
  'includes/seo/class-tbf-nmi-seo.php',
  'includes/seo/class-tbf-nmi-sitemaps.php',
  'includes/seo/class-tbf-nmi-sitemaps-admin.php',

  // Photofall admin
  'includes/admin/class-tbf-nmi-photofall-admin.php',
];

foreach ($optional as $rel) {
  $path = TBF_NMI_DIR . $rel;
  if ( file_exists($path) ) {
    require_once $path;
  }
}

/**
 * v4 Plugin Facade (singleton)
 */
class TBF_NMI_Plugin {

  private static $inst = null;

  public static function instance() {
    if ( self::$inst === null ) self::$inst = new self();
    return self::$inst;
  }

  public function defaults() {
    $defaults = [
      // legacy modal browser settings
      'insert_mode' => 'proxy',
      'per_page'    => 60,
      'max_sites'   => 5000,

      // photofall defaults
      'photofall_enabled' => 1,
      'photofall_public' => 1,
      'photofall_page_size' => 96,
      'photofall_cache_ttl' => 300,
      'photofall_sitemap_chunk' => 1000,
    ];

    if ( class_exists('TBF_NMI_PhotoFall_Settings') ) {
      $defaults = array_merge($defaults, TBF_NMI_PhotoFall_Settings::defaults());
    }

    return $defaults;
  }

  public function get_settings() {
    $defaults = $this->defaults();

    if ( is_multisite() ) {
      $saved = get_site_option('tbf_nmi_settings', []);
    } else {
      $saved = get_option('tbf_nmi_settings', []);
    }
    $s = is_array($saved) ? array_merge($defaults, $saved) : $defaults;

    // sanitize
    $s['per_page']    = max(10, min(200, (int)($s['per_page'] ?? 60)));
    $s['max_sites']   = max(1, min(20000, (int)($s['max_sites'] ?? 5000)));
    $s['insert_mode'] = in_array(($s['insert_mode'] ?? 'proxy'), ['proxy','url'], true) ? $s['insert_mode'] : 'proxy';

    $s['photofall_enabled'] = ! empty($s['photofall_enabled']) ? 1 : 0;
    $s['photofall_public']  = ! empty($s['photofall_public']) ? 1 : 0;
    $s['photofall_page_size'] = max(24, min(200, (int)($s['photofall_page_size'] ?? 96)));
    $s['photofall_cache_ttl'] = max(0, min(3600, (int)($s['photofall_cache_ttl'] ?? 300)));
    $s['photofall_sitemap_chunk'] = max(200, min(5000, (int)($s['photofall_sitemap_chunk'] ?? 1000)));

    return $s;
  }
}

/**
 * Keep the old class name working.
 */
class TBF_Network_Media_Index {

  public static function init() {
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
    add_filter('media_view_strings', [__CLASS__, 'add_media_tab_string']);

    TBF_NMI_Placeholder::init();
    TBF_NMI_Visibility::init();
  }

  public static function add_media_tab_string($strings) {
    $strings['tbfNetworkMediaTitle'] = __('Network Media', 'tbf-network-media-index');
    return $strings;
  }

  public static function get_settings() {
    return TBF_NMI_Plugin::instance()->get_settings();
  }

  public static function can_browse() {
    return is_user_logged_in();
  }

  public static function enqueue_assets($hook) {
    if ( ! is_admin() ) return;
    if ( ! self::can_browse() ) return;

    wp_enqueue_media();

    if ( file_exists(TBF_NMI_DIR . 'assets/css/admin.css') ) {
      wp_enqueue_style('tbf-nmi-admin', TBF_NMI_URL . 'assets/css/admin.css', [], TBF_NMI_VER);
    }

    if ( file_exists(TBF_NMI_DIR . 'assets/js/modal.js') ) {
      wp_enqueue_script(
        'tbf-nmi-modal',
        TBF_NMI_URL . 'assets/js/modal.js',
        ['jquery', 'media-views', 'media-editor', 'wp-util', 'underscore', 'backbone'],
        TBF_NMI_VER,
        true
      );
    }

    if ( file_exists(TBF_NMI_DIR . 'assets/js/admin-page.js') ) {
      wp_enqueue_script(
        'tbf-nmi-admin-page',
        TBF_NMI_URL . 'assets/js/admin-page.js',
        ['jquery', 'tbf-nmi-modal'],
        TBF_NMI_VER,
        true
      );
    }

    $s = self::get_settings();

    if ( wp_script_is('tbf-nmi-modal', 'enqueued') ) {
      wp_localize_script('tbf-nmi-modal', 'TBF_NMI', [
        'ajax'          => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('tbf_nmi_nonce'),
        'insertMode'    => $s['insert_mode'],
        'perPage'       => (int)$s['per_page'],
        'maxSites'      => (int)$s['max_sites'],
        'isMultisite'   => is_multisite() ? 1 : 0,
        'placeholderId' => (int) TBF_NMI_Placeholder::get_id(),
      ]);
    }
  }
}

/**
 * Activation: ensure tables + rewrite rules exist.
 */
function tbf_nmi_activate() {
  if ( class_exists('TBF_NMI_Indexer') ) {
    $idx = new TBF_NMI_Indexer();
    if ( ! $idx->has_table() ) $idx->create_table();
  }

  if ( class_exists('TBF_NMI_PhotoFall_Router') ) {
    TBF_NMI_PhotoFall_Router::register();
  }
  if ( class_exists('TBF_NMI_Sitemaps') ) {
    TBF_NMI_Sitemaps::rewrite_rules();
  }

  flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'tbf_nmi_activate');

function tbf_nmi_deactivate() {
  flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'tbf_nmi_deactivate');

/**
 * Boot everything
 */
TBF_Network_Media_Index::init();
TBF_NMI_Admin::init();
TBF_NMI_AJAX::init();

if ( class_exists('TBF_NMI_PhotoFall') ) {
  TBF_NMI_PhotoFall::init();
}

if ( class_exists('TBF_NMI_REST') ) {
  add_action('rest_api_init', ['TBF_NMI_REST', 'register_routes']);
}

if ( class_exists('TBF_NMI_Indexer_Admin') ) {
  TBF_NMI_Indexer_Admin::init();
}
if ( class_exists('TBF_NMI_Indexer_AJAX') ) {
  TBF_NMI_Indexer_AJAX::init();
}

if ( class_exists('TBF_NMI_PhotoFall_Admin') ) {
  TBF_NMI_PhotoFall_Admin::init();
}

if ( class_exists('TBF_NMI_SEO') ) {
  TBF_NMI_SEO::init();
}
if ( class_exists('TBF_NMI_Sitemaps') ) {
  TBF_NMI_Sitemaps::init();
}
if ( class_exists('TBF_NMI_Sitemaps_Admin') ) {
  TBF_NMI_Sitemaps_Admin::init();
}
