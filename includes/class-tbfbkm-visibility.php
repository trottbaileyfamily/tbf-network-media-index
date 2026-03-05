<?php
/**
 * File: includes/class-tbfbkm-visibility.php
 * Version: 4.3.6 (Smart Exclusion)
 */
if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Visibility {

  public static function init() {
    // Filter AJAX Grid (Media Library)
    add_filter('ajax_query_attachments_args', [__CLASS__, 'filter_ajax_query'], 20);
    
    // Filter List View (upload.php)
    add_action('pre_get_posts', [__CLASS__, 'filter_admin_list']);
  }

  /**
   * The Allowed Statuses for the Grid.
   * We EXCLUDE 'tbfbkm-hidden' here to make the library fast.
   */
  private static function get_grid_statuses() {
      return ['inherit', 'private', 'publish']; 
  }

  public static function filter_ajax_query($query) {
    if ( ! is_admin() ) return $query;

    // 1. CRITICAL: If the Modal is verifying a selection (asking for IDs), DO NOT FILTER.
    if ( ! empty($query['post__in']) || ! empty($query['p']) || ! empty($query['include']) ) {
        // Explicitly Allow Hidden if checking specific IDs
        if ( ! isset($query['post_status']) ) $query['post_status'] = ['inherit', 'tbfbkm-hidden'];
        return $query;
    }

    // 2. Otherwise (General Grid browsing), show only standard statuses.
    if ( ! isset($query['post_status']) || $query['post_status'] === 'any' ) {
        $query['post_status'] = self::get_grid_statuses();
    }

    return $query;
  }

  public static function filter_admin_list($q) {
    if ( ! is_admin() || ! $q->is_main_query() ) return;
    global $pagenow;
    
    if ( $pagenow === 'upload.php' && $q->get('post_type') === 'attachment' ) {
        if ( ! $q->get('post__in') && ! $q->get('p') ) {
             $status = $q->get('post_status');
             if ( ! $status || $status === 'any' ) {
                 $q->set('post_status', self::get_grid_statuses());
             }
        }
    }
  }
}

