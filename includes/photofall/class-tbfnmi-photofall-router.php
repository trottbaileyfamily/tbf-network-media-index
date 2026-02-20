<?php
/**
 * File: includes/photofall/class-tbfnmi-photofall-router.php
 * Version: 6.0.6 (Unique Site Permalinks)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Photofall_Router {

  const REWRITE_TAG = 'photofall_page';

  public static function init() {
    add_action('init', [__CLASS__, 'add_rewrite_rules']);
    add_action('query_vars', [__CLASS__, 'add_query_vars']);
    add_action('template_redirect', [__CLASS__, 'template_redirect']);
    add_filter('document_title_parts', [__CLASS__, 'filter_title'], 10, 1);
    
    add_action('wp_ajax_tbfnmi_load_more', [__CLASS__, 'ajax_load_more']);
    add_action('wp_ajax_nopriv_tbfnmi_load_more', [__CLASS__, 'ajax_load_more']);
  }

  public static function ajax_load_more() {
      check_ajax_referer('tbfnmi_frontend', 'nonce');
      require_once TBFNMI_DIR . 'includes/photofall/class-tbfnmi-photofall-query.php';
      require_once TBFNMI_DIR . 'includes/photofall/class-tbfnmi-photofall-templates.php';

      $settings = TBFNMI_Subsite_Settings::get_options();
      
      $args = [
          'allowed_types' => $settings['allowed_types'],
          'sort'          => isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : $settings['default_sort'],
          'filter'        => isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all',
          'search'        => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
          'year'          => isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '',
          'site_filter'   => isset($_POST['site_filter']) ? sanitize_text_field($_POST['site_filter']) : '',
          'page'          => isset($_POST['page']) ? (int)$_POST['page'] : 1,
          'per_page'      => 50
      ];

      $data = TBFNMI_Photofall_Query::get_media($args);
      $html = '';

      if ( ! empty($data['posts']) ) {
          foreach ( $data['posts'] as $item ) {
              $html .= TBFNMI_Photofall_Templates::get_item_html($item);
          }
      }

      wp_send_json_success([
          'html'      => $html,
          'max_pages' => $data['max_pages'],
          'next_page' => $args['page'] + 1
      ]);
  }

  public static function template_redirect() {
    if ( get_query_var(self::REWRITE_TAG) ) {
        
        require_once TBFNMI_DIR . 'includes/photofall/class-tbfnmi-photofall-query.php';
        require_once TBFNMI_DIR . 'includes/photofall/class-tbfnmi-photofall-templates.php';

        $settings = TBFNMI_Subsite_Settings::get_options();
        $single_id = get_query_var('pf_id');
        $blog_id   = get_query_var('pf_blog_id'); // Extract Blog ID

        if ( $single_id ) {
            // Pass BOTH ID and Blog ID
            $item = TBFNMI_Photofall_Query::get_single((int)$single_id, (int)$blog_id);
            if ( ! $item ) {
                global $wp_query; $wp_query->set_404(); status_header(404); get_template_part(404); exit;
            }
            
            $rel_res = TBFNMI_Photofall_Query::get_media([
                'allowed_types' => [$item->type],
                'exclude'       => [$item->ID],
                'per_page'      => 12,
                'sort'          => $settings['default_sort'] 
            ]);
            
            TBFNMI_Photofall_Templates::render_single($item, $rel_res['posts'], $settings);
            exit;
        }

        $args = [
            'allowed_types' => $settings['allowed_types'],
            'sort'          => isset($_GET['tbf_sort']) ? sanitize_text_field($_GET['tbf_sort']) : $settings['default_sort'],
            'filter'        => isset($_GET['tbf_filter']) ? sanitize_text_field($_GET['tbf_filter']) : 'all',
            'search'        => isset($_GET['tbf_search']) ? sanitize_text_field($_GET['tbf_search']) : '',
            'year'          => isset($_GET['tbf_year']) ? sanitize_text_field($_GET['tbf_year']) : '',
            'site_filter'   => isset($_GET['tbf_site']) ? sanitize_text_field($_GET['tbf_site']) : '',
            'page'          => 1
        ];

        $data = TBFNMI_Photofall_Query::get_media($args);
        $filter_options = TBFNMI_Photofall_Query::get_filter_data(); 
        
        TBFNMI_Photofall_Templates::render_page($data, $settings, $args, $filter_options);
        exit;
    }
  }

  public static function add_rewrite_rules() {
    // 1. New Structure: /photo/image/3-67899/ (Includes Blog ID)
    add_rewrite_rule('^photo/(image|video|audio)/(\d+)-(\d+)/?$', 'index.php?' . self::REWRITE_TAG . '=1&pf_type=$matches[1]&pf_blog_id=$matches[2]&pf_id=$matches[3]', 'top');
    
    // 2. Fallback for Old Links: /photo/image/67899/
    add_rewrite_rule('^photo/(image|video|audio)/(\d+)/?$', 'index.php?' . self::REWRITE_TAG . '=1&pf_type=$matches[1]&pf_id=$matches[2]', 'top');
    
    // 3. Main Archive: /photo/
    add_rewrite_rule('^photo/?$', 'index.php?' . self::REWRITE_TAG . '=1', 'top');
  }

  public static function add_query_vars($vars) {
    $vars[] = self::REWRITE_TAG; 
    $vars[] = 'pf_id'; 
    $vars[] = 'pf_type'; 
    $vars[] = 'pf_blog_id'; // Allow WP to extract this variable
    return $vars;
  }

  public static function filter_title($title) {
      if ( get_query_var(self::REWRITE_TAG) ) {
          $title['title'] = get_query_var('pf_id') ? 'Media File - ' . get_query_var('pf_id') : 'Photofall Gallery';
      }
      return $title;
  }
}
