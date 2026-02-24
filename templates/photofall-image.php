<?php
/**
 * File: templates/photofall-image.php
 * Version: 6.5.0 (Strict Late Escaping)
 *
 * Photofall Image Detail Page
 */
if ( ! defined('ABSPATH') ) exit;

$blogId = (int) get_query_var('tbf_pf_blog_id');
$attId  = (int) get_query_var('tbf_pf_att_id');

$q    = new TBFNMI_PhotoFall_Query();
$item = $q->get_item($blogId, $attId);

if ( ! $item ) {
  echo '<div class="tbf-photofall"><div class="tbf-photofall__wrap"><p>' . esc_html__('Image not found.', 'tbf-network-media-index') . '</p></div></div>';
  return;
}

$full   = (string) ($item['url_full'] ?? '');
$medium = (string) ($item['url_medium'] ?? '');
$src    = $medium ?: $full;

echo '<div class="tbf-photofall tbf-photofall--detail"><div class="tbf-photofall__wrap">';
echo '<article class="tbf-photofall__detail">';

echo '<header class="tbf-photofall__header">';
echo '<h1 class="tbf-photofall__title">' . esc_html((string) ($item['title'] ?? '')) . '</h1>';
echo '</header>';

echo '<figure class="tbf-photofall__figure">';
echo '<a class="tbf-photofall__full-link" href="' . esc_url($full) . '" target="_blank" rel="noopener">';
echo '<img class="tbf-photofall__full tbf-pf-detail__img" src="' . esc_url($src) . '" alt="' . esc_attr((string) ($item['alt'] ?? '')) . '" loading="eager" decoding="async" />';
echo '</a>';
if ( !empty($item['caption']) ) echo '<figcaption class="tbf-photofall__caption">' . esc_html((string) ($item['caption'] ?? '')) . '</figcaption>';
echo '</figure>';

echo '<dl class="tbf-photofall__facts">';
echo '<div><dt>Uploaded</dt><dd>' . esc_html((string) ($item['created_gmt'] ?? '')) . '</dd></div>';
echo '<div><dt>Dimensions</dt><dd>' . esc_html((int) ($item['width'] ?? 0)) . '×' . esc_html((int) ($item['height'] ?? 0)) . '</dd></div>';
echo '<div><dt>Type</dt><dd>Image</dd></div>';
echo '</dl>';

$related = $q->related($blogId, $attId, 12);
if ( $related ) {
  echo '<section class="tbf-photofall__related">';
  echo '<h2>Related</h2>';
  echo '<div class="tbf-photofall__grid">';
  foreach ( $related as $r ) {
    $thumbRaw = (string) ($r['thumb_url'] ?? '');
    if ($thumbRaw === '') $thumbRaw = (string) ($r['poster_url'] ?? '');
    if ($thumbRaw === '') $thumbRaw = (string) ($r['url_full'] ?? '');

    if ($thumbRaw === '') continue; // avoid <img src="">

    echo '<article class="tbf-pf-card">';
    echo '<a class="tbf-pf-card__link" href="' . esc_url((string) ($r['href'] ?? '')) . '">';
    echo '<img class="tbf-pf-card__img" src="' . esc_url($thumbRaw) . '" alt="" loading="lazy" decoding="async" />';
    if ( (string) ($r['media_type'] ?? '') === 'video' ) echo '<span class="tbf-pf-card__badge">▶</span>';
    echo '</a></article>';
  }
  echo '</div></section>';
}

echo '</article>';
echo '</div></div>';