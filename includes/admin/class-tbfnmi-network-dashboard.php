<?php
/**
 * File: includes/admin/class-tbfnmi-network-dashboard.php
 * Version: 6.2.6 (Enqueued Script Fixes)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Network_Dashboard {

  const PAGE_SLUG = 'tbfnmi-network';
  const OPTION_GENERAL       = 'tbfnmi_settings';
  const OPTION_ENABLED_SITES = 'tbfnmi_photofall_enabled_sites';
  const OPTION_BP_SETTINGS   = 'tbfnmi_buddypress_settings';

  public static function init() {
    add_action('network_admin_menu', [__CLASS__, 'register_menu']);
    add_action('network_admin_edit_tbfnmi_save', [__CLASS__, 'save_settings']);
    add_action('wp_ajax_tbfnmi_index_batch', [__CLASS__, 'ajax_index_batch']);
    // Hook the new asset enqueue system
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function enqueue_assets($hook) {
      // Ensure we only load this JS on our specific plugin dashboard page
      if (strpos($hook, self::PAGE_SLUG) === false) return;

      wp_enqueue_script(
          'tbfnmi-admin-dashboard',
          TBFNMI_URL . 'assets/js/admin-dashboard.js',
          ['jquery'],
          TBFNMI_VER,
          true
      );

      // Gather the sites data to pass securely to Javascript
      $raw_sites = get_sites(['number' => 500]); 
      $sites_data = [];
      foreach($raw_sites as $s) {
          $sites_data[] = [
              'id'   => $s->blog_id,
              'name' => get_blog_option($s->blog_id, 'blogname') ?: 'Site ' . $s->blog_id
          ];
      }

      // Securely pass data to the JS file
      wp_localize_script('tbfnmi-admin-dashboard', 'tbfnmi_dashboard_data', [
          'ajaxurl' => admin_url('admin-ajax.php'),
          'nonce'   => wp_create_nonce('tbfnmi_indexer_run'),
          'sites'   => $sites_data
      ]);
  }

  public static function register_menu() {
    add_menu_page(
      'TBF Network Media', 
      'TBF Network Media', 
      'manage_network_options',
      self::PAGE_SLUG,
      [__CLASS__, 'render_page'],
      'dashicons-images-alt2',
      25
    );
  }

  public static function get_current_tab() {
      return isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
  }

  public static function render_page() {
    $tab = self::get_current_tab();
    ?>
    <div class="wrap">
      <h1>TBF Network Media Index</h1>
      
      <nav class="nav-tab-wrapper">
        <a href="?page=<?php echo esc_attr(self::PAGE_SLUG); ?>&tab=general" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">General Settings</a>
        <a href="?page=<?php echo esc_attr(self::PAGE_SLUG); ?>&tab=photofall" class="nav-tab <?php echo $tab === 'photofall' ? 'nav-tab-active' : ''; ?>">Photofall Sites</a>
        <a href="?page=<?php echo esc_attr(self::PAGE_SLUG); ?>&tab=buddypress" class="nav-tab <?php echo $tab === 'buddypress' ? 'nav-tab-active' : ''; ?>">Frontend Uploads</a>
        <a href="?page=<?php echo esc_attr(self::PAGE_SLUG); ?>&tab=indexer" class="nav-tab <?php echo $tab === 'indexer' ? 'nav-tab-active' : ''; ?>">Indexer (Import)</a>
      </nav>

      <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=tbfnmi_save')); ?>">
        <?php wp_nonce_field('tbfnmi_save'); ?>
        <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">

        <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-top:15px; max-width:1000px;">
            <?php 
            switch ($tab) {
                case 'general':    self::render_general_tab(); break;
                case 'photofall':  self::render_photofall_tab(); break;
                case 'buddypress': self::render_buddypress_tab(); break;
                case 'indexer':    self::render_indexer_tab(); break;
            }
            ?>
        </div>

        <?php if ($tab !== 'indexer'): ?>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings"></p>
        <?php endif; ?>
      </form>
    </div>
    <?php
  }

  private static function render_general_tab() {
      $defaults = ['capability' => 'upload_files', 'insert_mode' => 'proxy', 'per_page' => 60, 'max_sites' => 5000];
      $opts = get_site_option(self::OPTION_GENERAL, $defaults);
      ?>
      <h3>General Configuration</h3>
      <table class="form-table">
          <tr>
              <th scope="row">Who can browse Network Media?</th>
              <td>
                  <fieldset>
                      <label><input type="radio" name="settings[capability]" value="upload_files" <?php checked($opts['capability'], 'upload_files'); ?>> Uploaders</label><br>
                      <label><input type="radio" name="settings[capability]" value="manage_options" <?php checked($opts['capability'], 'manage_options'); ?>> Admins Only</label><br>
                      <label><input type="radio" name="settings[capability]" value="manage_network_options" <?php checked($opts['capability'], 'manage_network_options'); ?>> Super Admins Only</label>
                  </fieldset>
              </td>
          </tr>
          <tr>
              <th scope="row">Insert Mode</th>
              <td>
                  <fieldset>
                      <label><input type="radio" name="settings[insert_mode]" value="proxy" <?php checked($opts['insert_mode'], 'proxy'); ?>> <strong>Proxy Attachment</strong></label><br>
                      <label><input type="radio" name="settings[insert_mode]" value="direct" <?php checked($opts['insert_mode'], 'direct'); ?>> Direct URL</label>
                  </fieldset>
              </td>
          </tr>
          <tr>
              <th scope="row">Items Per Page</th>
              <td><input type="number" name="settings[per_page]" value="<?php echo esc_attr($opts['per_page']); ?>" class="small-text"></td>
          </tr>
          <tr>
              <th scope="row">Max Sites to Scan</th>
              <td><input type="number" name="settings[max_sites]" value="<?php echo esc_attr($opts['max_sites']); ?>" class="small-text"></td>
          </tr>
      </table>
      <?php
  }

  private static function render_photofall_tab() {
    $sites = get_sites(['number' => 500]); 
    $enabled = get_site_option(self::OPTION_ENABLED_SITES, []);
    ?>
    <h3>Photofall Activation</h3>
    <table class="widefat striped" style="max-height: 400px; display:block; overflow-y:auto;">
        <thead><tr><th class="check-column"><input type="checkbox" id="cb-select-all-1"></th><th>Site Name</th><th>Path</th></tr></thead>
        <tbody>
            <?php foreach ( $sites as $site ): ?>
            <tr>
                <td><input type="checkbox" name="enabled_sites[]" value="<?php echo esc_attr($site->blog_id); ?>" <?php checked(in_array($site->blog_id, $enabled)); ?>></td>
                <td><strong><?php echo esc_html(get_blog_option($site->blog_id, 'blogname')); ?></strong></td>
                <td><?php echo esc_html($site->path); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
  }

  private static function render_buddypress_tab() {
    $bp_defaults = ['enabled' => 0, 'roles' => ['administrator']];
    $bp_settings = get_site_option(self::OPTION_BP_SETTINGS, $bp_defaults);
    $wp_roles = new WP_Roles(); 
    $all_roles = $wp_roles->get_names();
    ?>
    <h3>Frontend & BuddyPress Uploads</h3>
    <table class="form-table">
        <tr>
            <th scope="row">Auto-Indexing</th>
            <td>
            <label>
                <input type="checkbox" name="bp_indexing_enabled" value="1" <?php checked($bp_settings['enabled']); ?>>
                <strong>Enable Indexing for Frontend Uploads</strong>
            </label>
            </td>
        </tr>
        <tr>
            <th scope="row">Allowed Roles</th>
            <td>
            <fieldset>
                <?php foreach ($all_roles as $role_key => $role_name): ?>
                <label style="display:inline-block; margin-right:15px; margin-bottom:5px;">
                    <input type="checkbox" name="bp_allowed_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $bp_settings['roles'])); ?>>
                    <?php echo esc_html($role_name); ?>
                </label><br>
                <?php endforeach; ?>
            </fieldset>
            </td>
        </tr>
    </table>
    <?php
  }

  private static function render_indexer_tab() {
      // Inline JS removed and migrated to assets/js/admin-dashboard.js
      ?>
      <h3>Network Batch Indexer & Integrations</h3>
      <p>Scan the network in real-time. This tool processes images in small batches so your server won't crash.</p>

      <div id="tbf-indexer-ui">
          <p style="display:flex; gap:15px; align-items:center;">
              <button type="button" id="start-indexing" class="button button-primary button-hero">Start Full Network Index</button>
              <button type="button" id="sync-vikinger" class="button button-secondary button-hero">Sync Vikinger Frontend Uploads</button>
          </p>
          
          <div id="index-progress" style="display:none; margin-top:20px; border:1px solid #ddd; border-radius:8px; overflow:hidden;">
              
              <div style="display:flex; background:#f1f1f1; border-bottom:1px solid #ddd; padding:15px;">
                  <div style="flex:1;"><strong>Overall Progress:</strong> <span id="stat-progress">0%</span></div>
                  <div style="flex:1;"><strong>Current Site:</strong> <span id="stat-site">-</span></div>
                  <div style="flex:1; text-align:right;"><strong>Total Indexed Network-Wide:</strong> <span id="stat-total-indexed" style="color:#2271b1; font-size:16px; font-weight:bold;">0</span></div>
              </div>

              <div style="background:#fff; height:25px; width:100%;">
                  <div id="progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.3s;"></div>
              </div>

              <div style="background:#1e1e1e; color:#00ff00; padding:15px; font-family:monospace; font-size:13px;">
                  <ul id="log-list" style="margin:0; max-height:250px; overflow-y:auto; list-style:none; padding-left:0;">
                      <li>> System Ready. Awaiting command...</li>
                  </ul>
              </div>

          </div>
      </div>
      <?php
  }

  public static function save_settings() {
    check_admin_referer('tbfnmi_save');
    if ( ! current_user_can('manage_network_options') ) wp_die('Unauthorized');
    $tab = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : 'general';
    
    if ($tab === 'general') {
        $settings = [
            'capability'  => isset($_POST['settings']['capability']) ? sanitize_text_field($_POST['settings']['capability']) : 'upload_files', 
            'insert_mode' => isset($_POST['settings']['insert_mode']) ? sanitize_text_field($_POST['settings']['insert_mode']) : 'proxy', 
            'per_page'    => isset($_POST['settings']['per_page']) ? (int)$_POST['settings']['per_page'] : 60, 
            'max_sites'   => isset($_POST['settings']['max_sites']) ? (int)$_POST['settings']['max_sites'] : 5000
        ];
        update_site_option(self::OPTION_GENERAL, $settings);
        
    } elseif ($tab === 'photofall') {
        $sites = isset($_POST['enabled_sites']) ? array_map('intval', wp_unslash($_POST['enabled_sites'])) : [];
        update_site_option(self::OPTION_ENABLED_SITES, $sites);
        
    } elseif ($tab === 'buddypress') {
        $roles = isset($_POST['bp_allowed_roles']) ? array_map('sanitize_text_field', wp_unslash($_POST['bp_allowed_roles'])) : [];
        $bp = [
            'enabled' => isset($_POST['bp_indexing_enabled']) ? 1 : 0, 
            'roles'   => $roles
        ];
        update_site_option(self::OPTION_BP_SETTINGS, $bp);
    }
    
    wp_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'tab' => $tab, 'updated' => 'true'], network_admin_url('admin.php')));
    exit;
  }

  public static function ajax_index_batch() {
      check_ajax_referer('tbfnmi_indexer_run', 'nonce');
      if ( ! current_user_can('manage_network_options') ) wp_send_json_error('Unauthorized');
      
      $blog_id = isset($_POST['blog_id']) ? (int)$_POST['blog_id'] : 0;
      $start_after = isset($_POST['start_after']) ? (int)$_POST['start_after'] : 0;
      
      require_once TBFNMI_DIR . 'includes/indexer/class-tbfnmi-indexer.php';
      $indexer = new TBFNMI_Indexer();
      
      $result = $indexer->index_site_batch($blog_id, [
          'limit' => 100, 
          'start_after' => $start_after
      ]);
      
      if ( isset($result['error']) ) wp_send_json_error($result);
      wp_send_json_success($result);
  }
}