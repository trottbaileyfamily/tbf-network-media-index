<?php
/*
 * File: includes/class-tbf-nmi-admin.php
 * Version: 1.0.8
 */

if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_Admin {

  public static function init() {
    add_action('network_admin_menu', [__CLASS__, 'network_menu']);
    add_action('admin_menu', [__CLASS__, 'single_menu']);
  }

  public static function network_menu() {
    if ( ! is_multisite() ) return;

    add_submenu_page(
      'settings.php',
      __('Network Media Index', 'tbf-nmi'),
      __('Network Media Index', 'tbf-nmi'),
      'manage_network_options',
      'tbf-nmi-settings',
      [__CLASS__, 'render']
    );
  }

  public static function single_menu() {
    if ( is_multisite() ) return;

    add_options_page(
      __('Network Media Index', 'tbf-nmi'),
      __('Network Media Index', 'tbf-nmi'),
      'manage_options',
      'tbf-nmi-settings',
      [__CLASS__, 'render']
    );
  }

  public static function render() {
    if ( is_multisite() ) {
      if ( ! current_user_can('manage_network_options') ) wp_die('Forbidden');
      $saved = get_site_option('tbf_nmi_settings', []);
    } else {
      if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
      $saved = get_option('tbf_nmi_settings', []);
    }

    $s = array_merge(TBF_Network_Media_Index::defaults(), is_array($saved) ? $saved : []);

    if ( isset($_POST['tbf_nmi_save']) && check_admin_referer('tbf_nmi_save_settings') ) {
      $s['who_can_browse'] = in_array($_POST['who_can_browse'] ?? 'uploaders', ['uploaders','admins','superadmins'], true)
        ? sanitize_text_field($_POST['who_can_browse'])
        : 'uploaders';

      $s['insert_mode'] = in_array($_POST['insert_mode'] ?? 'proxy', ['proxy','url'], true)
        ? sanitize_text_field($_POST['insert_mode'])
        : 'proxy';

      $s['per_page']  = max(10, min(200, (int)($_POST['per_page'] ?? 60)));
      $s['max_sites'] = max(1, min(20000, (int)($_POST['max_sites'] ?? 5000)));

      if ( is_multisite() ) {
        update_site_option('tbf_nmi_settings', $s);
      } else {
        update_option('tbf_nmi_settings', $s);
      }

      echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }

    ?>
    <div class="wrap">
      <h1>Network Media Index (No Copy)</h1>
      <p class="description">
        This plugin lets editors browse media from any site in the network and insert it without copying or moving files.
        For Featured Image support, it can create a DB-only proxy attachment on the current site.
      </p>

      <form method="post">
        <?php wp_nonce_field('tbf_nmi_save_settings'); ?>

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">Who can browse Network Media</th>
            <td>
              <select name="who_can_browse">
                <option value="uploaders" <?php selected($s['who_can_browse'], 'uploaders'); ?>>Users who can upload files (recommended)</option>
                <option value="admins" <?php selected($s['who_can_browse'], 'admins'); ?>>Admins only</option>
                <option value="superadmins" <?php selected($s['who_can_browse'], 'superadmins'); ?>>Super admins only</option>
              </select>
            </td>
          </tr>

          <tr>
            <th scope="row">Insert mode</th>
            <td>
              <select name="insert_mode">
                <option value="proxy" <?php selected($s['insert_mode'], 'proxy'); ?>>Proxy attachment (DB-only, supports Featured Image)</option>
                <option value="url" <?php selected($s['insert_mode'], 'url'); ?>>Insert URL only (no local attachment record)</option>
              </select>
            </td>
          </tr>

          <tr>
            <th scope="row">Items per page</th>
            <td><input type="number" name="per_page" value="<?php echo esc_attr((int)$s['per_page']); ?>" min="10" max="200"></td>
          </tr>

          <tr>
            <th scope="row">Max sites scanned</th>
            <td><input type="number" name="max_sites" value="<?php echo esc_attr((int)$s['max_sites']); ?>" min="1" max="20000"></td>
          </tr>
        </table>

        <p>
          <button class="button button-primary" name="tbf_nmi_save" value="1">Save</button>
        </p>
      </form>
    </div>
    <?php
  }
}