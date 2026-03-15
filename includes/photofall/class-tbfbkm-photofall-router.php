<?php
/**
 * File: includes/photofall/class-tbfbkm-photofall-router.php
 * Version: 7.0.1.7 (Security Hardening - Direct DB Fix)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Photofall_Router {

  public static function init() {
    add_action('init', [__CLASS__, 'add_rewrite_rule']);
    add_filter('query_vars', [__CLASS__, 'add_query_vars']);
    add_action('template_redirect', [__CLASS__, 'template_redirect'], 1);
  }

  public static function add_rewrite_rule() {
    add_rewrite_rule('^photo/?$', 'index.php?tbfbkm_photofall=1', 'top');
    add_rewrite_rule('^photo/(image|video|audio)/([0-9]+)-([0-9]+)/?$', 'index.php?tbfbkm_single=1&tbf_type=$matches[1]&tbf_blog=$matches[2]&tbf_id=$matches[3]', 'top');
  }

  public static function add_query_vars($vars) {
    $vars[] = 'tbfbkm_photofall';
    $vars[] = 'tbfbkm_single';
    $vars[] = 'tbf_type';
    $vars[] = 'tbf_id';
    $vars[] = 'tbf_blog';
    return $vars;
  }

  public static function template_redirect() {
    $master_id = (int) get_site_option('tbfbkm_master_controller_id', get_main_site_id());
    $current_id = get_current_blog_id();
    $master_base_url = get_site_url($master_id, '/photo/');

    if ( is_attachment() ) {
        $att_id = get_queried_object_id();
        $mime = get_post_mime_type($att_id);
        
        $type = 'image';
        if (strpos($mime, 'video') !== false) $type = 'video';
        if (strpos($mime, 'audio') !== false) $type = 'audio';

        $redirect_url = $master_base_url . $type . '/' . $current_id . '-' . $att_id . '/';
        wp_redirect($redirect_url, 301);
        exit;
    }

    $is_archive = get_query_var('tbfbkm_photofall');
    $is_single  = get_query_var('tbfbkm_single');

    if ( ! $is_archive && ! $is_single ) return;

    if ( $current_id !== $master_id ) {
        $requested_path = $_SERVER['REQUEST_URI'] ?? '';
        $redirect_url = rtrim(get_site_url($master_id), '/') . $requested_path;
        wp_redirect($redirect_url, 301);
        exit;
    }

    if ( ! class_exists('TBFBKM_Photofall_Templates') ) {
        require_once plugin_dir_path(__FILE__) . 'class-tbfbkm-photofall-templates.php';
    }

    $settings = class_exists('TBFBKM_Subsite_Settings') ? TBFBKM_Subsite_Settings::get_options() : get_option('tbfbkm_photofall_options', []);
    global $wpdb;

    if ( $is_single ) {
        $att_id = (int)get_query_var('tbf_id');
        $blog_id = (int)get_query_var('tbf_blog');
        
        // FIX: Hardcoded table prefix inside the string literal
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT attachment_id as ID, blog_id, title as post_title, caption as post_excerpt, url_full as tbf_url_full, url_thumb as tbf_url_thumb, media_type as type 
             FROM {$wpdb->base_prefix}tbfbkm_index 
             WHERE attachment_id = %d AND blog_id = %d LIMIT 1", 
             $att_id, $blog_id
        ));

        if (!$item) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            exit;
        }

        $related = $wpdb->get_results($wpdb->prepare(
            "SELECT attachment_id as ID, blog_id, title as post_title, caption as post_excerpt, url_full as tbf_url_full, url_thumb as tbf_url_thumb, media_type as type 
             FROM {$wpdb->base_prefix}tbfbkm_index 
             WHERE media_type = %s AND attachment_id != %d 
             ORDER BY RAND() LIMIT 8",
             $item->type, $att_id
        ));

        TBFBKM_Photofall_Templates::render_single($item, $related, $settings);
        exit;
    }

    if ( $is_archive ) {
        $page = isset($_GET['tbf_page']) ? max(1, (int)$_GET['tbf_page']) : (get_query_var('paged') ? max(1, (int)get_query_var('paged')) : 1);
        $per_page = isset($settings['per_page']) ? max(1, (int)$settings['per_page']) : 20;

        $filter = isset($_GET['tbf_filter']) ? sanitize_key($_GET['tbf_filter']) : 'all';
        $sort = isset($_GET['tbf_sort']) ? sanitize_key($_GET['tbf_sort']) : ($settings['default_sort'] ?? 'date_desc');
        $search = isset($_GET['tbf_search']) ? sanitize_text_field(wp_unslash($_GET['tbf_search'])) : '';
        $year = isset($_GET['tbf_year']) ? (int)$_GET['tbf_year'] : 0;
        $site_filter = isset($_GET['tbf_site']) ? (int)$_GET['tbf_site'] : 0;

        $where = "1=1";
        $params = [];
        
        $where .= " AND (url_full NOT LIKE %s)";
        $params[] = '%/vikinger/%';

        if ( in_array($filter, ['image', 'video', 'audio'], true) ) {
            $where .= " AND media_type = %s";
            $params[] = $filter;
        }

        if ( $year > 0 ) {
            $where .= " AND year = %d";
            $params[] = $year;
        }

        if ( $site_filter > 0 ) {
            $where .= " AND blog_id = %d";
            $params[] = $site_filter;
        }

        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $order_sql = "ORDER BY created_gmt DESC";
        if ($sort === 'date_asc') $order_sql = "ORDER BY created_gmt ASC";
        if ($sort === 'random') $order_sql = "ORDER BY RAND()";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = "SELECT COUNT(DISTINCT url_full) FROM {$wpdb->base_prefix}tbfbkm_index WHERE {$where}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int)$wpdb->get_var($wpdb->prepare($count_sql, $params));
        
        $max_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT MIN(blog_id) as blog_id, MAX(attachment_id) as attachment_id, MAX(title) as post_title, MAX(caption) as post_excerpt, MAX(media_type) as type, url_full as tbf_url_full, MAX(url_thumb) as tbf_url_thumb 
                FROM {$wpdb->base_prefix}tbfbkm_index 
                WHERE {$where} GROUP BY url_full {$order_sql} LIMIT %d OFFSET %d";
                
        $final_params = array_merge($params, [$per_page, $offset]);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $posts = $wpdb->get_results($wpdb->prepare($sql, $final_params));

        $years = $wpdb->get_col("SELECT DISTINCT year FROM {$wpdb->base_prefix}tbfbkm_index WHERE year > 0 ORDER BY year DESC");
        $sites = [];
        if (is_multisite()) {
            $site_ids = $wpdb->get_col("SELECT DISTINCT blog_id FROM {$wpdb->base_prefix}tbfbkm_index");
            foreach($site_ids as $sid) {
                $sites[$sid] = get_blog_option($sid, 'blogname') ?: 'Site ' . $sid;
            }
        }

        $data = [
            'posts' => $posts,
            'max_pages' => $max_pages,
            'current_page' => $page
        ];

        $args = [
            'filter' => $filter,
            'sort' => $sort,
            'search' => $search,
            'year' => $year,
            'site_filter' => $site_filter,
            'source' => 'all'
        ];

        $filter_options = [
            'years' => $years,
            'sites' => $sites
        ];

        TBFBKM_Photofall_Templates::render_page($data, $settings, $args, $filter_options);
        exit;
    }
  }
}