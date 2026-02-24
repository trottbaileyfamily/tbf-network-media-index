<?php
/**
 * File: templates/photofall-video.php
 * Version: 6.5.0 (Strict Late Escaping)
 *
 * Photofall Video Detail Page
 */
if ( ! defined('ABSPATH') ) exit;

$blogId = (int)get_query_var('tbf_pf_blog_id');
$attId  = (int)get_query_var('tbf_pf_att_id');

$q = new TBFNMI_PhotoFall_Query();
$item = $q->get_item($blogId, $attId);

if ( ! $item ) {
  echo '<div class="tbf-photofall"><div class="tbf-photofall__wrap"><p>' . esc_html__('Video not found.', 'tbf-network-media-index') . '</p></div></div>';
  return;
}

$poster  = (string)($item['poster_url'] ?: $item['thumb_url']);
$content = (string)($item['content_url'] ?? '');
$embed   = (string)($item['embed_url'] ?? '');

echo '<div class="tbf-photofall tbf-photofall--detail"><div class="tbf-photofall__wrap">';
echo '<article class="tbf-photofall__detail">';

echo '<header class="tbf-photofall__header">';
echo '<h1 class="tbf-photofall__title">' . esc_html((string)($item['title'] ?? '')) . '</h1>';
echo '</header>';

echo '<section class="tbf-photofall__player">';

if ( $content ) {
  echo '<video class="tbf-photofall__video" controls preload="metadata" poster="' . esc_url($poster) . '">';
  echo '<source src="' . esc_url($content) . '" />';
  echo esc_html__('Your browser does not support the video tag.', 'tbf-network-media-index');
  echo '</video>';
} elseif ( $embed ) {
  echo '<div class="tbf-photofall__embed">';
  echo '<iframe src="' . esc_url($embed) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>';
  echo '</div>';
} else {
  echo '<div class="tbf-photofall__empty"><p>' . esc_html__('No playable source found for this video.', 'tbf-network-media-index') . '</p></div>';
}

echo '</section>';

if ( !empty($item['caption']) ) {
  echo '<p class="tbf-photofall__intro" style="margin-top:12px;">' . esc_html((string)($item['caption'] ?? '')) . '</p>';
}

echo '<dl class="tbf-photofall__facts">';
echo '<div><dt>Uploaded</dt><dd>' . esc_html((string)($item['created_gmt'] ?? '')) . '</dd></div>';
echo '<div><dt>Provider</dt><dd>' . esc_html((string)($item['provider'] ?? '')) . '</dd></div>';
echo '</dl>';

$related = $q->related($blogId, $attId, 12);
if ($related) {
  echo '<section class="tbf-photofall__related">';
  echo '<h2>Related</h2>';
  echo '<div class="tbf-photofall__grid">';
  foreach ($related as $r) {
    $thumb = (string)($r['thumb_url'] ?: $r['poster_url']);
    
    echo '<article class="tbf-pf-card">';
    echo '<a class="tbf-pf-card__link" href="' . esc_url((string)($r['href'] ?? '')) . '">';
    echo '<img class="tbf-pf-card__img" src="' . esc_url($thumb) . '" alt="" loading="lazy" />';
    if (($r['media_type'] ?? '') === 'video') {
      echo '<span class="tbf-pf-card__badge">▶</span>';
    }
    echo '</a>';
    echo '</article>';
  }
  echo '</div></section>';
}

echo '</article>';
echo '</div></div>';