<?php
/**
 * File: includes/admin/class-tbfnmi-subsite-settings.php
 * Version: 6.5.2 (Tabbed UI & Secure Sitemap Pinging)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Subsite_Settings {

  const OPTION_KEY = 'tbfnmi_photofall_options';

  public static function init() {
    $enabled_sites = get_site_option('tbfnmi_photofall_enabled_sites', []);
    if ( ! in_array(get_current_blog_id(), $enabled_sites) ) return;

    add_action('admin_menu', [__CLASS__, 'register_subsite_page']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('admin_init', [__CLASS__, 'handle_sitemap_ping']);
  }

  public static function register_subsite_page() {
    add_menu_page(
      esc_html__('TBF Network Media', 'tbf-network-media-index'), 
      esc_html__('TBF Network Media', 'tbf-network-media-index'), 
      'manage_options',
      'tbfnmi-photofall',
      [__CLASS__, 'render_page'],
      'dashicons-grid-view',
      30
    );
  }

  public static function register_settings() {
    register_setting(
        'tbfnmi_photofall_group', 
        self::OPTION_KEY, 
        [
            'sanitize_callback' => [__CLASS__, 'sanitize_options']
        ]
    );
  }

  public static function handle_sitemap_ping() {
      if ( isset($_POST['tbfnmi_ping_sitemaps']) && check_admin_referer('tbfnmi_ping_action', 'tbfnmi_ping_nonce') ) {
          if ( ! current_user_can('manage_options') ) return;

          $photoIndex = home_url('/photo-sitemap-index.xml');
          $videoIndex = home_url('/video-sitemap-index.xml');

          // Ping Google
          wp_remote_get('https://www.google.com/ping?sitemap=' . urlencode($photoIndex), ['blocking' => false]);
          wp_remote_get('https://www.google.com/ping?sitemap=' . urlencode($videoIndex), ['blocking' => false]);
          
          // Ping Bing
          wp_remote_get('https://www.bing.com/ping?sitemap=' . urlencode($photoIndex), ['blocking' => false]);
          wp_remote_get('https://www.bing.com/ping?sitemap=' . urlencode($videoIndex), ['blocking' => false]);

          wp_redirect(add_query_arg(['page' => 'tbfnmi-photofall', 'tab' => 'sitemaps', 'pinged' => '1'], admin_url('admin.php')));
          exit;
      }
  }

  public static function sanitize_options($input) {
      $output = [];
      $output['show_search']      = !empty($input['show_search']) ? 1 : 0;
      $output['show_filter_type'] = !empty($input['show_filter_type']) ? 1 : 0;
      $output['show_filter_year'] = !empty($input['show_filter_year']) ? 1 : 0;
      $output['show_filter_site'] = !empty($input['show_filter_site']) ? 1 : 0;
      $output['show_random']      = !empty($input['show_random']) ? 1 : 0;
      $output['show_sort']        = !empty($input['show_sort']) ? 1 : 0;
      $output['show_frontend']    = !empty($input['show_frontend']) ? 1 : 0;
      $output['default_sort']     = isset($input['default_sort']) ? sanitize_text_field($input['default_sort']) : 'date_desc';
      $output['caption_mode']     = isset($input['caption_mode']) ? sanitize_text_field($input['caption_mode']) : 'hover';
      $output['allowed_types']    = (isset($input['allowed_types']) && is_array($input['allowed_types'])) ? array_map('sanitize_text_field', $input['allowed_types']) : [];
      $output['source_sites']     = (isset($input['source_sites']) && is_array($input['source_sites'])) ? array_map('intval', $input['source_sites']) : [];
      return $output;
  }

  public static function get_options() {
    $defaults = [
      'show_search'      => 1, 
      'show_filter_type' => 1, 
      'show_filter_year' => 1, 
      'show_filter_site' => 1, 
      'show_random'      => 1,
      'show_sort'        => 1,
      'default_sort'     => 'date_desc', 
      'caption_mode'     => 'hover',
      'allowed_types'    => ['image', 'video', 'audio'], 
      'source_sites'     => [],
      'show_frontend'    => 1, 
    ];
    $opts = get_option(self::OPTION_KEY, []);
    return wp_parse_args($opts, $defaults);
  }

  public static function render_page() {
    $opts = self::get_options();
    $all_sites = get_sites(['number' => 500]); 
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('TBF Photofall Configuration', 'tbf-network-media-index'); ?></h1>
      
      <h2 class="nav-tab-wrapper">
          <a href="?page=tbfnmi-photofall&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General Settings', 'tbf-network-media-index'); ?></a>
          <a href="?page=tbfnmi-photofall&tab=sitemaps" class="nav-tab <?php echo $active_tab === 'sitemaps' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('SEO & Sitemaps', 'tbf-network-media-index'); ?></a>
      </h2>

      <?php if ( $active_tab === 'general' ): ?>
          <form method="post" action="options.php">
            <?php settings_fields('tbfnmi_photofall_group'); ?>
            
            <table class="form-table">
              <tr>
                <th scope="row"><?php esc_html_e('Advanced Filter Experience', 'tbf-network-media-index'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php esc_html_e('Frontend Filters', 'tbf-network-media-index'); ?></span></legend>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_search]" value="1" <?php checked($opts['show_search']); ?>> <strong><?php esc_html_e('Show Search Bar', 'tbf-network-media-index'); ?></strong></label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_filter_type]" value="1" <?php checked($opts['show_filter_type']); ?>> <?php esc_html_e('Show Media Type Buttons', 'tbf-network-media-index'); ?></label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_filter_year]" value="1" <?php checked($opts['show_filter_year']); ?>> <?php esc_html_e('Show Upload Year Dropdown', 'tbf-network-media-index'); ?></label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_filter_site]" value="1" <?php checked($opts['show_filter_site']); ?>> <?php esc_html_e('Show Source Site Dropdown', 'tbf-network-media-index'); ?></label><br>
                    </fieldset>
                </td>
              </tr>

              <tr>
                <th scope="row"><?php esc_html_e('Sorting & Order', 'tbf-network-media-index'); ?></th>
                <td>
                    <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_sort]">
                        <option value="date_desc" <?php selected($opts['default_sort'], 'date_desc'); ?>><?php esc_html_e('Newest First', 'tbf-network-media-index'); ?></option>
                        <option value="date_asc" <?php selected($opts['default_sort'], 'date_asc'); ?>><?php esc_html_e('Oldest First', 'tbf-network-media-index'); ?></option>
                        <option value="random" <?php selected($opts['default_sort'], 'random'); ?>><?php esc_html_e('Random Order', 'tbf-network-media-index'); ?></option>
                    </select>
                    <br><br>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_random]" value="1" <?php checked($opts['show_random']); ?>> <?php esc_html_e('Show "Randomize" Quick Button', 'tbf-network-media-index'); ?></label><br>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_sort]" value="1" <?php checked($opts['show_sort']); ?>> <?php esc_html_e('Show Sort Dropdown', 'tbf-network-media-index'); ?></label>
                </td>
              </tr>

              <tr>
                <th scope="row"><?php esc_html_e('Source Content', 'tbf-network-media-index'); ?></th>
                <td>
                  <fieldset>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_frontend]" value="1" <?php checked($opts['show_frontend']); ?>> 
                        <strong><?php esc_html_e('Include Frontend/BuddyPress Uploads', 'tbf-network-media-index'); ?></strong>
                    </label>
                  </fieldset>
                  <br>
                  <fieldset>
                    <strong><?php esc_html_e('Pull Media From These Sites:', 'tbf-network-media-index'); ?></strong>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff; margin-top:5px;">
                      <?php foreach ($all_sites as $site): ?>
                        <label style="display:block; margin-bottom:5px;">
                          <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[source_sites][]" 
                                 value="<?php echo esc_attr($site->blog_id); ?>"
                                 <?php checked(in_array($site->blog_id, $opts['source_sites'])); ?>>
                          <?php echo esc_html(get_blog_option($site->blog_id, 'blogname')); ?> 
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                </td>
              </tr>

              <tr>
                <th scope="row"><?php esc_html_e('Allowed Types', 'tbf-network-media-index'); ?></th>
                <td>
                  <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_types][]" value="image" <?php checked(in_array('image', $opts['allowed_types'])); ?>> <?php esc_html_e('Images', 'tbf-network-media-index'); ?></label><br>
                  <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_types][]" value="video" <?php checked(in_array('video', $opts['allowed_types'])); ?>> <?php esc_html_e('Videos', 'tbf-network-media-index'); ?></label><br>
                  <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_types][]" value="audio" <?php checked(in_array('audio', $opts['allowed_types'])); ?>> <?php esc_html_e('Audio', 'tbf-network-media-index'); ?></label>
                </td>
              </tr>

              <tr>
                <th scope="row"><?php esc_html_e('Captions', 'tbf-network-media-index'); ?></th>
                <td>
                  <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[caption_mode]">
                    <option value="hover" <?php selected($opts['caption_mode'], 'hover'); ?>><?php esc_html_e('Show on Hover', 'tbf-network-media-index'); ?></option>
                    <option value="always" <?php selected($opts['caption_mode'], 'always'); ?>><?php esc_html_e('Always Visible', 'tbf-network-media-index'); ?></option>
                    <option value="never" <?php selected($opts['prevent_status_revert'], 'never'); ?>><?php esc_html_e('Never Show', 'tbf-network-media-index'); ?></option>
                  </select>
                </td>
              </tr>

            </table>
            <?php submit_button(); ?>
          </form>

      <?php elseif ( $active_tab === 'sitemaps' ): ?>
          <?php 
          $photoIndex = home_url('/photo-sitemap-index.xml');
          $videoIndex = home_url('/video-sitemap-index.xml');
          $pinged = isset($_GET['pinged']) && $_GET['pinged'] === '1';
          ?>
          
          <?php if ( $pinged ): ?>
              <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Search engines have been notified successfully.', 'tbf-network-media-index'); ?></p></div>
          <?php endif; ?>

          <div class="card" style="max-width: 800px; margin-top: 20px;">
              <h2><?php esc_html_e('Photofall Media Sitemaps', 'tbf-network-media-index'); ?></h2>
              <p><?php esc_html_e('Your network media is automatically indexed and formatted for Google Search. Submit these index URLs directly to Google Search Console:', 'tbf-network-media-index'); ?></p>
              
              <ul style="font-size:15px; line-height:1.8; background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1;">
                  <li><strong><?php esc_html_e('Photo Sitemap:', 'tbf-network-media-index'); ?></strong> <a href="<?php echo esc_url($photoIndex); ?>" target="_blank" rel="noopener"><?php echo esc_html($photoIndex); ?></a></li>
                  <li><strong><?php esc_html_e('Video Sitemap:', 'tbf-network-media-index'); ?></strong> <a href="<?php echo esc_url($videoIndex); ?>" target="_blank" rel="noopener"><?php echo esc_html($videoIndex); ?></a></li>
              </ul>
              <p class="description"><?php esc_html_e('If these URLs return a 404 error, simply navigate to Settings → Permalinks and click "Save Changes" to flush the rewrite rules.', 'tbf-network-media-index'); ?></p>
              
              <hr style="margin: 20px 0;">
              
              <h3><?php esc_html_e('Manual Search Engine Ping', 'tbf-network-media-index'); ?></h3>
              <p><?php esc_html_e('Click the button below to instantly notify Google and Bing that your media sitemaps have been updated.', 'tbf-network-media-index'); ?></p>
              
              <form method="post" action="">
                  <?php wp_nonce_field('tbfnmi_ping_action', 'tbfnmi_ping_nonce'); ?>
                  <button type="submit" name="tbfnmi_ping_sitemaps" class="button button-primary"><?php esc_html_e('Notify Search Engines', 'tbf-network-media-index'); ?></button>
              </form>
          </div>
      <?php endif; ?>

    </div>
    <?php
  }
}