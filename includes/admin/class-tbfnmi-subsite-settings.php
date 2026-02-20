<?php
/**
 * File: includes/admin/class-tbfnmi-subsite-settings.php
 * Version: 6.1.1 (Added Setting Sanitization)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Subsite_Settings {

  const OPTION_KEY = 'tbfnmi_photofall_options';

  public static function init() {
    $enabled_sites = get_site_option('tbfnmi_photofall_enabled_sites', []);
    if ( ! in_array(get_current_blog_id(), $enabled_sites) ) return;

    add_action('admin_menu', [__CLASS__, 'register_subsite_page']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
  }

  public static function register_subsite_page() {
    add_menu_page(
      'TBF Network Media', 
      'TBF Network Media', 
      'manage_options',
      'tbfnmi-photofall',
      [__CLASS__, 'render_page'],
      'dashicons-grid-view',
      30
    );
  }

  public static function register_settings() {
    // Added the required sanitization callback to satisfy WP Code Standards
    register_setting(
        'tbfnmi_photofall_group', 
        self::OPTION_KEY, 
        [
            'sanitize_callback' => [__CLASS__, 'sanitize_options']
        ]
    );
  }

  // NEW: Robust sanitization function for every field
  public static function sanitize_options($input) {
      $output = [];
      
      // Sanitize booleans/checkboxes as strict integers (1 or 0)
      $output['show_search']      = !empty($input['show_search']) ? 1 : 0;
      $output['show_filter_type'] = !empty($input['show_filter_type']) ? 1 : 0;
      $output['show_filter_year'] = !empty($input['show_filter_year']) ? 1 : 0;
      $output['show_filter_site'] = !empty($input['show_filter_site']) ? 1 : 0;
      $output['show_random']      = !empty($input['show_random']) ? 1 : 0;
      $output['show_sort']        = !empty($input['show_sort']) ? 1 : 0;
      $output['show_frontend']    = !empty($input['show_frontend']) ? 1 : 0;

      // Sanitize standard text fields
      $output['default_sort']     = isset($input['default_sort']) ? sanitize_text_field($input['default_sort']) : 'date_desc';
      $output['caption_mode']     = isset($input['caption_mode']) ? sanitize_text_field($input['caption_mode']) : 'hover';

      // Sanitize arrays of text values
      $output['allowed_types']    = (isset($input['allowed_types']) && is_array($input['allowed_types'])) 
                                      ? array_map('sanitize_text_field', $input['allowed_types']) 
                                      : [];
                                      
      // Sanitize arrays of IDs as strict integers
      $output['source_sites']     = (isset($input['source_sites']) && is_array($input['source_sites'])) 
                                      ? array_map('intval', $input['source_sites']) 
                                      : [];
                                      
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
    ?>
    <div class="wrap">
      <h1>TBF Photofall Settings</h1>
      <form method="post" action="options.php">
        <?php settings_fields('tbfnmi_photofall_group'); ?>
        
        <table class="form-table">
          <tr>
            <th scope="row">Advanced Filter Experience</th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span>Frontend Filters</span></legend>
                    <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_search]" value="1" <?php checked($opts['show_search']); ?>> <strong>Show Search Bar</strong></label><br>
                    <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_filter_type]" value="1" <?php checked($opts['show_filter_type']); ?>> Show Media Type Buttons (All/Images/Videos/Audio)</label><br>
                    <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_filter_year]" value="1" <?php checked($opts['show_filter_year']); ?>> Show Upload Year Dropdown</label><br>
                    <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_filter_site]" value="1" <?php checked($opts['show_filter_site']); ?>> Show Source Site Dropdown</label><br>
                </fieldset>
                <p class="description">Enable these tools to help users navigate your 10,000+ image library.</p>
            </td>
          </tr>

          <tr>
            <th scope="row">Sorting & Order</th>
            <td>
                <select name="<?php echo self::OPTION_KEY; ?>[default_sort]">
                    <option value="date_desc" <?php selected($opts['default_sort'], 'date_desc'); ?>>Newest First</option>
                    <option value="date_asc" <?php selected($opts['default_sort'], 'date_asc'); ?>>Oldest First</option>
                    <option value="random" <?php selected($opts['default_sort'], 'random'); ?>>Random Order</option>
                </select>
                <p class="description">The default order when a user visits the archive or views Related Media.</p>
                <br>
                <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_random]" value="1" <?php checked($opts['show_random']); ?>> Show "Randomize" Quick Button</label><br>
                <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_sort]" value="1" <?php checked($opts['show_sort']); ?>> Show Sort Dropdown</label>
            </td>
          </tr>

          <tr>
            <th scope="row">Source Content</th>
            <td>
              <fieldset>
                <label>
                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_frontend]" value="1" <?php checked($opts['show_frontend']); ?>> 
                    <strong>Include Frontend/BuddyPress Uploads</strong>
                </label>
              </fieldset>
              <br>
              <fieldset>
                <strong>Pull Media From These Sites:</strong>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff; margin-top:5px;">
                  <?php foreach ($all_sites as $site): ?>
                    <label style="display:block; margin-bottom:5px;">
                      <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[source_sites][]" 
                             value="<?php echo $site->blog_id; ?>"
                             <?php checked(in_array($site->blog_id, $opts['source_sites'])); ?>>
                      <?php echo esc_html(get_blog_option($site->blog_id, 'blogname')); ?> 
                    </label>
                  <?php endforeach; ?>
                </div>
                <p class="description">Leave all unchecked to show media from the entire network.</p>
              </fieldset>
            </td>
          </tr>

          <tr>
            <th scope="row">Allowed Types</th>
            <td>
              <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[allowed_types][]" value="image" <?php checked(in_array('image', $opts['allowed_types'])); ?>> Images</label><br>
              <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[allowed_types][]" value="video" <?php checked(in_array('video', $opts['allowed_types'])); ?>> Videos</label><br>
              <label><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[allowed_types][]" value="audio" <?php checked(in_array('audio', $opts['allowed_types'])); ?>> Audio</label>
            </td>
          </tr>

          <tr>
            <th scope="row">Captions</th>
            <td>
              <select name="<?php echo self::OPTION_KEY; ?>[caption_mode]">
                <option value="hover" <?php selected($opts['caption_mode'], 'hover'); ?>>Show on Hover</option>
                <option value="always" <?php selected($opts['caption_mode'], 'always'); ?>>Always Visible</option>
                <option value="never" <?php selected($opts['caption_mode'], 'never'); ?>>Never Show</option>
              </select>
            </td>
          </tr>

        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }
}