<?php
/**
 * File: includes/seo/class-tbfnmi-seo.php
 * Version: 6.5.2 (Strict SEO Bootstrap)
 *
 * SEO bootstrap.
 */
if ( ! defined('ABSPATH') ) exit;

class TBFNMI_SEO {
  public static function init() {
    // 1. Load the XML Sitemap Generator Engine
    if ( class_exists('TBFNMI_Sitemaps') ) {
        TBFNMI_Sitemaps::init();
    }
    
    // 2. Load the Robots & Canonical Directives
    if ( class_exists('TBFNMI_Robots') ) {
        TBFNMI_Robots::init();
    }
    
    // 3. Load the Dynamic OpenGraph & Meta Tags
    if ( class_exists('TBFNMI_SEO_Meta') ) {
        TBFNMI_SEO_Meta::init();
    }
    
    // Note: The Sitemap Admin UI is natively handled by class-tbfnmi-subsite-settings.php in v6.5.2+
  }
}