<?php
/**
 * File: includes/photofall/class-tbfnmi-photofall-templates.php
 * Version: 6.7.5 (Fatal Error Fix & Full Logic Restoration)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Photofall_Templates {

  public static function resolve_url($post) {
      $id = (int)($post->ID ?? $post->attachment_id ?? 0);
      if ( !empty($post->tbf_url_full) ) return $post->tbf_url_full;
      $proxy_url = get_post_meta($id, '_tbfnmi_proxy_url', true);
      if ( $proxy_url ) return $proxy_url;
      $featured_url = get_post_meta($id, '_tbfnmi_featured_url', true);
      if ( $featured_url ) return $featured_url;
      $source_url = get_post_meta($id, '_tbfnmi_source_url', true);
      if ( $source_url ) return $source_url;
      $local_url = wp_get_attachment_url($id);
      if ( $local_url ) return $local_url;
      return ''; 
  }

  public static function render_page($data, $settings, $current_args, $filter_options) {
    self::enqueue_assets($data['max_pages'], $current_args);
    
    $opts = get_option('tbfnmi_photofall_options', []);
    $is_admin = current_user_can('manage_options') || is_super_admin();
    $tab = isset($_GET['tbf_tab']) ? sanitize_text_field($_GET['tbf_tab']) : 'active';
    
    $can_upload = false;
    if ( $is_admin ) {
        $can_upload = true;
    } elseif ( !empty($opts['enable_frontend_upload']) && is_user_logged_in() ) {
        $user = wp_get_current_user();
        $allowed_roles = !empty($opts['upload_roles']) ? $opts['upload_roles'] : ['administrator'];
        if ( !empty(array_intersect($allowed_roles, $user->roles)) ) {
            $can_upload = true;
        }
    }

    $hidden_items = get_option('tbfnmi_hidden_media', []);
    $media = $data['posts'];

    if ( $tab === 'hidden' ) {
        global $wpdb;
        $table = $wpdb->base_prefix . 'tbfnmi_index';
        if ( empty($hidden_items) ) {
            $media = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($hidden_items), '%d'));
            $query = $wpdb->prepare("SELECT attachment_id as ID, blog_id, url_thumb as tbf_url_thumb, url_full as tbf_url_full, width as tbf_width, height as tbf_height, media_type as type, title as post_title, caption as post_excerpt FROM {$table} WHERE attachment_id IN ($placeholders) ORDER BY created_gmt DESC", $hidden_items);
            $media = $wpdb->get_results($query);
        }
        $data['max_pages'] = 1; 
    }

    get_header(); 
    ?>
    <div class="tbf-photofall-wrapper">
      
      <?php if ( $is_admin ): ?>
      <div class="tbf-admin-tabs" style="margin-bottom: 20px; font-weight: bold; font-size: 16px; display: flex; align-items: center;">
          <a href="?tbf_tab=active" style="margin-right: 20px; text-decoration: none; padding-bottom: 5px; border-bottom: <?php echo $tab === 'active' ? '3px solid #2271b1' : 'none'; ?>; color: <?php echo $tab === 'active' ? '#2271b1' : '#555'; ?>;">Live Media</a>
          <a href="?tbf_tab=hidden" style="margin-right: auto; text-decoration: none; padding-bottom: 5px; border-bottom: <?php echo $tab === 'hidden' ? '3px solid #d63638' : 'none'; ?>; color: <?php echo $tab === 'hidden' ? '#d63638' : '#555'; ?>;">Hidden Media</a>
          
          <?php if ( !empty($opts['enable_xml_sitemaps']) ): ?>
              <button type="button" onclick="tbfnmiPingSEO()" class="tbf-btn" style="background:#fff; font-size:12px; border:1px solid #2271b1; color:#2271b1;">Notify Search Engines</button>
          <?php endif; ?>
      </div>
      <?php endif; ?>

      <form method="get" action="<?php echo esc_url(home_url('/photo/')); ?>" id="tbf-filter-form" class="tbf-photofall-toolbar">
        <?php if($is_admin): ?><input type="hidden" name="tbf_tab" value="<?php echo esc_attr($tab); ?>"><?php endif; ?>
        
        <div class="tbf-toolbar-main">
            <?php if (!empty($settings['show_search'])): ?>
                <div class="tbf-search-box">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" name="tbf_search" placeholder="Search media..." value="<?php echo esc_attr($current_args['search']); ?>">
                    <?php if(!empty($current_args['search'])): ?>
                        <a href="<?php echo esc_url(home_url('/photo/')); ?>" class="tbf-clear-search">&times;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_filter_type'])): ?>
                <div class="tbf-filter-group">
                    <button type="submit" name="tbf_filter" value="all" class="tbf-btn <?php echo esc_attr($current_args['filter'] === 'all' ? 'active' : ''); ?>">All</button>
                    <?php foreach($settings['allowed_types'] as $t): ?>
                        <button type="submit" name="tbf_filter" value="<?php echo esc_attr($t); ?>" class="tbf-btn <?php echo esc_attr($current_args['filter'] === $t ? 'active' : ''); ?>"><?php echo esc_html(ucfirst($t)); ?>s</button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <input type="hidden" name="tbf_filter" value="<?php echo esc_attr($current_args['filter']); ?>">
            <?php endif; ?>

            <?php if ( $can_upload && $tab === 'active' ): ?>
                <button type="button" id="tbfnmi-trigger-upload" class="tbf-btn" style="background: #2271b1; color: #fff; border: none; font-weight: bold; margin-left: auto;">Upload Media</button>
            <?php endif; ?>
        </div>

        <div class="tbf-toolbar-secondary">
            <?php if ( is_multisite() ): ?>
            <select name="tbf_source" class="tbf-auto-submit">
                <option value="all" <?php selected($current_args['source'], 'all'); ?>>All Uploads</option>
                <option value="frontend" <?php selected($current_args['source'], 'frontend'); ?>>Frontend</option>
                <option value="backend" <?php selected($current_args['source'], 'backend'); ?>>Backend</option>
            </select>
            <?php endif; ?>

            <?php if (!empty($settings['show_filter_year']) && !empty($filter_options['years'])): ?>
                <select name="tbf_year" class="tbf-auto-submit">
                    <option value="">Any Year</option>
                    <?php foreach ($filter_options['years'] as $yr): ?>
                        <option value="<?php echo esc_attr($yr); ?>" <?php selected($current_args['year'], $yr); ?>><?php echo esc_html($yr); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if ( is_multisite() && !empty($settings['show_filter_site']) && !empty($filter_options['sites']) ): ?>
                <select name="tbf_site" class="tbf-auto-submit">
                    <option value="">Any Site</option>
                    <?php foreach ($filter_options['sites'] as $id => $name): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($current_args['site_filter'], $id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if (!empty($settings['show_sort'])): ?>
                <select name="tbf_sort" class="tbf-auto-submit">
                    <option value="date_desc" <?php selected($current_args['sort'], 'date_desc'); ?>>Newest First</option>
                    <option value="date_asc" <?php selected($current_args['sort'], 'date_asc'); ?>>Oldest First</option>
                    <option value="random" <?php selected($current_args['sort'], 'random'); ?>>Random Order</option>
                </select>
            <?php endif; ?>

            <div class="tbf-icon-group" style="display:inline-flex; gap:10px;">
                <button type="button" id="tbf-toggle-captions" class="tbf-btn tbf-btn-icon" title="Toggle Captions"><span class="dashicons dashicons-text"></span></button>
                <?php if (!empty($settings['show_random'])): ?>
                    <button type="submit" name="tbf_sort" value="random" class="tbf-btn tbf-btn-icon" title="Shuffle"><span class="dashicons dashicons-randomize"></span></button>
                <?php endif; ?>
            </div>
        </div>
      </form>

      <div id="tbf-grid-container" class="tbf-photofall-grid caption-mode-<?php echo esc_attr($settings['caption_mode']); ?>">
        <?php if (empty($media)): ?>
            <div class="tbf-no-results">
                <h2>No media found</h2>
                <p>Try adjusting your search or filters. If you just installed the plugin, please run the "Full Network Index" in the Admin Dashboard.</p>
                <a href="<?php echo esc_url(home_url('/photo/')); ?>" class="tbf-btn">Clear All Filters</a>
            </div>
        <?php else: ?>
            <?php 
            foreach ($media as $item) {
                echo wp_kses(self::get_item_html($item), self::get_allowed_html()); 
            }
            ?>
        <?php endif; ?>
      </div>

      <?php if ( $data['max_pages'] > 1 && $tab === 'active' ): ?>
          <div class="tbf-load-more-wrap">
              <button id="tbf-load-more" class="tbf-btn">Load More</button>
              <span id="tbf-loader" style="display:none;">Loading...</span>
          </div>
      <?php endif; ?>
    </div>
    
    <?php self::render_lightbox_markup(); ?>
    <?php if ( $can_upload ) self::render_upload_modal(); ?>

    <?php get_footer();
  }

  public static function render_single($item, $related, $settings) {
      self::enqueue_assets(1, ['sort' => $settings['default_sort']]);
      get_header();
      
      $url_full = self::resolve_url($item); 
      $type = $item->type;

      global $wpdb;
      $usage_table = $wpdb->base_prefix . 'tbfnmi_usage_map';
      $has_usage_table = !empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($usage_table))));
      $featured_links = [];

      if ($has_usage_table && !empty($url_full)) {
          $url_http = set_url_scheme($url_full, 'http');
          $url_https = set_url_scheme($url_full, 'https');
          
          $featured_links = $wpdb->get_results($wpdb->prepare("
              SELECT post_title, permalink, site_name 
              FROM {$usage_table} 
              WHERE media_url = %s OR media_url = %s 
              ORDER BY id DESC LIMIT 10
          ", $url_http, $url_https));
      }

      ?>
      <div class="tbf-photofall-wrapper tbf-single-view">
          <div class="tbf-single-header">
              <a href="<?php echo esc_url(home_url('/photo/')); ?>" class="tbf-btn">&larr; Back to Gallery</a>
          </div>
          
          <div class="tbf-single-stage" style="text-align: center; background: #f9f9f9; padding: 40px 20px; border-radius: 12px; margin-bottom: 40px;">
              <?php if ($type === 'video'): ?>
                  <video src="<?php echo esc_url($url_full); ?>" controls class="tbf-single-media" style="max-width: 100%; max-height: 70vh; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);"></video>
              <?php elseif ($type === 'audio'): ?>
                  <div style="background:#fff; padding:40px; border-radius:12px; display:inline-block; max-width:100%; border:1px solid #ddd; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                      <img src="<?php echo esc_url(includes_url('images/media/audio.png')); ?>" style="width:100px; height:auto; margin-bottom:20px;" alt="Audio File">
                      <audio src="<?php echo esc_url($url_full); ?>" controls style="width:100%; min-width:300px;"></audio>
                  </div>
              <?php else: ?>
                  <img src="<?php echo esc_url($url_full); ?>" class="tbf-single-media" decoding="async" style="max-width: 100%; max-height: 70vh; width: auto; height: auto; object-fit: contain; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); cursor: zoom-in;" onclick="tbfnmi_photofall.openRaw('<?php echo esc_url($url_full); ?>', 'image', '<?php echo esc_attr($item->post_title); ?>')">
              <?php endif; ?>
              
              <div class="tbf-single-info" style="margin-top: 30px;">
                  <h1><?php echo esc_html($item->post_title); ?></h1>
                  <?php if($item->post_excerpt): ?><p><?php echo esc_html($item->post_excerpt); ?></p><?php endif; ?>
              </div>

              <?php if (!empty($featured_links)): ?>
              <div style="margin-top: 30px; padding: 20px; background: #fff; border-left: 4px solid #2271b1; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left; max-width: 800px; margin-left: auto; margin-right: auto;">
                  <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 16px; color: #1d2327;">Featured Context</h4>
                  <p style="margin: 0 0 10px; font-size: 14px; color: #3c434a;">This media file is featured in the following spaces across the AgriGames network:</p>
                  <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #3c434a; line-height: 1.6;">
                      <?php foreach ($featured_links as $link): ?>
                          <li style="margin-bottom: 6px;">
                              <a href="<?php echo esc_url($link->permalink); ?>" style="color: #2271b1; text-decoration: none; font-weight: bold;" target="_blank">
                                  <?php echo esc_html($link->post_title); ?>
                              </a> on <?php echo esc_html($link->site_name); ?>
                          </li>
                      <?php endforeach; ?>
                  </ul>
              </div>
              <?php endif; ?>

          </div>
          
          <?php if (!empty($related)): ?>
              <h3 class="tbf-related-title">Related Media</h3>
              <div class="tbf-photofall-grid caption-mode-<?php echo esc_attr($settings['caption_mode']); ?>">
                  <?php foreach ($related as $rel_item): echo wp_kses(self::get_item_html($rel_item), self::get_allowed_html()); endforeach; ?>
              </div>
          <?php endif; ?>
      </div>
      <?php self::render_lightbox_markup(); ?>
      <?php get_footer();
  }

  public static function get_allowed_html() {
      return [
          'div'    => ['class' => true, 'style' => true, 'onmouseover' => true, 'onmouseout' => true],
          'span'   => ['class' => true, 'style' => true],
          'button' => ['type' => true, 'onclick' => true, 'style' => true, 'class' => true, 'id' => true, 'title' => true],
          'img'    => [
              'src' => true, 'width' => true, 'height' => true, 
              'style' => true, 'loading' => true, 'decoding' => true, 
              'class' => true, 'data-id' => true, 'data-full' => true, 
              'data-type' => true, 'data-permalink' => true, 
              'data-caption' => true, 'onclick' => true, 'alt' => true,
              'data-source-title' => true, 'data-source-url' => true
          ],
          'a'      => ['href' => true, 'class' => true, 'target' => true, 'rel' => true, 'onclick' => true, 'style' => true]
      ];
  }

  public static function get_item_html($post) {
      $is_admin = current_user_can('manage_options') || is_super_admin();
      $tab = isset($_REQUEST['tbf_tab']) ? sanitize_text_field($_REQUEST['tbf_tab']) : 'active';
      $hidden_items = get_option('tbfnmi_hidden_media', []);

      $att_id = (int)($post->ID ?? $post->attachment_id ?? 0);
      $is_hidden = in_array($att_id, $hidden_items);

      if ( $tab === 'active' && $is_hidden ) return '';
      if ( $tab === 'hidden' && !$is_hidden ) return '';

      $url_full = self::resolve_url($post);

      if ( isset($post->tbf_url_thumb) ) {
          $src = $post->tbf_url_thumb; 
          if (!$url_full) $url_full = $post->tbf_url_full; 
      } else {
          if (!$url_full) $url_full = wp_get_attachment_url($att_id); 
          $thumb = wp_get_attachment_image_src($att_id, 'medium_large');
          $src = $thumb ? $thumb[0] : $url_full;
      }
      
      $type = $post->type ?? $post->media_type ?? 'image';
      $permalink = home_url('/photo/' . $type . '/' . ($post->blog_id ?? get_current_blog_id()) . '-' . $att_id . '/');
      $caption = esc_attr($post->post_excerpt ?? $post->caption ?? $post->post_title ?? $post->title);

      global $wpdb;
      $usage_table = $wpdb->base_prefix . 'tbfnmi_usage_map';
      $has_usage_table = !empty($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($usage_table))));
      $source_title = '';
      $source_url = '';

      if ($has_usage_table && !empty($url_full)) {
          $usage = $wpdb->get_row($wpdb->prepare("SELECT post_title, permalink FROM {$usage_table} WHERE media_url = %s ORDER BY id DESC LIMIT 1", $url_full));
          if ($usage) {
              $source_title = $usage->post_title;
              $source_url = $usage->permalink;
          }
      }
      
      $admin_nonce = wp_create_nonce('tbfnmi_admin_action');
      ob_start();
      ?>
      <div class="tbf-grid-item type-<?php echo esc_attr($type); ?>" style="position:relative;" onmouseover="if(this.querySelector('.tbf-pf-admin-controls')) this.querySelector('.tbf-pf-admin-controls').style.display='flex'" onmouseout="if(this.querySelector('.tbf-pf-admin-controls')) this.querySelector('.tbf-pf-admin-controls').style.display='none'">
        
        <?php if ( $is_admin ): ?>
            <div class="tbf-pf-admin-controls" style="display:none; position:absolute; top:10px; right:10px; z-index:10; background:rgba(0,0,0,0.8); padding:5px; border-radius:4px; gap:5px;">
                <button type="button" onclick="tbfnmiToggleHide(<?php echo esc_attr($att_id); ?>, '<?php echo esc_attr($admin_nonce); ?>')" style="background:#fff; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:bold; color:#555;"><?php echo $tab === 'hidden' ? 'Unhide' : 'Hide'; ?></button>
                <button type="button" onclick="tbfnmiDeleteMedia(<?php echo esc_attr($att_id); ?>, '<?php echo esc_attr($admin_nonce); ?>')" style="background:#d63638; color:#fff; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:bold;">Delete</button>
            </div>
        <?php endif; ?>

        <div class="tbf-media-card">
            <?php if ($type === 'video'): ?><div class="tbf-video-badge">▶</div><?php endif; ?>
            <?php if ($type === 'audio'): ?><div class="tbf-video-badge" style="background:#2271b1;">🎵</div><?php endif; ?>
            
            <img src="<?php echo esc_url($src); ?>" 
                 style="width:100%; height:auto;" 
                 loading="lazy" decoding="async" 
                 class="tbf-photofall-img" 
                 data-id="<?php echo esc_attr($att_id); ?>" 
                 data-full="<?php echo esc_url($url_full); ?>" 
                 data-type="<?php echo esc_attr($type); ?>" 
                 data-permalink="<?php echo esc_url($permalink); ?>" 
                 data-caption="<?php echo esc_attr($caption); ?>" 
                 data-source-title="<?php echo esc_attr($source_title); ?>" 
                 data-source-url="<?php echo esc_url($source_url); ?>" 
                 onclick="tbfnmi_photofall.open(this)">
            
            <div class="tbf-caption"><?php echo esc_html($post->post_excerpt ?? $post->caption ?? $post->post_title ?? $post->title); ?></div>
        </div>
      </div>
      <?php return ob_get_clean();
  }

  private static function render_lightbox_markup() {
      ?>
      <div id="tbf-lightbox" class="tbf-lightbox">
        <span class="tbf-close">&times;</span>
        <div class="tbf-lightbox-content">
            <img id="tbf-lb-img" src="" alt="Lightbox Media" style="display:none;">
            <video id="tbf-lb-video" controls style="display:none; max-height:85vh; max-width:90vw;"></video>
            <audio id="tbf-lb-audio" controls style="display:none; width:80%; max-width:500px;"></audio>
            
            <div class="tbf-lb-meta" style="margin-top:20px; text-align:center;">
                <div id="tbf-lb-caption" style="color:#fff; font-size:16px; margin-bottom:15px; font-weight:bold;"></div>
                <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                    <a id="tbf-lb-view-page" href="#" style="display:none; text-decoration:none; background:#444; color:#fff; padding:10px 20px; border-radius:6px; font-weight:bold; font-size:14px; transition:0.2s;">View Media Page</a>
                    <a id="tbf-lb-source-link" href="#" style="display:none; text-decoration:none; background:#2271b1; color:#fff; padding:10px 20px; border-radius:6px; font-weight:bold; font-size:14px; transition:0.2s;">View Source Post</a>
                </div>
            </div>
        </div>
        <a class="tbf-prev" style="cursor:pointer; position:absolute; left:20px; top:50%; color:#fff; font-size:40px; text-decoration:none; font-weight:bold;">&#10094;</a>
        <a class="tbf-next" style="cursor:pointer; position:absolute; right:20px; top:50%; color:#fff; font-size:40px; text-decoration:none; font-weight:bold;">&#10095;</a>
      </div>
      <?php
  }

  private static function render_upload_modal() {
      ?>
      <div id="tbfnmi-upload-modal" class="tbfnmi-modal-overlay">
          <div class="tbfnmi-modal-content">
              <span class="tbfnmi-modal-close" onclick="document.getElementById('tbfnmi-upload-modal').style.display='none'">&times;</span>
              <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Upload to AgriGames</h2>
              
              <div id="tbfnmi-upload-staging">
                  <div class="tbfnmi-drop-zone" onclick="document.getElementById('tbfnmi-file-input').click()">
                      <span class="dashicons dashicons-upload" style="font-size:40px; height:40px; width:40px; color:#aaa;"></span>
                      <p style="margin:10px 0 0; color:#666;">Click to Select Images, Videos, or Audio</p>
                      <input type="file" id="tbfnmi-file-input" multiple accept="image/*,video/*,audio/*" style="display:none;">
                  </div>
                  
                  <div id="tbfnmi-queue-list" class="tbfnmi-queue-list"></div>
              </div>

              <div style="border-top:1px solid #eee; padding-top:15px; display:flex; justify-content:flex-end;">
                  <button type="button" id="tbfnmi-start-upload" class="tbfnmi-upload-btn" disabled>Start Upload</button>
              </div>
          </div>
      </div>
      <?php
  }

  private static function enqueue_assets($max_pages = 1, $current_args = []) {
      wp_enqueue_style('tbf-photofall', TBFNMI_URL . 'assets/css/photofall-public.css', ['dashicons'], TBFNMI_VER);
      wp_enqueue_script('tbf-photofall', TBFNMI_URL . 'assets/js/photofall-public.js', [], TBFNMI_VER, true);
      
      $includes_url = includes_url();
      
      wp_localize_script('tbf-photofall', 'tbfnmi_data', [
          'ajax_url'    => admin_url('admin-ajax.php'),
          'nonce'       => wp_create_nonce('tbfnmi_frontend'), 
          'includes_url'=> $includes_url,
          'max_pages'   => $max_pages,
          'current_page'=> 1,
          'filter'      => isset($current_args['filter']) ? $current_args['filter'] : 'all',
          'source'      => isset($current_args['source']) ? $current_args['source'] : 'all',
          'sort'        => isset($current_args['sort']) ? $current_args['sort'] : '',
          'search'      => isset($current_args['search']) ? $current_args['search'] : '',
          'year'        => isset($current_args['year']) ? $current_args['year'] : '',
          'site_filter' => isset($current_args['site_filter']) ? $current_args['site_filter'] : '',
      ]);
  }
}