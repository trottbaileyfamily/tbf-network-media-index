<?php
/**
 * File: templates/photofall-archive.php
 * Version: 6.6.8 (Fixed Upload Authorization & Permalink Routing)
 */
if ( ! defined('ABSPATH') ) exit;

$route  = sanitize_key((string)get_query_var('tbf_pf_route'));
$page   = max(1, (int)get_query_var('tbf_pf_page'));
$blogId = (int)get_query_var('tbf_pf_blog_id');
$year   = (int)get_query_var('tbf_pf_year');
$month  = (int)get_query_var('tbf_pf_month');
$tag    = sanitize_title((string)get_query_var('tbf_pf_tag'));

// Force defaults so missing backend saves do not lock out the UI
$opts = class_exists('TBFNMI_Subsite_Settings') ? TBFNMI_Subsite_Settings::get_options() : get_option('tbfnmi_photofall_options', []);
$settings = class_exists('TBFNMI_Plugin') ? TBFNMI_Plugin::instance()->get_settings() : [];
$pageSize = isset($settings['photofall_page_size']) ? (int)$settings['photofall_page_size'] : 96;

$q = new TBFNMI_PhotoFall_Query();

$list = $q->list([
  'route' => $route, 'page' => $page, 'page_size' => $pageSize,
  'blog_id' => $blogId, 'year' => $year, 'month' => $month,
  'tag' => $tag, 'type' => 'all', 'provider' => 'all', 'q' => '',
]);

$items = $list['items'];
$hasMore = ! empty($list['has_more']);

$title = 'Photofall';
if ($route === 'site')  $title = 'Site ' . $blogId;
if ($route === 'year')  $title = (string)$year;
if ($route === 'month') $title = sprintf('%04d-%02d', $year, $month);
if ($route === 'tag')   $title = 'Tag: ' . $tag;

$placeholderUrl = '';
if (class_exists('TBFNMI_Placeholder')) {
  $pid = (int) TBFNMI_Placeholder::get_id();
  if ($pid) $placeholderUrl = (string) wp_get_attachment_url($pid);
}

// Security Override
$tab = isset($_GET['tbf_tab']) ? sanitize_text_field($_GET['tbf_tab']) : 'active';
$hidden_items = get_option('tbfnmi_hidden_media', []);

$can_upload = false;
$is_admin = false;

if ( is_user_logged_in() ) {
    if ( current_user_can('manage_options') || is_super_admin() ) {
        $can_upload = true;
        $is_admin = true;
    } elseif ( !empty($opts['enable_frontend_upload']) ) {
        $user = wp_get_current_user();
        $allowed_roles = !empty($opts['upload_roles']) ? $opts['upload_roles'] : ['administrator'];
        if ( !empty(array_intersect($allowed_roles, $user->roles)) ) {
            $can_upload = true;
        }
    }
}

global $wpdb;
$usage_table = $wpdb->base_prefix . 'tbfnmi_usage_map';
$has_usage_table = !empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($usage_table))));
?>

<div class="tbf-photofall"><div class="tbf-photofall__wrap">

<header class="tbf-photofall__header" style="position:relative;">
    <div>
        <h1 class="tbf-photofall__title"><?php echo esc_html($title); ?></h1>
        <p class="tbf-photofall__intro"><?php esc_html_e('AgriGames Photofall: Discover our free experiences, unique architecture, camping, and play areas.', 'tbf-network-media-index'); ?></p>

        <?php if ( $is_admin ): ?>
        <div style="margin-top: 15px; font-weight: bold;">
            <a href="?tbf_tab=active" style="margin-right: 15px; text-decoration: none; color: <?php echo $tab === 'active' ? '#2271b1' : '#555'; ?>;">Live Media</a>
            <a href="?tbf_tab=hidden" style="text-decoration: none; color: <?php echo $tab === 'hidden' ? '#2271b1' : '#555'; ?>;">Hidden Media</a>
        </div>
        <?php endif; ?>
    </div>

    <div style="display: flex; gap: 15px; align-items: flex-end;">
        <div class="tbf-photofall__search" style="min-width:260px;">
            <label style="display:block; font-size:12px; opacity:.7; margin-bottom:6px;">Search</label>
            <input type="search" placeholder="Search titles, captions..." style="width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(0,0,0,.2);" />
        </div>

        <?php if ( $can_upload && $tab === 'active' ): ?>
            <button id="tbfnmi-trigger-upload" style="background: #2271b1; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; white-space: nowrap;">Upload Media</button>
        <?php endif; ?>
    </div>
</header>

<main class="tbf-photofall__main">
<div class="tbf-photofall__grid">
<?php
foreach ($items as $it) {
  $att_id = (int)($it['attachment_id'] ?? 0);
  $is_hidden = in_array($att_id, $hidden_items);

  if ( $tab === 'active' && $is_hidden ) continue;
  if ( $tab === 'hidden' && !$is_hidden ) continue;

  $thumbRaw = (string)($it['thumb_url'] ?? '');
  if ($thumbRaw === '') $thumbRaw = (string)($it['poster_url'] ?? '');
  if ($thumbRaw === '') $thumbRaw = (string)($it['url_full'] ?? '');
  if ($thumbRaw === '' && $placeholderUrl) $thumbRaw = $placeholderUrl;

  $url_full = $it['url_full'] ?? '';
  $source_title = '';
  $source_url = '';

  if ($has_usage_table && !empty($url_full)) {
      $usage = $wpdb->get_row($wpdb->prepare("SELECT post_title, permalink FROM {$usage_table} WHERE media_url = %s ORDER BY id DESC LIMIT 1", $url_full));
      if ($usage) {
          $source_title = $usage->post_title;
          $source_url = $usage->permalink;
      }
  }

  echo '<article class="tbf-pf-card" style="position:relative;" onmouseover="this.querySelector(\'.tbf-pf-admin-controls\').style.display=\'flex\'" onmouseout="if(this.querySelector(\'.tbf-pf-admin-controls\')) this.querySelector(\'.tbf-pf-admin-controls\').style.display=\'none\'">';
  
  if ( $is_admin ) {
      echo '<div class="tbf-pf-admin-controls" style="display:none; position:absolute; top:10px; right:10px; z-index:10; background:rgba(0,0,0,0.8); padding:5px; border-radius:4px; gap:5px;">';
      echo '<button onclick="tbfnmiToggleHide(' . $att_id . ')" style="background:#fff; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:bold; color:#555;">' . ($tab === 'hidden' ? 'Unhide' : 'Hide') . '</button>';
      echo '<button onclick="tbfnmiDeleteMedia(' . $att_id . ')" style="background:#d63638; color:#fff; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:bold;">Delete</button>';
      echo '</div>';
  }

  echo '<a class="tbf-pf-card__link" href="' . esc_url($it['href']) . '" onclick="event.preventDefault(); tbfnmi_photofall.open(this.querySelector(\'img\'));">';
  echo '<img class="tbf-pf-card__img" src="' . esc_url($thumbRaw) . '" data-full="' . esc_url($url_full) . '" data-type="' . esc_attr($it['media_type'] ?? 'image') . '" data-caption="' . esc_attr($it['caption'] ?? $it['title'] ?? '') . '" data-source-title="' . esc_attr($source_title) . '" data-source-url="' . esc_url($source_url) . '" data-permalink="' . esc_url($it['href']) . '" alt="' . esc_attr($it['alt'] ?? '') . '" loading="lazy" />';
  if (($it['media_type'] ?? '') === 'video') echo '<span class="tbf-pf-card__badge">▶</span>';
  echo '</a>';
  
  echo '<h2 class="tbf-pf-card__title"><a href="' . esc_url($it['href']) . '">' . esc_html((string)($it['title'] ?? '')) . '</a></h2>';
  echo '</article>';
}
?>
</div>

<?php
$base = '/' . trim(TBFNMI_PHOTOFALL_BASE, '/') . '/';
$baseUrl = home_url($base);
switch ($route) {
  case 'site':  $baseUrl = home_url($base . 'site/' . $blogId . '/'); break;
  case 'year':  $baseUrl = home_url($base . $year . '/'); break;
  case 'month': $baseUrl = home_url($base . $year . '/' . sprintf('%02d', $month) . '/'); break;
  case 'tag':   $baseUrl = home_url($base . 'tag/' . $tag . '/'); break;
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
?>

<div class="tbf-photofall__sentinel"></div>
</main>
</div></div>

<div id="tbf-lightbox" class="tbf-lightbox">
    <span class="tbf-close">&times;</span>
    <span class="tbf-prev">&#10094;</span>
    <span class="tbf-next">&#10095;</span>
    <div class="tbf-lightbox-content">
        <img id="tbf-lb-img" src="" alt="" style="display:none;">
        <video id="tbf-lb-video" controls style="display:none;"></video>
        <audio id="tbf-lb-audio" controls style="display:none;"></audio>
        <div class="tbf-lb-meta">
            <p id="tbf-lb-caption"></p>
            <a id="tbf-lb-source-link" href="#" target="_blank" style="display:none; text-decoration:none; background:#2271b1; color:#fff; padding:10px 20px; border-radius:6px; font-weight:bold; font-size:14px; margin-top:10px;"></a>
        </div>
    </div>
</div>

<?php if ( $can_upload ): ?>
<div id="tbfnmi-upload-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; padding:30px; border-radius:12px; width:90%; max-width:500px; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <span onclick="document.getElementById('tbfnmi-upload-modal').style.display='none'" style="position:absolute; top:15px; right:20px; font-size:28px; cursor:pointer; color:#555;">&times;</span>
        <h2 style="margin-top:0;">Upload to Photofall</h2>
        <form id="tbfnmi-frontend-upload-form" enctype="multipart/form-data">
            <input type="file" name="tbfnmi_media[]" multiple accept="image/*,video/*,audio/*" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;">
            <input type="text" name="tbfnmi_title" placeholder="Media Title" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px;">
            <textarea name="tbfnmi_description" placeholder="Description of the space..." style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px; height:100px; resize:vertical;"></textarea>
            <input type="hidden" name="action" value="tbfnmi_frontend_upload">
            <input type="hidden" name="security" value="<?php echo wp_create_nonce('tbfnmi_frontend_upload_nonce'); ?>">
            <button type="submit" style="width:100%; background:#2271b1; color:#fff; border:none; padding:12px; border-radius:6px; font-weight:bold; cursor:pointer;">Upload Files</button>
        </form>
        <div id="tbfnmi-upload-progress" style="margin-top:15px; font-weight:bold; text-align:center;"></div>
    </div>
</div>
<?php endif; ?>

<?php if ( $is_admin ): ?>
<script>
function tbfnmiToggleHide(id) {
    const formData = new FormData();
    formData.append('action', 'tbfnmi_hide_media');
    formData.append('attachment_id', id);
    formData.append('nonce', '<?php echo wp_create_nonce('tbfnmi_admin_action'); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => { if(data.success) location.reload(); });
}

function tbfnmiDeleteMedia(id) {
    if (!confirm('WARNING: This permanently wipes this media file from the local library and the network index. Proceed?')) return;
    const formData = new FormData();
    formData.append('action', 'tbfnmi_delete_media');
    formData.append('attachment_id', id);
    formData.append('nonce', '<?php echo wp_create_nonce('tbfnmi_admin_action'); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => { if(data.success) location.reload(); });
}
</script>
<?php endif; ?>