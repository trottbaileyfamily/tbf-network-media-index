<?php
/**
 * File: includes/photofall/class-tbfbkm-photofall-router.php
 * Version: 6.9.20 (Strict Master Redirects & Attachment Interceptor)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Photofall_Router {

  public static function init() {
    add_action('init', [__CLASS__, 'add_rewrite_rule']);
    add_filter('query_vars', [__CLASS__, 'add_query_vars']);
    add_action('template_redirect', [__CLASS__, 'template_redirect'], 1); // High priority intercept
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

    // ========================================================================
    // 1. GLOBAL INTERCEPTOR: Kill default WP Attachment Pages
    // ========================================================================
    // If someone visits /?attachment_id=123 or /media-slug/, redirect to Master Photofall
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

    // ========================================================================
    // 2. SUBSITE INTERCEPTOR: Kill Subsite /photo/ pages
    // ========================================================================
    // If we are on a custom /photo/ route, but NOT on the Master site, redirect to Master.
    if ( $current_id !== $master_id ) {
        // Grab the exact URI they requested (e.g. /photo/image/2-4054/) and apply it to the master domain
        $requested_path = $_SERVER['REQUEST_URI'];
        $redirect_url = rtrim(get_site_url($master_id), '/') . $requested_path;
        
        wp_redirect($redirect_url, 301);
        exit;
    }

    // ========================================================================
    // 3. MASTER SITE RENDER LOGIC
    // ========================================================================
    if ( ! class_exists('TBFBKM_Photofall_Templates') ) {
        require_once plugin_dir_path(__FILE__) . 'class-tbfbkm-photofall-templates.php';
    }

    $settings = class_exists('TBFBKM_Subsite_Settings') ? TBFBKM_Subsite_Settings::get_options() : get_option('tbfbkm_photofall_options', []);

    if ( $is_single ) {
        global $wpdb;
        $table = $wpdb->base_prefix . 'tbfbkm_index';
        $att_id = (int)get_query_var('tbf_id');
        $blog_id = (int)get_query_var('tbf_blog');
        
        $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE blog_id = %d AND attachment_id = %d", $blog_id, $att_id));
        
        if ( ! $post ) {
            global $wp_query; $wp_query->set_404(); status_header(404); nocache_headers(); return;
        }
        
        $post->ID = $post->attachment_id;
        $post->post_title = $post->title;
        $post->post_excerpt = $post->caption;
        $post->tbf_url_full = $post->url_full;
        $post->tbf_url_thumb = $post->url_thumb ?: $post->poster_url;
        $post->type = $post->media_type;

        TBFBKM_Photofall_Templates::render_single($post, [], $settings);
        exit;
    }

    if ( $is_archive ) {
        global $wpdb;
        $table = $wpdb->base_prefix . 'tbfbkm_index';
        
        $paged  = max(1, (int) get_query_var('paged', 1));
        if ( isset($_GET['tbf_page']) ) $paged = max(1, (int)$_GET['tbf_page']);
        
        $per_page = (int)($settings['per_page'] ?? 20);
        $offset = ($paged - 1) * $per_page;

        $search = sanitize_text_field($_GET['tbf_search'] ?? '');
        $filter_type = sanitize_text_field($_GET['tbf_filter'] ?? 'all');
        $filter_source = sanitize_text_field($_GET['tbf_source'] ?? 'all');
        $filter_year = (int)($_GET['tbf_year'] ?? 0);
        $filter_site = (int)($_GET['tbf_site'] ?? 0);
        $sort = sanitize_text_field($_GET['tbf_sort'] ?? ($settings['default_sort'] ?? 'date_desc'));

        $where = "1=1";
        $params = [];

        // Vikinger filter 
        $where .= " AND (url_full NOT LIKE %s)";
        $params[] = '%/vikinger/%';

        if ( !empty($search) ) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (title LIKE %s OR alt LIKE %s OR caption LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        if ( $filter_type !== 'all' ) {
            if ( $filter_type === 'image' ) { $where .= " AND media_type = 'image'"; }
            elseif ( $filter_type === 'video' ) { $where .= " AND media_type = 'video'"; }
            elseif ( $filter_type === 'audio' ) { $where .= " AND media_type = 'audio'"; }
        }

        if ( $filter_year > 0 ) {
            $where .= " AND year = %d";
            $params[] = $filter_year;
        }

        if ( $filter_site > 0 ) {
            $where .= " AND blog_id = %d";
            $params[] = $filter_site;
        } else {
            if ( is_multisite() && ($settings['network_scope_mode'] ?? 'all') === 'specific' ) {
                $allowed = $settings['network_scope_sites'] ?? [];
                if ( !empty($allowed) ) {
                    $in = implode(',', array_map('intval', $allowed));
                    $where .= " AND blog_id IN ($in)";
                }
            }
        }

        $order_sql = "ORDER BY created_gmt DESC";
        if ( $sort === 'date_asc' ) $order_sql = "ORDER BY created_gmt ASC";
        if ( $sort === 'random' ) $order_sql = "ORDER BY RAND()";

        // Distinct URL Grouping to prevent duplicates
        $sql = "SELECT MAX(blog_id) as blog_id, MAX(attachment_id) as attachment_id, MAX(title) as title, 
                       MAX(caption) as caption, MAX(media_type) as media_type, url_full, 
                       MAX(url_thumb) as url_thumb, MAX(poster_url) as poster_url 
                FROM {$table} WHERE {$where} GROUP BY url_full {$order_sql} LIMIT %d OFFSET %d";
                
        $final_params = array_merge($params, [$per_page, $offset]);
        
        $posts = $wpdb->get_results($wpdb->prepare($sql, $final_params));
        
        $count_sql = "SELECT COUNT(DISTINCT url_full) FROM {$table} WHERE {$where}";
        $total = $wpdb->get_var($wpdb->prepare($count_sql, $params));
        $max_pages = ceil($total / $per_page);

        $years = $wpdb->get_col("SELECT DISTINCT year FROM {$table} ORDER BY year DESC");
        $sites = [];
        if ( is_multisite() ) {
            $raw_sites = $wpdb->get_results("SELECT DISTINCT blog_id FROM {$table}");
            foreach($raw_sites as $s) {
                $sites[$s->blog_id] = get_blog_option($s->blog_id, 'blogname') ?: 'Site '.$s->blog_id;
            }
        }

        TBFBKM_Photofall_Templates::render_page(
            [
                'posts' => $posts,
                'max_pages' => $max_pages,
                'current_page' => $paged
            ],
            array_merge($settings, ['allowed_types' => ['image','video','audio']]), 
            [
                'search' => $search,
                'filter' => $filter_type,
                'source' => $filter_source,
                'year'   => $filter_year,
                'site_filter' => $filter_site,
                'sort'   => $sort
            ],
            [
                'years' => $years,
                'sites' => $sites
            ]
        );
        exit;
    }
  }
}
