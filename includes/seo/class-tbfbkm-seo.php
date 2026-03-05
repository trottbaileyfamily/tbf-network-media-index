<?php
/**
 * File: includes/seo/class-tbfbkm-seo.php
 * Version: 6.5.2 (Strict SEO Bootstrap)
 *
 * SEO bootstrap.
 */
if ( ! defined('ABSPATH') ) exit;

class TBFBKM_SEO {
  public static function init() {
    // 1. Load the XML Sitemap Generator Engine
    if ( class_exists('TBFBKM_Sitemaps') ) {
        TBFBKM_Sitemaps::init();
    }
    
    // 2. Load the Robots & Canonical Directives
    if ( class_exists('TBFBKM_Robots') ) {
        TBFBKM_Robots::init();
    }
    
    // 3. Load the Dynamic OpenGraph & Meta Tags
    if ( class_exists('TBFBKM_SEO_Meta') ) {
        TBFBKM_SEO_Meta::init();
    }
    
    // Note: The Sitemap Admin UI is natively handled by class-tbfbkm-subsite-settings.php in v6.5.2+
  }
}
