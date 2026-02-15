<?php
/*
 * File: includes/class-tbf-nmi-ajax.php
 * Version: 1.0.22
 */

if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_AJAX {

  public static function init() {
    add_action('wp_ajax_tbf_nmi_sites', [__CLASS__, 'sites']);
    add_action('wp_ajax_tbf_nmi_list',  [__CLASS__, 'list_media']);
    add_action('wp_ajax_tbf_nmi_proxy', [__CLASS__, 'create_proxy']);
  }

  private static function check() {
    if ( ! class_exists('TBF_Network_Media_Index') || ! TBF_Network_Media_Index::can_browse() ) {
      wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    check_ajax_referer('tbf_nmi_nonce', 'nonce');
  }

  public static function sites() {
    self::check();

    if ( ! is_multisite() ) {
      wp_send_json_success(['sites' => []]);
    }

    $s = TBF_Network_Media_Index::get_settings();
    $sites = get_sites(['number' => (int)$s['max_sites']]);
    $out = [];

    foreach ($sites as $site) {
      $bid = (int)$site->blog_id;
      $d = get_blog_details($bid);
      if ( ! $d ) continue;
      $out[] = [
        'blog_id' => $bid,
        'name'    => $d->blogname,
        'url'     => $d->siteurl,
      ];
    }

    wp_send_json_success(['sites' => $out]);
  }

  /**
   * Virtual index search across sites.
   * Returns items with { blog_id, attachment_id, url, thumb, title, mime }.
   */
  public static function list_media() {
    self::check();

    if ( ! is_multisite() ) {
      wp_send_json_success([
        'page' => 1, 'per_page' => 0, 'total' => 0, 'max_pages' => 0, 'items' => []
      ]);
    }

    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(10, min(200, (int)($_GET['per_page'] ?? 60)));
    $search   = sanitize_text_field($_GET['s'] ?? '');
    $mime     = sanitize_text_field($_GET['mime'] ?? '');
    $origin   = (int)($_GET['origin_blog_id'] ?? 0);

    $sites = get_sites(['number' => (int)TBF_Network_Media_Index::get_settings()['max_sites']]);

    // If filtering by a specific site, reduce scan to one blog
    if ( $origin > 0 ) {
      $sites = array_values(array_filter($sites, function($site) use ($origin){
        return (int)$site->blog_id === $origin;
      }));
    }

    // Lightweight "global pagination"
    $items = [];
    $total_estimate = 0;

    foreach ($sites as $site) {
      $bid = (int)$site->blog_id;

      switch_to_blog($bid);

      $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => $per_page,     // cap per site per request
        'orderby'        => 'date',
        'order'          => 'DESC',
        's'              => $search,
        'fields'         => 'ids',
        'no_found_rows'  => false,         // needed to estimate totals per site
      ];

      if ($mime) {
        $args['post_mime_type'] = $mime;
      }

      $q = new WP_Query($args);

      $total_estimate += (int)$q->found_posts;

      foreach ($q->posts as $att_id) {
        $att_id = (int)$att_id;

        $url = wp_get_attachment_url($att_id);
        if ( ! $url ) continue;

        $thumb = wp_get_attachment_image_src($att_id, 'thumbnail');
        $items[] = [
          'blog_id'       => $bid,
          'attachment_id' => $att_id,
          'title'         => get_the_title($att_id),
          'mime'          => get_post_mime_type($att_id),
          'date'          => get_the_date('c', $att_id),
          'url'           => $url,
          'thumb'         => is_array($thumb) ? $thumb[0] : '',
        ];

        // keep bounded so one request never explodes
        if (count($items) >= ($per_page * 6)) break; // cap aggregation
      }

      wp_reset_postdata();
      restore_current_blog();

      if (count($items) >= ($per_page * 6)) break;
    }

    // Sort aggregated list by date desc across sites
    usort($items, function($a, $b){
      return strcmp($b['date'], $a['date']);
    });

    // Now paginate the bounded set
    $offset = ($page - 1) * $per_page;
    $paged_items = array_slice($items, $offset, $per_page);

    // total / max_pages is an estimate (good enough for browsing)
    $total = $total_estimate;
    $max_pages = max(1, (int)ceil($total / $per_page));

    wp_send_json_success([
      'page'      => $page,
      'per_page'  => $per_page,
      'total'     => $total,
      'max_pages' => $max_pages,
      'items'     => $paged_items,
    ]);
  }

  public static function create_proxy() {
    self::check();

    $origin_blog_id = (int)($_POST['origin_blog_id'] ?? 0);
    $origin_att_id  = (int)($_POST['origin_attachment_id'] ?? 0);

    if ( ! $origin_blog_id || ! $origin_att_id ) {
      wp_send_json_error(['message' => 'Missing IDs'], 400);
    }

    $local_id = TBF_NMI_Proxy::ensure_proxy($origin_blog_id, $origin_att_id);
    if ( is_wp_error($local_id) ) {
      wp_send_json_error(['message' => $local_id->get_error_message()], 500);
    }

    wp_send_json_success(['local_attachment_id' => (int)$local_id]);
  }
}