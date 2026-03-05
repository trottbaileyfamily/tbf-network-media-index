<?php
/**
 * File: includes/photofall/class-tbfbkm-photofall.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBFBKM_PhotoFall {
  public static function init() {
    if ( class_exists('TBFBKM_PhotoFall_Router') ) {
      add_action('init', [__CLASS__, 'routes'], 1);
    }
    if ( class_exists('TBFBKM_PhotoFall_Templates') ) {
      add_action('init', [__CLASS__, 'templates'], 2);
    }
  }
  public static function routes(){ TBFBKM_PhotoFall_Router::register(); }
  public static function templates(){ TBFBKM_PhotoFall_Templates::init(); }
}

