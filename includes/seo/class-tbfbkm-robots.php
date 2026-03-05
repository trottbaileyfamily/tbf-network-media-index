<?php
/**
 * File: includes/seo/class-tbfbkm-robots.php
 * Version: 4.0.0
 *
 * Adds allow rules for Photofall and sitemaps.
 */
if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Robots {
  public static function init() {
    add_filter('robots_txt', [__CLASS__, 'robots'], 20, 2);
  }

  public static function robots($output, $public) {
    // If site is not public, still keep output as-is (Search engines may not crawl).
    $home = home_url('/');
    $lines = [];
    $lines[] = '';
    $lines[] = '# TBF Photofall';
    $lines[] = 'Sitemap: ' . $home . 'photo-sitemap-index.xml';
    $lines[] = 'Sitemap: ' . $home . 'video-sitemap-index.xml';
    return $output . "\n" . implode("\n", $lines) . "\n";
  }
}

