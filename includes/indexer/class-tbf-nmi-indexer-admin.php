<?php
/**
 * File: includes/indexer/class-tbf-nmi-indexer-admin.php
 * Version: 4.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Indexer_Admin {
  public static function init() {
    if ( is_multisite() ) add_action('network_admin_menu', [__CLASS__, 'menu']);
    else add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
  }

  public static function menu() {
    $cap = is_multisite() ? 'manage_network_options' : 'manage_options';
    $parent = is_multisite() ? 'settings.php' : 'options-general.php';
    add_submenu_page($parent, 'Photofall Index', 'Photofall Index', $cap, 'tbf-photofall-index', [__CLASS__, 'render']);
  }

  public static function assets($hook) {
    if ( strpos((string)$hook, 'tbf-photofall-index') === false ) return;
    wp_enqueue_style('tbf-nmi-indexer-admin', TBF_NMI_URL . 'assets/css/indexer-admin.css', [], TBF_NMI_VER);
    wp_enqueue_script('tbf-nmi-indexer-admin', TBF_NMI_URL . 'assets/js/indexer-admin.js', ['jquery'], TBF_NMI_VER, true);
    wp_localize_script('tbf-nmi-indexer-admin', 'TBF_NMI_INDEXER', [
      'ajax' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('tbf_nmi_indexer_nonce'),
    ]);
  }

  public static function get_state() {
    $default = [
      'running' => 0,
      'current_blog_id' => 0,
      'cursor' => 0,
      'total_indexed' => 0,
      'updated_at' => '',
      'site_mode' => 0,
      'site_only' => 0,
    ];
    $opt = is_multisite() ? get_site_option('tbf_nmi_indexer_state', []) : get_option('tbf_nmi_indexer_state', []);
    if ( ! is_array($opt) ) $opt = [];
    return array_merge($default, $opt);
  }

  public static function save_state(array $state) {
    $state['updated_at'] = gmdate('Y-m-d H:i:s');
    if ( is_multisite() ) update_site_option('tbf_nmi_indexer_state', $state);
    else update_option('tbf_nmi_indexer_state', $state);
  }

  public static function reset_state() {
    self::save_state([
      'running' => 0,
      'current_blog_id' => 0,
      'cursor' => 0,
      'total_indexed' => 0,
      'updated_at' => gmdate('Y-m-d H:i:s'),
      'site_mode' => 0,
      'site_only' => 0,
    ]);
  }

  public static function render() {
    if ( ! current_user_can(is_multisite() ? 'manage_network_options' : 'manage_options') ) wp_die('Permission denied');
    $state = self::get_state();
    $sites = is_multisite() ? get_sites(['number'=>5000]) : [];
    echo '<div class="wrap tbf-nmi-indexer-wrap">';
    echo '<h1>Photofall Index</h1>';
    echo '<p>Build the fast media index used by <code>/photo/</code> and sitemaps.</p>';

    echo '<div class="tbf-nmi-indexer-card">';
    echo '<h2>Status</h2>';
    echo '<div class="tbf-nmi-indexer-status">';
    echo '<p><strong>Running:</strong> <span class="tbf-running">' . (!empty($state['running']) ? 'Yes' : 'No') . '</span></p>';
    echo '<p><strong>Current site:</strong> <span class="tbf-current-site">' . esc_html((string)($state['current_blog_id'] ?? '')) . '</span></p>';
    echo '<p><strong>Cursor:</strong> <span class="tbf-cursor">' . esc_html((string)($state['cursor'] ?? '0')) . '</span></p>';
    echo '<p><strong>Total indexed (this run):</strong> <span class="tbf-total-indexed">' . esc_html((string)($state['total_indexed'] ?? '0')) . '</span></p>';
    echo '<p><strong>Updated:</strong> <span class="tbf-updated">' . esc_html((string)($state['updated_at'] ?? '')) . '</span></p>';
    echo '</div><hr/>';

    echo '<h2>Run Settings</h2>';
    echo '<table class="form-table"><tbody>';

    echo '<tr><th scope="row"><label for="tbf-limit">Batch size</label></th><td>';
    echo '<input type="number" id="tbf-limit" value="500" min="50" max="2000" />';
    echo '<p class="description">500 is safe. Higher = faster, more load.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Media types</th><td>';
    echo '<label><input type="checkbox" id="tbf-images" checked /> Images</label> &nbsp; ';
    echo '<label><input type="checkbox" id="tbf-videos" checked /> Videos</label>';
    echo '</td></tr>';

    if ( is_multisite() ) {
      echo '<tr><th scope="row"><label for="tbf-site">Only index one site (optional)</label></th><td>';
      echo '<select id="tbf-site"><option value="">All sites</option>';
      foreach ($sites as $s) {
        $bid = (int)$s->blog_id;
        $name = get_blog_option($bid, 'blogname');
        echo '<option value="' . esc_attr($bid) . '">' . esc_html($name) . ' (ID ' . $bid . ')</option>';
      }
      echo '</select></td></tr>';
    }

    echo '</tbody></table>';

    echo '<div class="tbf-nmi-indexer-actions">';
    echo '<button type="button" class="button button-primary" id="tbf-start-index">Start / Resume</button> ';
    echo '<button type="button" class="button" id="tbf-stop-index">Stop</button> ';
    echo '<button type="button" class="button" id="tbf-reset-index">Reset Progress</button>';
    echo '</div>';

    echo '<hr/><h2>Live Log</h2>';
    echo '<pre class="tbf-nmi-indexer-log" style="max-height:280px; overflow:auto; background:#111; color:#c7f0d8; padding:12px; border-radius:8px;"></pre>';
    echo '</div></div>';
  }
}
