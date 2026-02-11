<?php
/*
 * File: includes/class-tbf-nmi-visibility.php
 * Version: 2.0.0
 */

if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Visibility {

  public static function init() {
    // Media modal "Media Library" tab uses this query filter
    add_filter('ajax_query_attachments_args', [__CLASS__, 'exclude_proxies_ajax_query'], 20, 1);

    // Upload screen (Media > Library)
    add_action('pre_get_posts', [__CLASS__, 'exclude_proxies_upload_screen'], 20, 1);

    // Gutenberg / REST media collections (not single media by ID)
    add_filter('rest_media_query', [__CLASS__, 'exclude_proxies_rest_query'], 20, 2);
  }

  private static function proxy_exclusion_meta_query($existing_meta_query = []) {
    $mq = is_array($existing_meta_query) ? $existing_meta_query : [];

    $mq[] = [
      'relation' => 'OR',
      [
        'key'     => TBF_NMI_Proxy::META_IS_PROXY,
        'compare' => 'NOT EXISTS',
      ],
      [
        'key'     => TBF_NMI_Proxy::META_IS_PROXY,
        'value'   => '1',
        'compare' => '!=',
      ],
    ];

    return $mq;
  }

  /**
   * Exclude proxies from the Media Library tab inside the media modal (core AJAX).
   */
  public static function exclude_proxies_ajax_query($query) {
    if ( ! is_admin() ) return $query;

    // If querying specific IDs, do not filter (proxies must remain retrievable by ID)
    if ( ! empty($query['p']) || ! empty($query['post__in']) || ! empty($query['include']) ) {
      return $query;
    }

    $query['meta_query'] = self::proxy_exclusion_meta_query($query['meta_query'] ?? []);
    return $query;
  }

  /**
   * Exclude proxies from the wp-admin Upload screen (Media > Library).
   */
  public static function exclude_proxies_upload_screen($q) {
    if ( ! is_admin() || ! $q instanceof WP_Query ) return;

    global $pagenow;
    if ( $pagenow !== 'upload.php' ) return;

    if ( ! $q->is_main_query() ) return;

    $post_type = $q->get('post_type');
    if ( $post_type !== 'attachment' ) return;

    // If querying specific IDs, do not filter
    if ( $q->get('p') || $q->get('post__in') || $q->get('include') ) return;

    $q->set('meta_query', self::proxy_exclusion_meta_query($q->get('meta_query')));
  }

  /**
   * Exclude proxies from REST collections (used by Gutenberg media library),
   * but do not interfere with single /media/{id} requests.
   */
  public static function exclude_proxies_rest_query($args, $request) {

    // If requesting explicit includes, do not filter
    $include = $request->get_param('include');
    if ( ! empty($include) ) return $args;

    // If requesting a single ID via REST route, this filter usually won't apply,
    // but we still avoid touching if 'id' param is present.
    $id = $request->get_param('id');
    if ( ! empty($id) ) return $args;

    $args['meta_query'] = self::proxy_exclusion_meta_query($args['meta_query'] ?? []);
    return $args;
  }
}
