<?php
/**
 * File: includes/photofall/class-tbf-nmi-photofall.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_PhotoFall {
  public static function init() {
    if ( class_exists('TBF_NMI_PhotoFall_Router') ) {
      add_action('init', [__CLASS__, 'routes'], 1);
    }
    if ( class_exists('TBF_NMI_PhotoFall_Templates') ) {
      add_action('init', [__CLASS__, 'templates'], 2);
    }
  }
  public static function routes(){ TBF_NMI_PhotoFall_Router::register(); }
  public static function templates(){ TBF_NMI_PhotoFall_Templates::init(); }
}
