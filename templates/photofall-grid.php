<?php
/**
 * Template: Photofall Grid
 * Version: 6.5.0 (Strict Enqueue & JSON Encode)
 */
if ( ! defined('ABSPATH') ) exit;

get_header();

// Expect the templates class to localize these on the page:
$apiBase = esc_url_raw( home_url('/1drop/wp-json/tbf-photofall/v1') );
$placeholder = esc_url_raw( home_url('/wp-content/uploads/2026/02/tbfnmi-placeholder.png') );

// WordPress Mandatory Enqueue Standards
wp_enqueue_script('jquery');
$inline_script = "
    window.TBF_PHOTOFALL = window.TBF_PHOTOFALL || {};
    window.TBF_PHOTOFALL.apiBase = " . wp_json_encode($apiBase) . ";
    window.TBF_PHOTOFALL.placeholder = " . wp_json_encode($placeholder) . ";
    window.TBF_PHOTOFALL.pageSize = window.TBF_PHOTOFALL.pageSize || 24;
";
wp_add_inline_script('jquery', $inline_script);
?>
<div class="tbf-photofall-wrap">
  <div class="tbf-photofall-topbar">
    <h1 class="tbf-photofall-title">Photofall</h1>
    <input type="search" class="tbf-photofall-search" placeholder="Search photos..." data-photofall-search />
  </div>

  <div class="tbf-photofall-grid" data-photofall-grid></div>

  <div class="tbf-photofall-modal" data-photofall-modal style="display:none;">
    <div class="tbf-photofall-modal-inner">
      <button type="button" class="tbf-photofall-close" data-photofall-modal-close aria-label="Close">×</button>

      <div class="tbf-photofall-modal-media">
        <img data-photofall-modal-img alt="" style="display:none;max-width:100%;height:auto;" />
        <video data-photofall-modal-video style="display:none;max-width:100%;" controls></video>
      </div>

      <div class="tbf-photofall-modal-meta">
        <div class="tbf-photofall-modal-title" data-photofall-modal-title></div>
      </div>
    </div>
  </div>
</div>

<?php
get_footer();