<?php
/**
 * File: templates/photofall-image.php
 * Version: 4.1.2
 *
 * Photofall Image Detail Page
 */
if ( ! defined('ABSPATH') ) exit;

$blogId = (int) get_query_var('tbf_pf_blog_id');
$attId  = (int) get_query_var('tbf_pf_att_id');

$q    = new TBF_NMI_PhotoFall_Query();
$item = $q->get_item($blogId, $attId);

if ( ! $item ) {
  echo '<div class="tbf-photofall"><div class="tbf-photofall__wrap"><p>Image not found.</p></div></div>';
  return;
}

$title = esc_html((string) ($item['title'] ?? ''));
$cap   = esc_html((string) ($item['caption'] ?? ''));
$alt   = esc_attr((string) ($item['alt'] ?? ''));

$full   = esc_url((string) ($item['url_full'] ?? ''));
$medium = esc_url((string) ($item['url_medium'] ?? ''));
$src    = $medium ?: $full;

echo '<div class="tbf-photofall tbf-photofall--detail"><div class="tbf-photofall__wrap">';
echo '<article class="tbf-photofall__detail">';

echo '<header class="tbf-photofall__header">';
echo '<h1 class="tbf-photofall__title">' . $title . '</h1>';
echo '</header>';

echo '<figure class="tbf-photofall__figure">';
echo '<a class="tbf-photofall__full-link" href="' . $full . '" target="_blank" rel="noopener">';
echo '<img class="tbf-photofall__full tbf-pf-detail__img" src="' . $src . '" alt="' . $alt . '" loading="eager" decoding="async" />';
echo '</a>';
if ( $cap ) echo '<figcaption class="tbf-photofall__caption">' . $cap . '</figcaption>';
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
    $href = esc_url((string) ($r['href'] ?? ''));

    $thumbRaw = (string) ($r['thumb_url'] ?? '');
    if ($thumbRaw === '') $thumbRaw = (string) ($r['poster_url'] ?? '');
    if ($thumbRaw === '') $thumbRaw = (string) ($r['url_full'] ?? '');

    if ($thumbRaw === '') continue; // avoid <img src="">

    $thumb = esc_url($thumbRaw);

    echo '<article class="tbf-pf-card">';
    echo '<a class="tbf-pf-card__link" href="' . $href . '">';
    echo '<img class="tbf-pf-card__img" src="' . $thumb . '" alt="" loading="lazy" decoding="async" />';
    if ( (string) ($r['media_type'] ?? '') === 'video' ) echo '<span class="tbf-pf-card__badge">▶</span>';
    echo '</a></article>';
  }
  echo '</div></section>';
}

echo '</article>';
echo '</div></div>';
