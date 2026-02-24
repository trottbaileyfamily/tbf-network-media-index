<?php
/**
 * File: includes/photofall/class-tbfnmi-photofall-templates.php
 * Version: 6.5.0 (Strict Late Escaping & KSES Output)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Photofall_Templates {

  public static function render_page($data, $settings, $current_args, $filter_options) {
    self::enqueue_assets($data['max_pages'], $current_args);
    $media = $data['posts'];
    get_header(); 
    ?>
    <div class="tbf-photofall-wrapper">
      
      <form method="get" action="<?php echo esc_url(home_url('/photo/')); ?>" id="tbf-filter-form" class="tbf-photofall-toolbar">
        
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
        </div>

        <div class="tbf-toolbar-secondary">
            <select name="tbf_source" class="tbf-auto-submit">
                <option value="all" <?php selected($current_args['source'], 'all'); ?>>All Uploads</option>
                <option value="frontend" <?php selected($current_args['source'], 'frontend'); ?>>Frontend (Vikinger)</option>
                <option value="backend" <?php selected($current_args['source'], 'backend'); ?>>Backend (WP Library)</option>
            </select>

            <?php if (!empty($settings['show_filter_year']) && !empty($filter_options['years'])): ?>
                <select name="tbf_year" class="tbf-auto-submit">
                    <option value="">Any Year</option>
                    <?php foreach ($filter_options['years'] as $yr): ?>
                        <option value="<?php echo esc_attr($yr); ?>" <?php selected($current_args['year'], $yr); ?>><?php echo esc_html($yr); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if (!empty($settings['show_filter_site']) && !empty($filter_options['sites'])): ?>
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

            <?php if (!empty($settings['show_random'])): ?>
                <button type="submit" name="tbf_sort" value="random" class="tbf-btn tbf-btn-icon" title="Shuffle"><span class="dashicons dashicons-randomize"></span></button>
            <?php endif; ?>
        </div>
      </form>

      <div id="tbf-grid-container" class="tbf-photofall-grid caption-mode-<?php echo esc_attr($settings['caption_mode']); ?>">
        <?php if (empty($media)): ?>
            <div class="tbf-no-results">
                <h2>No media found</h2>
                <p>Try adjusting your search or filters.</p>
                <a href="<?php echo esc_url(home_url('/photo/')); ?>" class="tbf-btn">Clear All Filters</a>
            </div>
        <?php else: ?>
            <?php foreach ($media as $item): echo wp_kses(self::get_item_html($item), self::get_allowed_html()); endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if ( $data['max_pages'] > 1 ): ?>
          <div class="tbf-load-more-wrap">
              <button id="tbf-load-more" class="tbf-btn">Load More</button>
              <span id="tbf-loader">Loading...</span>
          </div>
      <?php endif; ?>
    </div>
    <?php self::render_lightbox_markup(); ?>
    <?php get_footer();
  }

  public static function render_single($item, $related, $settings) {
      self::enqueue_assets(1, ['sort' => $settings['default_sort']]);
      get_header();
      
      $url_full = $item->tbf_url_full; 
      $type = $item->type;

      ?>
      <div class="tbf-photofall-wrapper tbf-single-view">
          <div class="tbf-single-header">
              <a href="<?php echo esc_url(home_url('/photo/')); ?>" class="tbf-btn">&larr; Back to Gallery</a>
          </div>
          <div class="tbf-single-stage">
              <?php if ($type === 'video'): ?>
                  <video src="<?php echo esc_url($url_full); ?>" controls class="tbf-single-media"></video>
              <?php elseif ($type === 'audio'): ?>
                  <div style="background:#fff; padding:40px; border-radius:12px; display:inline-block; max-width:100%; border:1px solid #ddd; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                      <img src="<?php echo esc_url(includes_url('images/media/audio.png')); ?>" style="width:100px; height:auto; margin-bottom:20px;" alt="Audio File">
                      <audio src="<?php echo esc_url($url_full); ?>" controls style="width:100%; min-width:300px;"></audio>
                  </div>
              <?php else: ?>
                  <img src="<?php echo esc_url($url_full); ?>" class="tbf-single-media" decoding="async"
                       onclick="tbfnmi_photofall.openRaw('<?php echo esc_url($url_full); ?>', 'image', '<?php echo esc_attr($item->post_title); ?>')">
              <?php endif; ?>
              
              <div class="tbf-single-info">
                  <h1><?php echo esc_html($item->post_title); ?></h1>
                  <?php if($item->post_excerpt): ?><p><?php echo esc_html($item->post_excerpt); ?></p><?php endif; ?>
              </div>
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
          'div'  => ['class' => true, 'style' => true],
          'span' => ['class' => true, 'style' => true],
          'img'  => [
              'src' => true, 'width' => true, 'height' => true, 
              'style' => true, 'loading' => true, 'decoding' => true, 
              'class' => true, 'data-id' => true, 'data-full' => true, 
              'data-type' => true, 'data-permalink' => true, 
              'data-caption' => true, 'onclick' => true, 'alt' => true
          ],
          'a'    => ['href' => true, 'class' => true, 'target' => true, 'rel' => true]
      ];
  }

  public static function get_item_html($post) {
      if ( isset($post->tbf_url_thumb) ) {
          $src = $post->tbf_url_thumb; $url_full = $post->tbf_url_full; $w = $post->tbf_width; $h = $post->tbf_height;
      } else {
          $url_full = wp_get_attachment_url($post->ID); $thumb = wp_get_attachment_image_src($post->ID, 'medium_large');
          $src = $thumb ? $thumb[0] : $url_full; $w = $thumb ? $thumb[1] : 0; $h = $thumb ? $thumb[2] : 0;
      }
      
      if ($w < 1) $w = 800; if ($h < 1) $h = 600;
      $style_ar = "aspect-ratio: {$w} / {$h};";
      
      $type = $post->type ?? 'image';
      $permalink = home_url('/photo/' . $type . '/' . $post->blog_id . '-' . $post->ID . '/');
      $caption = esc_attr($post->post_excerpt ?: $post->post_title);
      
      ob_start();
      ?>
      <div class="tbf-grid-item type-<?php echo esc_attr($type); ?>">
        <div class="tbf-media-card">
            <?php if ($type === 'video'): ?><div class="tbf-video-badge">▶</div><?php endif; ?>
            <?php if ($type === 'audio'): ?><div class="tbf-video-badge" style="background:#2271b1;">🎵</div><?php endif; ?>
            <img src="<?php echo esc_url($src); ?>" width="<?php echo (int)$w; ?>" height="<?php echo (int)$h; ?>" style="<?php echo esc_attr($style_ar); ?>" loading="lazy" decoding="async" class="tbf-photofall-img" data-id="<?php echo esc_attr($post->ID); ?>" data-full="<?php echo esc_url($url_full); ?>" data-type="<?php echo esc_attr($type); ?>" data-permalink="<?php echo esc_url($permalink); ?>" data-caption="<?php echo esc_attr($caption); ?>" onclick="tbfnmi_photofall.open(this)">
            <div class="tbf-caption"><?php echo esc_html($post->post_excerpt ?: $post->post_title); ?></div>
        </div>
      </div>
      <?php return ob_get_clean();
  }

  private static function render_lightbox_markup() {
      ?>
      <div id="tbf-lightbox" class="tbf-lightbox">
        <span class="tbf-close">&times;</span>
        <div class="tbf-lightbox-content">
            <img id="tbf-lb-img" src="" alt="Lightbox Media">
            <video id="tbf-lb-video" controls style="display:none"></video>
            <audio id="tbf-lb-audio" controls style="display:none; width:80%; max-width:500px;"></audio>
            <div class="tbf-lb-meta"><div id="tbf-lb-caption"></div><a id="tbf-lb-link" href="#" class="tbf-btn tbf-btn-sm">View Page</a></div>
        </div>
        <a class="tbf-prev">&#10094;</a><a class="tbf-next">&#10095;</a>
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