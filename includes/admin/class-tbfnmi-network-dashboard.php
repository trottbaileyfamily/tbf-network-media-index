<?php
/**
 * File: includes/admin/class-tbfnmi-network-dashboard.php
 * Version: 6.9.6.1 (Consolidated Big King Media Dashboard)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Network_Dashboard {

  public static function init() {
    add_action('network_admin_menu', [__CLASS__, 'add_network_page']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    add_action('network_admin_edit_tbfnmi_save_network', [__CLASS__, 'save_network_settings']);
  }

  public static function add_network_page() {
    // MOVED: To Submenu of Settings (Compliance with WP High Position Rule)
    add_submenu_page(
      'settings.php',
      'Big King Media',
      'Big King Media',
      'manage_network_options',
      'tbfnmi-network',
      [__CLASS__, 'render_dashboard']
    );
  }

  public static function enqueue_assets($hook) {
    if ( strpos($hook, 'tbfnmi-network') === false ) return;
    wp_enqueue_script('jquery');
  }

  public static function save_network_settings() {
      check_admin_referer('tbfnmi_network_options');
      
      if ( ! current_user_can('manage_network_options') ) wp_die('Access Denied');

      // 1. Master ID
      $master_id = isset($_POST['tbfnmi_master_id']) ? (int)$_POST['tbfnmi_master_id'] : get_main_site_id();
      update_site_option('tbfnmi_master_controller_id', $master_id);

      // 2. Active Sites
      $active_sites = isset($_POST['tbfnmi_active_sites']) ? array_map('intval', $_POST['tbfnmi_active_sites']) : [];
      update_site_option('tbfnmi_network_active_sites', $active_sites);

      // 3. Behavior
      $behavior = [
          'open_default' => isset($_POST['tbfnmi_global_open']) ? 1 : 0,
          'auto_start'   => isset($_POST['tbfnmi_global_autostart']) ? 1 : 0
      ];
      update_site_option('tbfnmi_network_behavior', $behavior);

      // 4. Merged Legacy Settings (Who Can Browse / Insert Mode)
      $legacy = get_site_option('tbfnmi_settings', []);
      $legacy['who_can_browse'] = sanitize_text_field($_POST['tbfnmi_who_browse'] ?? 'uploaders');
      $legacy['insert_mode']    = sanitize_text_field($_POST['tbfnmi_insert_mode'] ?? 'proxy');
      update_site_option('tbfnmi_settings', $legacy);

      wp_redirect(add_query_arg(['page' => 'tbfnmi-network', 'updated' => 'true'], network_admin_url('admin.php')));
      exit;
  }

  public static function render_dashboard() {
    global $wpdb;
    $index_table = $wpdb->base_prefix . 'tbfnmi_index';
    $total_media = $wpdb->get_var("SELECT COUNT(*) FROM $index_table");
    
    $sites = get_sites(['number' => 500, 'public' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0]);
    $master_id = (int)get_site_option('tbfnmi_master_controller_id', get_main_site_id());
    $active_sites = get_site_option('tbfnmi_network_active_sites', []);
    if(!is_array($active_sites)) $active_sites = [];
    
    $behavior = get_site_option('tbfnmi_network_behavior', ['open_default' => 0, 'auto_start' => 0]);
    
    // Legacy merged options
    $legacy = get_site_option('tbfnmi_settings', []);
    $who = $legacy['who_can_browse'] ?? 'uploaders';
    $insert = $legacy['insert_mode'] ?? 'proxy';
    ?>
    <div class="wrap">
      <h1>Big King Media Command Center</h1>
      
      <?php if ( isset($_GET['updated']) ): ?>
      <div id="message" class="updated notice is-dismissible"><p>Network settings saved successfully.</p></div>
      <?php endif; ?>

      <form method="post" action="edit.php?action=tbfnmi_save_network">
          <?php wp_nonce_field('tbfnmi_network_options'); ?>
          
          <div class="card" style="max-width:800px; padding:20px; margin-top:20px;">
              <h2>Network Configuration</h2>

              <div style="background: #eef5fa; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px;">
                  <label for="tbfnmi_master_id" style="font-weight:bold; font-size:14px; display:block; margin-bottom:5px;">Master Control Site</label>
                  <p style="margin-top:0; color:#555; font-size:13px; margin-bottom:10px;">Who controls the <strong>Princess Keilah Studio</strong> playlist?</p>
                  
                  <select name="tbfnmi_master_id" id="tbfnmi_master_id" style="width:100%; max-width:400px;">
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
                          <select name="tbfnmi_who_browse">
                              <option value="uploaders" <?php selected('uploaders', $who); ?>>Uploaders & Admins</option>
                              <option value="admins" <?php selected('admins', $who); ?>>Admins Only</option>
                          </select>
                          <p class="description">Who can access the global media library?</p>
                      </td>
                  </tr>
                  <tr valign="top">
                      <th scope="row">Insertion Mode</th>
                      <td>
                          <select name="tbfnmi_insert_mode">
                              <option value="proxy" <?php selected('proxy', $insert); ?>>Smart Proxy (Recommended)</option>
                              <option value="url" <?php selected('url', $insert); ?>>Direct URL</option>
                          </select>
                          <p class="description">How files are inserted into posts.</p>
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
                          <input type="checkbox" name="tbfnmi_active_sites[]" value="<?php echo $bid; ?>" <?php checked(in_array($bid, $active_sites)); ?> class="tbf_site_cb"> 
                          <strong><?php echo esc_html(get_blog_option($bid, 'blogname')); ?></strong> 
                          <span style="color:#888; font-size:12px;">(ID: <?php echo $bid; ?>)</span> 
                      </label>
                  <?php endforeach; ?>
              </div>

              <h3>Gadget Behavior</h3>
              <label style="margin-right: 20px; font-weight: 500;">
                  <input type="checkbox" name="tbfnmi_global_open" value="1" <?php checked(1, $behavior['open_default']); ?>> 
                  Open by Default
              </label>
              <label style="font-weight: 500;">
                  <input type="checkbox" name="tbfnmi_global_autostart" value="1" <?php checked(1, $behavior['auto_start']); ?>> 
                  Auto-Start Audio
              </label>

              <p class="submit" style="margin-top: 20px;">
                  <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Network Settings">
              </p>
          </div>
      </form>

      <div class="card" style="max-width:800px; padding:20px; margin-top:20px; border-left:4px solid #72aee6;">
          <h2>Network Indexer</h2>
          <p><strong>Indexed Assets:</strong> <?php echo number_format($total_media); ?> items.</p>
          
          <div id="tbfnmi-progress-wrap" style="display:none; margin-bottom:15px; background: #f0f0f1; padding: 10px; border-radius: 4px;">
              <div style="background:#ddd; height:20px; border-radius:10px; overflow:hidden; margin-bottom: 5px;">
                  <div id="tbfnmi-bar" style="width:0%; background:#2271b1; height:100%; transition:width 0.2s;"></div>
              </div>
              <p id="tbfnmi-status" style="margin:0; font-weight:bold; color:#2271b1;">Initializing...</p>
          </div>

          <button id="tbfnmi-run-indexer" class="button button-primary">Run Big King Indexer</button>
          <button id="tbfnmi-wipe-index" class="button button-link-delete" style="float:right;">Wipe Index</button>
      </div>

      <script>
      jQuery(document).ready(function($){
          $('#tbf_select_all').change(function(){
              $('.tbf_site_cb').prop('checked', $(this).is(':checked'));
          });

          $('#tbfnmi-run-indexer').click(function(){
              $(this).prop('disabled', true).text('Indexing...');
              $('#tbfnmi-progress-wrap').slideDown();
              processBatch(1, 0);
          });

          function processBatch(step, offset) {
              $.post(ajaxurl, { action: 'tbfnmi_process_batch', step: step, offset: offset }, function(res) {
                  if(res.success) {
                      $('#tbfnmi-bar').css('width', res.data.progress + '%');
                      $('#tbfnmi-status').text(res.data.message);
                      if(!res.data.done) {
                          processBatch(res.data.step, res.data.offset);
                      } else {
                          $('#tbfnmi-bar').css('width', '100%');
                          $('#tbfnmi-status').text('Indexing Complete! Refreshing...');
                          setTimeout(function(){ location.reload(); }, 1500);
                      }
                  } else {
                      $('#tbfnmi-status').text('Error: ' + res.data.message);
                      $('#tbfnmi-run-indexer').prop('disabled', false).text('Retry Indexer');
                  }
              }).fail(function() {
                  $('#tbfnmi-status').text('Server Error. Check logs.');
                  $('#tbfnmi-run-indexer').prop('disabled', false).text('Retry Indexer');
              });
          }

          $('#tbfnmi-wipe-index').click(function(){
              if(!confirm('Delete entire index?')) return;
              $(this).text('Wiping...');
              $.post(ajaxurl, { action: 'tbfnmi_wipe_index', nonce: '<?php echo wp_create_nonce('tbfnmi_wipe_nonce'); ?>' }, function(res){
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