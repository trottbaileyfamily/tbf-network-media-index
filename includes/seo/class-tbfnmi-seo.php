<?php
/**
 * File: includes/seo/class-tbfnmi-seo.php
 * Version: 4.0.0
 *
 * SEO bootstrap.
 */
if ( ! defined('ABSPATH') ) exit;

class TBFNMI_SEO {
  public static function init() {
    if ( class_exists('TBFNMI_Robots') ) TBFNMI_Robots::init();
    if ( class_exists('TBFNMI_SEO_Meta') ) TBFNMI_SEO_Meta::init();
  }
}
