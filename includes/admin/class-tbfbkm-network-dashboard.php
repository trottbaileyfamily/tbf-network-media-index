<?php
/**
 * File: includes/admin/class-tbfbkm-network-dashboard.php
 * Version: 7.0.1.0
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Network_Dashboard {

  public static function init() {
    if ( is_multisite() ) {
        add_action('network_admin_menu', [__CLASS__, 'add_network_page']);
        add_action('network_admin_edit_tbfbkm_save_network', [__CLASS__, 'save_network_settings']);
    } else {
        add_action('admin_menu', [__CLASS__, 'add_network_page']);
        add_action('admin_post_tbfbkm_save_network', [__CLASS__, 'save_network_settings']);
    }
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function add_network_page() {
    if ( is_multisite() ) {
        add_submenu_page(
          'settings.php',
          'Big King Media Network',
          'Big King Media', 
          'manage_network_options',
          'tbfbkm-network',
          [__CLASS__, 'render_dashboard']
        );
    } else {
        add_submenu_page(
          'tools.php',
          'Big King Media',
          'Big King Media', 
          'manage_options',
          'tbfbkm-network',
          [__CLASS__, 'render_dashboard']
        );
    }
  }

  public static function enqueue_assets($hook) {
    if ( strpos($hook, 'tbfbkm-network') === false ) return;
    wp_enqueue_script('jquery');
  }

  public static function save_network_settings() {
      check_admin_referer('tbfbkm_network_options');
      
      if ( is_multisite() && !current_user_can('manage_network_options') ) wp_die('Access Denied');
      if ( !is_multisite() && !current_user_can('manage_options') ) wp_die('Access Denied');

      if ( is_multisite() ) {
          // 1. Master ID
          $master_id = isset($_POST['tbfbkm_master_id']) ? (int)$_POST['tbfbkm_master_id'] : get_main_site_id();
          update_site_option('tbfbkm_master_controller_id', $master_id);

          // 2. Active Sites
          $active_sites = isset($_POST['tbfbkm_active_sites']) ? array_map('intval', $_POST['tbfbkm_active_sites']) : [];
          update_site_option('tbfbkm_network_active_sites', $active_sites);

          // 3. Behavior
          $behavior = [
              'open_default' => isset($_POST['tbfbkm_global_open']) ? 1 : 0,
              'auto_start'   => isset($_POST['tbfbkm_global_autostart']) ? 1 : 0
          ];
          update_site_option('tbfbkm_network_behavior', $behavior);
      }

      // 4. Merged Legacy Settings (Who Can Browse / Insert Mode / Modal Limits)
      $legacy = get_site_option('tbfbkm_settings', []);
      $legacy['who_can_browse'] = sanitize_text_field($_POST['tbfbkm_who_browse'] ?? 'uploaders');
      $legacy['insert_mode']    = sanitize_text_field($_POST['tbfbkm_insert_mode'] ?? 'proxy');
      $legacy['per_page']       = isset($_POST['tbfbkm_per_page']) ? (int)$_POST['tbfbkm_per_page'] : 60;
      $legacy['max_sites']      = isset($_POST['tbfbkm_max_sites']) ? (int)$_POST['tbfbkm_max_sites'] : 5000;
      update_site_option('tbfbkm_settings', $legacy);

      $redirect_url = is_multisite() ? network_admin_url('settings.php?page=tbfbkm-network&updated=true') : admin_url('tools.php?page=tbfbkm-network&updated=true');
      wp_redirect($redirect_url);
      exit;
  }

  public static function render_dashboard() {
    global $wpdb;
    $index_table = $wpdb->base_prefix . 'tbfbkm_index';
    
    // Safe count query
    $total_media = 0;
    if ( $wpdb->get_var("SHOW TABLES LIKE '{$index_table}'") ) {
        $total_media = $wpdb->get_var("SELECT COUNT(*) FROM $index_table");
    }
    
    $sites = is_multisite() ? get_sites(['number' => 500, 'public' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0]) : [];
    $master_id = (int)get_site_option('tbfbkm_master_controller_id', is_multisite() ? get_main_site_id() : 1);
    $active_sites = get_site_option('tbfbkm_network_active_sites', []);
    if(!is_array($active_sites)) $active_sites = [];
    
    $behavior = get_site_option('tbfbkm_network_behavior', ['open_default' => 0, 'auto_start' => 0]);
    
    $legacy = get_site_option('tbfbkm_settings', []);
    $who = $legacy['who_can_browse'] ?? 'uploaders';
    $insert = $legacy['insert_mode'] ?? 'proxy';
    $per_page = $legacy['per_page'] ?? 60;
    $max_sites = $legacy['max_sites'] ?? 5000;
    ?>
    <div class="wrap">
      <h1><?php echo is_multisite() ? 'Big King Media Command Center' : 'Big King Media Settings'; ?></h1>
      
      <?php if ( isset($_GET['updated']) ): ?>
      <div id="message" class="updated notice is-dismissible"><p>Settings saved successfully.</p></div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url(admin_url(is_multisite() ? 'edit.php?action=tbfbkm_save_network' : 'admin-post.php?action=tbfbkm_save_network')); ?>">
          <?php wp_nonce_field('tbfbkm_network_options'); ?>
          
          <?php if ( is_multisite() ): ?>
          <div class="card" style="max-width:800px; padding:20px; margin-top:20px;">
              <h2>Network Configuration</h2>

              <div style="background: #eef5fa; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                  <label for="tbfbkm_master_id" style="font-weight:bold; font-size:14px; display:block; margin-bottom:5px;">Master Control Site</label>
                  <p style="margin-top:0; color:#555; font-size:13px; margin-bottom:10px;">Who controls the <strong>Princess Keilah Studio</strong> playlist?</p>
                  
                  <select name="tbfbkm_master_id" id="tbfbkm_master_id" style="width:100%; max-width:400px;">
                      <option value="-1" <?php selected(-1, $master_id); ?>>--- No Master (Each Site Independent) ---</option>
                      <?php foreach($sites as $s): $bid = (int)$s->blog_id; ?>
                          <option value="<?php echo $bid; ?>" <?php selected($bid, $master_id); ?>>
                              <?php echo esc_html(get_blog_option($bid, 'blogname')); ?> (ID: <?php echo $bid; ?>)
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <table class="form-table">
                  <tr valign="top">
                      <th scope="row">Permission Level</th>
                      <td>
                          <select name="tbfbkm_who_browse">
                              <option value="uploaders" <?php selected('uploaders', $who); ?>>Uploaders & Admins</option>
                              <option value="admins" <?php selected('admins', $who); ?>>Admins Only</option>
                          </select>
                          <p class="description">Who can access the global media library?</p>
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">Insertion Mode</th>
                      <td>
                          <select name="tbfbkm_insert_mode">
                              <option value="proxy" <?php selected('proxy', $insert); ?>>Smart Proxy (Recommended)</option>
                              <option value="url" <?php selected('url', $insert); ?>>Direct URL</option>
                          </select>
                          <p class="description">How files are inserted into posts.</p>
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">Modal Items Per Page</th>
                      <td>
                          <input type="number" name="tbfbkm_per_page" value="<?php echo esc_attr($per_page); ?>" min="10" max="200" />
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">Max Sites to Search</th>
                      <td>
                          <input type="number" name="tbfbkm_max_sites" value="<?php echo esc_attr($max_sites); ?>" min="1" max="10000" />
                      </td>
                  </tr>
              </table>

              <hr>

              <h3>Global Activation (Princess Keilah Gadget)</h3>
              <p>Select which sites should display the floating gadget.</p>
              
              <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9; margin: 15px 0;">
                  <label style="font-weight:bold; display:block; margin-bottom:10px; border-bottom:1px solid #ccc; padding-bottom:5px;">
                      <input type="checkbox" id="tbf_select_all"> Select All Sites
                  </label>
                  
                  <?php foreach ( $sites as $s ): ?>
                      <?php $bid = (int)$s->blog_id; ?>
                      <label style="display:block; padding: 4px 0; border-bottom:1px solid #eee;">
                          <input type="checkbox" name="tbfbkm_active_sites[]" value="<?php echo $bid; ?>" <?php checked(in_array($bid, $active_sites)); ?> class="tbf_site_cb"> 
                          <strong><?php echo esc_html(get_blog_option($bid, 'blogname')); ?></strong> 
                          <span style="color:#888; font-size:12px;">(ID: <?php echo $bid; ?>)</span> 
                      </label>
                  <?php endforeach; ?>
              </div>

              <h3>Gadget Behavior</h3>
              <label style="margin-right: 20px; font-weight: 500;">
                  <input type="checkbox" name="tbfbkm_global_open" value="1" <?php checked(1, $behavior['open_default']); ?>> 
                  Open by Default
              </label>
              <label style="font-weight: 500;">
                  <input type="checkbox" name="tbfbkm_global_autostart" value="1" <?php checked(1, $behavior['auto_start']); ?>> 
                  Auto-Start Audio
              </label>

              <p class="submit" style="margin-top: 20px;">
                  <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Network Settings">
              </p>
          </div>
          <?php else: ?>
          <div class="card" style="max-width:800px; padding:20px; margin-top:20px;">
              <h2>Library Settings</h2>
              <table class="form-table">
                  <tr valign="top">
                      <th scope="row">Permission Level</th>
                      <td>
                          <select name="tbfbkm_who_browse">
                              <option value="uploaders" <?php selected('uploaders', $who); ?>>Uploaders & Admins</option>
                              <option value="admins" <?php selected('admins', $who); ?>>Admins Only</option>
                          </select>
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">Insertion Mode</th>
                      <td>
                          <select name="tbfbkm_insert_mode">
                              <option value="proxy" <?php selected('proxy', $insert); ?>>Smart Proxy (Recommended)</option>
                              <option value="url" <?php selected('url', $insert); ?>>Direct URL</option>
                          </select>
                          <p class="description">How files are inserted into posts when selected from Big King Media.</p>
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">Modal Items Per Page</th>
                      <td>
                          <input type="number" name="tbfbkm_per_page" value="<?php echo esc_attr($per_page); ?>" min="10" max="200" />
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">Max Sites to Search</th>
                      <td>
                          <input type="number" name="tbfbkm_max_sites" value="<?php echo esc_attr($max_sites); ?>" min="1" max="10000" />
                      </td>
                  </tr>
              </table>
              <p class="submit" style="margin-top: 20px;">
                  <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
              </p>
          </div>
          <?php endif; ?>
      </form>

      <div class="card" style="max-width:800px; padding:20px; margin-top:20px; border-left:4px solid #72aee6;">
          <?php if ( is_multisite() ): ?>
              <h2>Network Media Indexer</h2>
              <p>Scan all network sites to pull images and audio into the central database. <strong>Indexed Assets:</strong> <?php echo number_format($total_media); ?> items.</p>
              <?php $btn_text = "Run Big King Indexer"; $clear_text = "Wipe Index"; ?>
          <?php else: ?>
              <h2>Deep Interlinking Tool (SEO Sync)</h2>
              <p>Supercharge your Media SEO by scanning your posts, pages, and Elementor layouts to build deep <strong>"Featured In"</strong> backlinks for your images and videos.</p>
              <?php $btn_text = "Run SEO Interlink Scan"; $clear_text = "Clear SEO Data"; ?>
          <?php endif; ?>
          
          <div id="tbfbkm-progress-wrap" style="display:none; margin-bottom:15px; background: #f0f0f1; padding: 10px; border-radius: 4px;">
              <div style="background:#ddd; height:20px; border-radius:10px; overflow:hidden; margin-bottom: 5px;">
                  <div id="tbfbkm-bar" style="width:0%; background:#2271b1; height:100%; transition:width 0.2s;"></div>
              </div>
              <p id="tbfbkm-status" style="margin:0; font-weight:bold; color:#2271b1;">Initializing...</p>
          </div>

          <button id="tbfbkm-run-indexer" class="button button-primary"><?php echo esc_html($btn_text); ?></button>
          <button id="tbfbkm-wipe-index" class="button button-link-delete" style="float:right;"><?php echo esc_html($clear_text); ?></button>
      </div>

      <script>
      jQuery(document).ready(function($){
          $('#tbf_select_all').change(function(){
              $('.tbf_site_cb').prop('checked', $(this).is(':checked'));
          });

          $('#tbfbkm-run-indexer').click(function(){
              $(this).prop('disabled', true).text('Processing...');
              $('#tbfbkm-progress-wrap').slideDown();
              processBatch(1, 0);
          });

          function processBatch(step, offset) {
              $.post(ajaxurl, { action: 'tbfbkm_process_batch', step: step, offset: offset }, function(res) {
                  if(res.success) {
                      $('#tbfbkm-bar').css('width', res.data.progress + '%');
                      $('#tbfbkm-status').text(res.data.message);
                      if(!res.data.done) {
                          processBatch(res.data.step, res.data.offset);
                      } else {
                          $('#tbfbkm-bar').css('width', '100%');
                          $('#tbfbkm-status').text('Processing Complete! Refreshing...');
                          setTimeout(function(){ location.reload(); }, 1500);
                      }
                  } else {
                      $('#tbfbkm-status').text('Error: ' + res.data.message);
                      $('#tbfbkm-run-indexer').prop('disabled', false).text('Retry Process');
                  }
              }).fail(function() {
                  $('#tbfbkm-status').text('Server Error. Check logs.');
                  $('#tbfbkm-run-indexer').prop('disabled', false).text('Retry Process');
              });
          }

          $('#tbfbkm-wipe-index').click(function(){
              var confirmMsg = '<?php echo is_multisite() ? 'Delete entire network media index?' : 'Clear all SEO Interlink mapping data?'; ?>';
              if(!confirm(confirmMsg)) return;
              $(this).text('Clearing...');
              $.post(ajaxurl, { action: 'tbfbkm_wipe_index', nonce: '<?php echo wp_create_nonce('tbfbkm_wipe_nonce'); ?>' }, function(res){
                  alert(res.data.message);
                  location.reload();
              });
          });
      });
      </script>
    </div>
    <?php
  }
}