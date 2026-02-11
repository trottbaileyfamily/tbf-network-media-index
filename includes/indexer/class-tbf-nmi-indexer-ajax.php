<?php
/**
 * File: includes/indexer/class-tbf-nmi-indexer-ajax.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Indexer_AJAX {

  public static function init() {
    add_action('wp_ajax_tbf_nmi_indexer_status', [__CLASS__, 'status']);
    add_action('wp_ajax_tbf_nmi_indexer_run', [__CLASS__, 'run']);
    add_action('wp_ajax_tbf_nmi_indexer_stop', [__CLASS__, 'stop']);
    add_action('wp_ajax_tbf_nmi_indexer_reset', [__CLASS__, 'reset']);
  }

  private static function verify() {
    $cap = is_multisite() ? 'manage_network_options' : 'manage_options';
    if ( ! current_user_can($cap) ) wp_send_json_error(['message'=>'Permission denied'], 403);
    check_ajax_referer('tbf_nmi_indexer_nonce', 'nonce');
  }

  public static function status() {
    self::verify();
    wp_send_json_success(TBF_NMI_Indexer_Admin::get_state());
  }

  public static function stop() {
    self::verify();
    $st = TBF_NMI_Indexer_Admin::get_state();
    $st['running'] = 0;
    TBF_NMI_Indexer_Admin::save_state($st);
    wp_send_json_success(['message'=>'Stopped','state'=>$st]);
  }

  public static function reset() {
    self::verify();
    TBF_NMI_Indexer_Admin::reset_state();
    wp_send_json_success(['message'=>'Progress reset','state'=>TBF_NMI_Indexer_Admin::get_state()]);
  }

  public static function run() {
    self::verify();

    $limit = max(50, min(2000, (int)($_POST['limit'] ?? 500)));
    $images = ! empty($_POST['images']);
    $videos = ! empty($_POST['videos']);
    $siteOnly = isset($_POST['site_only']) ? (int)$_POST['site_only'] : 0;

    $st = TBF_NMI_Indexer_Admin::get_state();
    $st['running'] = 1;

    if ( $siteOnly > 0 ) {
      $st['site_mode'] = 1;
      $st['site_only'] = $siteOnly;
      if ( empty($st['current_blog_id']) ) $st['current_blog_id'] = $siteOnly;
    }

    $blogId = (int)($st['current_blog_id'] ?? 0);
    if ($blogId <= 0) {
      $blogId = self::first_site_id($st);
      $st['current_blog_id'] = $blogId;
      $st['cursor'] = 0;
    }
    if ($blogId <= 0) {
      $st['running'] = 0;
      TBF_NMI_Indexer_Admin::save_state($st);
      wp_send_json_success(['message'=>'No sites found','state'=>$st,'done'=>true]);
    }

    $indexer = new TBF_NMI_Indexer();
    if ( ! $indexer->has_table() ) $indexer->create_table();

    $cursor = (int)($st['cursor'] ?? 0);

    $res = $indexer->index_site_batch($blogId, [
      'limit' => $limit,
      'start_after' => $cursor,
      'images' => $images,
      'videos' => $videos,
    ]);

    if ( ! empty($res['error']) ) {
      $st['running'] = 0;
      TBF_NMI_Indexer_Admin::save_state($st);
      wp_send_json_error(['message'=>$res['error'], 'state'=>$st], 500);
    }

    $st['cursor'] = (int)($res['last_id'] ?? $cursor);
    $st['total_indexed'] = (int)($st['total_indexed'] ?? 0) + (int)($res['indexed'] ?? 0);

    $doneSite = ! empty($res['done']);
    $log = ['blog_id'=>$blogId,'scanned'=>(int)($res['scanned']??0),'indexed'=>(int)($res['indexed']??0),'cursor'=>(int)$st['cursor'],'done_site'=>$doneSite];

    if ( $doneSite ) {
      $next = self::next_site_id($st, $blogId);
      if ( $next > 0 ) {
        $st['current_blog_id'] = $next;
        $st['cursor'] = 0;
      } else {
        $st['running'] = 0;
      }
    }

    TBF_NMI_Indexer_Admin::save_state($st);

    wp_send_json_success(['state'=>$st,'log'=>$log,'done'=>empty($st['running'])]);
  }

  private static function first_site_id(array $st) {
    if ( ! is_multisite() ) return get_current_blog_id();
    if ( ! empty($st['site_mode']) && ! empty($st['site_only']) ) return (int)$st['site_only'];
    $ids = get_sites(['number'=>1,'orderby'=>'id','order'=>'ASC','fields'=>'ids']);
    return !empty($ids[0]) ? (int)$ids[0] : 0;
  }

  private static function next_site_id(array $st, $currentBlogId) {
    if ( ! is_multisite() ) return 0;
    if ( ! empty($st['site_mode']) && ! empty($st['site_only']) ) return 0;
    $all = get_sites(['number'=>5000,'orderby'=>'id','order'=>'ASC','fields'=>'ids']);
    $all = array_map('intval', (array)$all);
    $pos = array_search((int)$currentBlogId, $all, true);
    if ($pos === false) return 0;
    return isset($all[$pos+1]) ? (int)$all[$pos+1] : 0;
  }
}
