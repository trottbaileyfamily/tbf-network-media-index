<?php
/**
 * File: includes/admin/class-tbfnmi-network-dashboard.php
 * Version: 6.7.7 (Added Wipe Index Capability)
 */

if ( ! defined('ABSPATH') ) exit;

class TBFNMI_Network_Dashboard {

  public static function init() {
    if ( is_multisite() ) {
        add_action('network_admin_menu', [__CLASS__, 'menu']);
    } else {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }
  }

  public static function menu() {
    $cap = is_multisite() ? 'manage_network_options' : 'manage_options';
    $slug = is_multisite() ? 'tbf-network-media' : 'tbf-media-indexer';
    
    add_menu_page(
      'Network Media',
      'TBF Network Media',
      $cap,
      $slug,
      [__CLASS__, 'render'],
      'dashicons-format-gallery',
      25
    );
  }

  public static function render() {
    wp_enqueue_script('tbfnmi-indexer', TBFNMI_URL . 'assets/js/indexer.js', ['jquery'], TBFNMI_VER, true);
    
    // Pass NONCE for Wipe Action
    wp_localize_script('tbfnmi-indexer', 'tbfnmi_idx_data', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('tbfnmi_indexer_nonce'),
        'wipe_nonce' => wp_create_nonce('tbfnmi_wipe_nonce') // NEW
    ]);

    ?>
    <div class="wrap">
      <h1>TBF Network Media Indexer</h1>
      
      <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>Global Network Indexing</h2>
        <p>This tool scans every site in your network and indexes images, videos, and audio into the central database. It does not copy files.</p>
        
        <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <label style="font-weight:bold;">Batch Size:</label>
            <input type="number" id="tbfnmi-batch-size" value="50" min="5" max="500" style="width:80px;">
            <p class="description">Number of items to process per request. Lower this if the indexer stalls.</p>
        </div>

        <button id="tbfnmi-start-index" class="button button-primary button-hero">Start Full Network Index</button>
        <button id="tbfnmi-stop-index" class="button" style="display:none; margin-left: 10px;">Pause Indexing</button>
        
        <button id="tbfnmi-wipe-index" class="button button-link-delete" style="float:right; text-decoration:none; border:1px solid #d63638; color:#d63638;">Wipe Index & Reset</button>

        <div id="tbfnmi-progress-wrap" style="margin-top: 20px; display: none;">
            <div style="background: #fff; border: 1px solid #ccc; height: 20px; border-radius: 10px; overflow: hidden;">
                <div id="tbfnmi-progress-bar" style="width: 0%; background: #2271b1; height: 100%; transition: width 0.2s;"></div>
            </div>
            <div id="tbfnmi-status" style="margin-top: 10px; font-weight: bold; color: #444;">Ready to start.</div>
            <div id="tbfnmi-log" style="margin-top: 10px; font-size: 11px; color: #666; max-height: 150px; overflow-y: auto; background: #fafafa; padding: 10px; border: 1px solid #eee;"></div>
        </div>
      </div>
      
      <script>
      jQuery(document).ready(function($){
          $('#tbfnmi-wipe-index').click(function(){
              if(!confirm('WARNING: This will empty the entire network media index database. You will need to re-run the Full Indexer to see images again. Are you sure?')) return;
              
              $(this).text('Wiping...').prop('disabled', true);
              
              $.post(tbfnmi_idx_data.ajax_url, {
                  action: 'tbfnmi_wipe_index',
                  nonce: tbfnmi_idx_data.wipe_nonce
              }, function(res) {
                  if(res.success) {
                      alert('Index wiped successfully. Please run the Full Indexer now.');
                      location.reload();
                  } else {
                      alert('Error: ' + (res.data ? res.data.message : 'Unknown error'));
                      $('#tbfnmi-wipe-index').text('Wipe Index & Reset').prop('disabled', false);
                  }
              });
          });
      });
      </script>
    </div>
    <?php
  }
}