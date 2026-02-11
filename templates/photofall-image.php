<?php
/**
 * File: templates/photofall-image.php
 * Version: 4.0.0
 *
 * Photofall Image Detail Page
 */
if ( ! defined('ABSPATH') ) exit;

$blogId = (int)get_query_var('tbf_pf_blog_id');
$attId  = (int)get_query_var('tbf_pf_att_id');

$q = new TBF_NMI_PhotoFall_Query();
$item = $q->get_item($blogId, $attId);

if ( ! $item ) {
  echo '<div class="tbf-photofall"><div class="tbf-photofall__wrap"><p>Image not found.</p></div></div>';
  return;
}

$title = esc_html($item['title']);
$cap   = esc_html($item['caption']);
$full  = esc_url($item['url_full']);
$alt   = esc_attr($item['alt']);

echo '<div class="tbf-photofall tbf-photofall--detail"><div class="tbf-photofall__wrap">';
echo '<article class="tbf-photofall__detail">';

echo '<header class="tbf-photofall__header">';
echo '<h1 class="tbf-photofall__title">' . $title . '</h1>';
echo '</header>';

echo '<figure class="tbf-photofall__figure">';
echo '<img class="tbf-photofall__full" src="' . $full . '" alt="' . $alt . '" loading="eager" decoding="async" />';
if ($cap) echo '<figcaption class="tbf-photofall__caption">' . $cap . '</figcaption>';
echo '</figure>';

echo '<dl class="tbf-photofall__facts">';
echo '<div><dt>Uploaded</dt><dd>' . esc_html($item['created_gmt']) . '</dd></div>';
echo '<div><dt>Dimensions</dt><dd>' . esc_html((int)$item['width']) . '×' . esc_html((int)$item['height']) . '</dd></div>';
echo '<div><dt>Type</dt><dd>Image</dd></div>';
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
    if (($r['media_type'] ?? '') === 'video') echo '<span class="tbf-pf-card__badge">▶</span>';
    echo '</a></article>';
  }
  echo '</div></section>';
}

echo '</article>';
echo '</div></div>';
