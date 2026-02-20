<?php
/**
 * File: templates/photofall-video.php
 * Version: 4.0.0
 *
 * Photofall Video Detail Page
 */
if ( ! defined('ABSPATH') ) exit;

$blogId = (int)get_query_var('tbf_pf_blog_id');
$attId  = (int)get_query_var('tbf_pf_att_id');

$q = new TBFNMI_PhotoFall_Query();
$item = $q->get_item($blogId, $attId);

if ( ! $item ) {
  echo '<div class="tbf-photofall"><div class="tbf-photofall__wrap"><p>Video not found.</p></div></div>';
  return;
}

$title   = esc_html($item['title']);
$cap     = esc_html($item['caption']);
$poster  = esc_url($item['poster_url'] ?: $item['thumb_url']);
$content = (string)($item['content_url'] ?? '');
$embed   = (string)($item['embed_url'] ?? '');

echo '<div class="tbf-photofall tbf-photofall--detail"><div class="tbf-photofall__wrap">';
echo '<article class="tbf-photofall__detail">';

echo '<header class="tbf-photofall__header">';
echo '<h1 class="tbf-photofall__title">' . $title . '</h1>';
echo '</header>';

echo '<section class="tbf-photofall__player">';

if ( $content ) {
  echo '<video class="tbf-photofall__video" controls preload="metadata" poster="' . $poster . '">';
  echo '<source src="' . esc_url($content) . '" />';
  echo 'Your browser does not support the video tag.';
  echo '</video>';
} elseif ( $embed ) {
  echo '<div class="tbf-photofall__embed">';
  echo '<iframe src="' . esc_url($embed) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>';
  echo '</div>';
} else {
  echo '<div class="tbf-photofall__empty"><p>No playable source found for this video.</p></div>';
}

echo '</section>';

if ( $cap ) {
  echo '<p class="tbf-photofall__intro" style="margin-top:12px;">' . $cap . '</p>';
}

echo '<dl class="tbf-photofall__facts">';
echo '<div><dt>Uploaded</dt><dd>' . esc_html($item['created_gmt']) . '</dd></div>';
echo '<div><dt>Provider</dt><dd>' . esc_html($item['provider']) . '</dd></div>';
echo '</dl>';

$related = $q->related($blogId, $attId, 12);
if ($related) {
  echo '<section class="tbf-photofall__related">';
  echo '<h2>Related</h2>';
  echo '<div class="tbf-photofall__grid">';
  foreach ($related as $r) {
    $href  = esc_url($r['href']);
    $thumb = esc_url($r['thumb_url'] ?: $r['poster_url']);
    echo '<article class="tbf-pf-card">';
    echo '<a class="tbf-pf-card__link" href="' . $href . '">';
    echo '<img class="tbf-pf-card__img" src="' . $thumb . '" alt="" loading="lazy" />';
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
