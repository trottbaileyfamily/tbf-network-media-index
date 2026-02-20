<?php
/**
 * File: includes/photofall/class-tbfnmi-photofall.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBFNMI_PhotoFall {
  public static function init() {
    if ( class_exists('TBFNMI_PhotoFall_Router') ) {
      add_action('init', [__CLASS__, 'routes'], 1);
    }
    if ( class_exists('TBFNMI_PhotoFall_Templates') ) {
      add_action('init', [__CLASS__, 'templates'], 2);
    }
  }
  public static function routes(){ TBFNMI_PhotoFall_Router::register(); }
  public static function templates(){ TBFNMI_PhotoFall_Templates::init(); }
}
