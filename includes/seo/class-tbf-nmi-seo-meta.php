<?php
/**
 * File: includes/seo/class-tbf-nmi-seo-meta.php
 * Version: 4.0.0
 *
 * Minimal SEO meta + JSON-LD for Photofall detail pages.
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_SEO_Meta {

  public static function init() {
    add_action('wp_head', [__CLASS__, 'meta'], 5);
  }

  public static function meta() {
    if ( ! class_exists('TBF_NMI_PhotoFall_Router') ) return;

    $kind = sanitize_key((string)get_query_var('tbf_pf_kind'));
    if ( $kind !== 'image' && $kind !== 'video' ) return;

    $blogId = (int)get_query_var('tbf_pf_blog_id');
    $attId  = (int)get_query_var('tbf_pf_att_id');

    if ( $blogId <= 0 || $attId <= 0 ) return;

    $q = new TBF_NMI_PhotoFall_Query();
    $item = $q->get_item($blogId, $attId);
    if ( ! $item ) return;

    $title = $item['title'] ?: 'Photofall';
    $desc  = $item['caption'] ?: 'Trott Bailey Family Photofall.';
    $url   = $item['href'];
    $img   = $item['url_full'] ?: ($item['poster_url'] ?: $item['thumb_url']);

    echo "\n<!-- TBF Photofall SEO -->\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    if ($img) echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";

    // JSON-LD
    $schema = [
      '@context' => 'https://schema.org',
      '@type' => ($kind === 'video') ? 'VideoObject' : 'ImageObject',
      'name' => $title,
      'description' => $desc,
      'contentUrl' => ($kind === 'video') ? ($item['content_url'] ?: $item['embed_url']) : $item['url_full'],
      'thumbnailUrl' => ($kind === 'video') ? ($item['poster_url'] ?: $item['thumb_url']) : $item['thumb_url'],
      'uploadDate' => $item['created_gmt'] ? gmdate('c', strtotime($item['created_gmt'])) : gmdate('c'),
      'url' => $url,
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
  }
}
