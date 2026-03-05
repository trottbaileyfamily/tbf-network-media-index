<?php
/**
 * File: includes/photofall/class-tbfbkm-photofall-settings.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBFBKM_PhotoFall_Settings {
  public static function defaults() {
    return [
      'photofall_enabled' => 1,
      'photofall_public' => 1,
      'photofall_page_size' => 96,
      'photofall_cache_ttl' => 300,
      'photofall_sitemap_chunk' => 1000,
    ];
  }
}

