<?php
/**
 * File: templates/photofall-archive.php
 * Version: 4.1.3
 *
 * Photofall Archive (HTML-first for SEO)
 */
if ( ! defined('ABSPATH') ) exit;

$route  = sanitize_key((string)get_query_var('tbf_pf_route'));
$page   = max(1, (int)get_query_var('tbf_pf_page'));
$blogId = (int)get_query_var('tbf_pf_blog_id');
$year   = (int)get_query_var('tbf_pf_year');
$month  = (int)get_query_var('tbf_pf_month');
$tag    = sanitize_title((string)get_query_var('tbf_pf_tag'));

$settings = class_exists('TBF_NMI_Plugin') ? TBF_NMI_Plugin::instance()->get_settings() : [];
$pageSize = isset($settings['photofall_page_size']) ? (int)$settings['photofall_page_size'] : 96;

$q = new TBF_NMI_PhotoFall_Query();

$list = $q->list([
  'route' => $route,
  'page' => $page,
  'page_size' => $pageSize,
  'blog_id' => $blogId,
  'year' => $year,
  'month' => $month,
  'tag' => $tag,
  'type' => 'all',
  'provider' => 'all',
  'q' => '',
]);

$items = $list['items'];
$hasMore = ! empty($list['has_more']);

$title = 'Photofall';
if ($route === 'site')  $title = 'Site ' . $blogId;
if ($route === 'year')  $title = (string)$year;
if ($route === 'month') $title = sprintf('%04d-%02d', $year, $month);
if ($route === 'tag')   $title = 'Tag: ' . $tag;

$placeholderUrl = '';
if (class_exists('TBF_NMI_Placeholder')) {
  $pid = (int) TBF_NMI_Placeholder::get_id();
  if ($pid) $placeholderUrl = (string) wp_get_attachment_url($pid);
}

echo '<div class="tbf-photofall"><div class="tbf-photofall__wrap">';

echo '<header class="tbf-photofall__header">';
echo '<div>';
echo '<h1 class="tbf-photofall__title">' . esc_html($title) . '</h1>';
echo '<p class="tbf-photofall__intro">Trott Bailey Family Photofall: images + videos across the network.</p>';
echo '</div>';

echo '<div class="tbf-photofall__search" style="min-width:260px;">';
echo '<label style="display:block; font-size:12px; opacity:.7; margin-bottom:6px;">Search</label>';
echo '<input type="search" placeholder="Search titles, captions, alt text…" style="width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(0,0,0,.2);" />';
echo '</div>';

echo '</header>';

echo '<main class="tbf-photofall__main">';
echo '<div class="tbf-photofall__grid">';

foreach ($items as $it) {
  $href = esc_url($it['href']);

  $thumbRaw = (string)($it['thumb_url'] ?? '');
  if ($thumbRaw === '') $thumbRaw = (string)($it['poster_url'] ?? '');
  if ($thumbRaw === '') $thumbRaw = (string)($it['url_full'] ?? '');
  if ($thumbRaw === '' && $placeholderUrl) $thumbRaw = $placeholderUrl;

  $thumb = esc_url($thumbRaw);
  $tt    = esc_html((string)($it['title'] ?? ''));
  $alt   = esc_attr((string)($it['alt'] ?? ''));

  echo '<article class="tbf-pf-card">';
  echo '<a class="tbf-pf-card__link" href="' . $href . '">';
  echo '<img class="tbf-pf-card__img" src="' . $thumb . '" alt="' . $alt . '" loading="lazy" />';
  if (($it['media_type'] ?? '') === 'video') {
    echo '<span class="tbf-pf-card__badge">▶</span>';
  }
  echo '</a>';
  echo '<h2 class="tbf-pf-card__title"><a href="' . $href . '">' . $tt . '</a></h2>';
  echo '</article>';
}

echo '</div>';

$base = '/' . trim(TBF_NMI_PHOTOFALL_BASE, '/') . '/';
$baseUrl = home_url($base);

switch ($route) {
  case 'site':  $baseUrl = home_url($base . 'site/' . $blogId . '/'); break;
  case 'year':  $baseUrl = home_url($base . $year . '/'); break;
  case 'month': $baseUrl = home_url($base . $year . '/' . sprintf('%02d', $month) . '/'); break;
  case 'tag':   $baseUrl = home_url($base . 'tag/' . $tag . '/'); break;
  case 'root':
  default:      $baseUrl = home_url($base); break;
}

$prevUrl = $page > 1 ? trailingslashit($baseUrl) . 'page/' . ($page - 1) . '/' : '';
$nextUrl = $hasMore ? trailingslashit($baseUrl) . 'page/' . ($page + 1) . '/' : '';

echo '<nav class="tbf-photofall__pagination"><div class="tbf-photofall__pagination-inner">';
if ($prevUrl) echo '<a class="tbf-pf-page" href="' . esc_url($prevUrl) . '">Previous</a>';
else echo '<span class="tbf-pf-page is-disabled">Previous</span>';
if ($nextUrl) echo '<a class="tbf-pf-page" href="' . esc_url($nextUrl) . '">Next</a>';
else echo '<span class="tbf-pf-page is-disabled">Next</span>';
echo '</div></nav>';

echo '<div class="tbf-photofall__sentinel"></div>';

echo '</main>';
echo '</div></div>';
