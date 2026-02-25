<?php
/**
 * File: includes/class-tbfnmi-featured-media.php
 * Version: 6.5.15 (Native REST Sanitization & CORS Fix)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Featured_Media {

  public static function register() {
    add_action('init', [__CLASS__, 'register_meta'], 99);
    add_filter('post_thumbnail_html', [__CLASS__, 'override_thumbnail_html'], 99, 5);
  }

  public static function register_meta() {
    // CRITICAL FIX: Using native 'esc_url_raw' prevents the REST API from rejecting 
    // the Vimeo signatures during Gutenberg auto-saves, stopping the disappearance bug.
    $args_url = [
      'type'              => 'string',
      'single'            => true,
      'show_in_rest'      => true,
      'sanitize_callback' => 'esc_url_raw'
    ];
    
    $args_str = [
      'type'              => 'string',
      'single'            => true,
      'show_in_rest'      => true,
      'sanitize_callback' => 'sanitize_text_field'
    ];

    $post_types = get_post_types(['public' => true], 'names');
    
    foreach ($post_types as $pt) {
        register_post_meta($pt, '_tbfnmi_featured_url', $args_url);
        register_post_meta($pt, '_tbfnmi_featured_mime', $args_str);
        register_post_meta($pt, '_tbfnmi_featured_type', $args_str);
    }
  }

  public static function override_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
    $url = get_post_meta($post_id, '_tbfnmi_featured_url', true);
    
    if ( !empty($url) ) {
        $alt = get_the_title($post_id);
        $url = html_entity_decode($url); // Ensures clean URL for processing
        
        // CRITICAL FIX: Removed crossorigin="anonymous" which was triggering Vimeo's CDN blocks.
        // Kept referrerpolicy="no-referrer" to bypass standard hotlink protection.
        return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" class="wp-post-image tbfnmi-remote-featured" loading="lazy" decoding="async" referrerpolicy="no-referrer" />';
    }
    
    return $html;
  }
}