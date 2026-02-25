<?php
/**
 * File: includes/seo/class-tbfnmi-seo-meta.php
 * Version: 6.5.16 (SEO Interlinking Engine)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_SEO_Meta {

  public static function init() {
    add_action('wp_head', [__CLASS__, 'inject_opengraph_tags'], 5);
    // Hook into the native content filter to inject the backlink on attachment pages
    add_filter('the_content', [__CLASS__, 'append_origin_post_interlink']);
  }

  public static function inject_opengraph_tags() {
    if ( ! is_attachment() ) return;
    
    $post = get_post();
    if ( ! $post ) return;

    $url = wp_get_attachment_url($post->ID);
    if ( ! $url ) return;

    $title = get_the_title($post->ID);
    $mime  = get_post_mime_type($post->ID);

    echo "\n<!-- TBF Network Media Index: OpenGraph Engine -->\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '" />' . "\n";
    
    if ( strpos($mime, 'image/') === 0 ) {
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:image" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($url) . '" />' . "\n";
    } elseif ( strpos($mime, 'video/') === 0 ) {
        echo '<meta property="og:type" content="video.other" />' . "\n";
        echo '<meta property="og:video" content="' . esc_url($url) . '" />' . "\n";
    }
    echo "<!-- /TBF Network Media Index -->\n\n";
  }

  public static function append_origin_post_interlink($content) {
    // We strictly target native single attachment pages
    if ( ! is_attachment() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $opts = get_option('tbfnmi_photofall_options', []);
    if ( empty($opts['seo_interlink_origin']) ) return $content;

    $post_id = get_the_ID();
    
    // Read the tracker meta from the Proxy Engine
    $origin_blog_id = (int) get_post_meta($post_id, '_tbfnmi_origin_blog_id', true);
    $origin_att_id  = (int) get_post_meta($post_id, '_tbfnmi_origin_attachment_id', true);

    if ( $origin_blog_id <= 0 || $origin_att_id <= 0 ) return $content;

    $current_blog = get_current_blog_id();
    $switched = false;

    // We must safely jump across the network to find where this media was used
    if ( $current_blog !== $origin_blog_id ) {
        switch_to_blog($origin_blog_id);
        $switched = true;
    }

    $parent_id = wp_get_post_parent_id($origin_att_id);
    $target_id = $parent_id ? $parent_id : $origin_att_id;
    
    $permalink = get_permalink($target_id);
    $title     = get_the_title($target_id);
    $blog_name = get_bloginfo('name');
    
    if ( $switched ) restore_current_blog();

    if ( empty($permalink) ) return $content;
    if ( empty($title) ) $title = 'Article ' . $target_id;

    // Construct the high-SEO-value contextual link box
    $html  = '<div class="tbfnmi-seo-interlink-box" style="margin-top: 2.5em; padding: 1.5em; background: #fafafa; border-left: 4px solid #2271b1; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
    $html .= '<h4 style="margin-top: 0; margin-bottom: 0.5em; font-size: 1.1em; color: #1d2327;">Featured Context</h4>';
    $html .= '<p style="margin: 0; font-size: 0.95em; color: #3c434a; line-height: 1.5;">This media file was originally published in the article <strong><a href="' . esc_url($permalink) . '" style="color: #2271b1; text-decoration: none; font-weight: 600;" target="_blank" rel="noopener">' . esc_html($title) . '</a></strong> on ' . esc_html($blog_name) . '.</p>';
    $html .= '</div>';

    return $content . $html;
  }
}