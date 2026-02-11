<?php
/**
 * File: includes/photofall/class-tbf-nmi-photofall.php
 * Version: 4.1.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_PhotoFall {

  /**
   * Only run Photofall on the hub site (/1drop).
   * If your hub blog_id ever changes, update this.
   */
  public static function target_blog_id() {
    return 2;
  }

  public static function should_boot() {
    if ( ! is_multisite() ) return true;
    return (int) get_current_blog_id() === (int) self::target_blog_id();
  }

  public static function init() {
    // Hard stop: do not add routes/templates on other subsites
    if ( ! self::should_boot() ) return;

    if ( class_exists('TBF_NMI_PhotoFall_Router') ) {
      // some versions used register(), others used init_rewrites()
      add_action('init', [__CLASS__, 'routes'], 1);
    }

    if ( class_exists('TBF_NMI_PhotoFall_Templates') ) {
      add_action('init', [__CLASS__, 'templates'], 2);
    }
  }

  public static function routes() {
    if ( class_exists('TBF_NMI_PhotoFall_Router') ) {
      if ( method_exists('TBF_NMI_PhotoFall_Router', 'register') ) {
        TBF_NMI_PhotoFall_Router::register();
      } elseif ( method_exists('TBF_NMI_PhotoFall_Router', 'init_rewrites') ) {
        TBF_NMI_PhotoFall_Router::init_rewrites();
      }
    }
  }

  public static function templates() {
    if ( class_exists('TBF_NMI_PhotoFall_Templates') ) {
      TBF_NMI_PhotoFall_Templates::init();
    }
  }
}
