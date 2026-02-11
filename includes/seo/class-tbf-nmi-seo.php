<?php
/**
 * File: includes/seo/class-tbf-nmi-seo.php
 * Version: 4.0.0
 *
 * SEO bootstrap.
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_SEO {
  public static function init() {
    if ( class_exists('TBF_NMI_Robots') ) TBF_NMI_Robots::init();
    if ( class_exists('TBF_NMI_SEO_Meta') ) TBF_NMI_SEO_Meta::init();
  }
}
