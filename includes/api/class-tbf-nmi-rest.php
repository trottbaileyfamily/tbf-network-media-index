<?php
/**
 * File: includes/api/class-tbf-nmi-rest.php
 * Version: 4.0.3
 *
 * Public REST endpoints for Photofall infinite scroll + item detail.
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_REST {

  public static function register_routes() {

    register_rest_route('tbf-photofall/v1', '/list', [
      'methods'  => 'GET',
      'permission_callback' => [__CLASS__, 'perm_public'],
      'callback' => [__CLASS__, 'list'],
      'args' => [
        'route'     => ['type'=>'string',  'required'=>false],
        'page'      => ['type'=>'integer', 'required'=>false],
        'page_size' => ['type'=>'integer', 'required'=>false],
        'blog_id'   => ['type'=>'integer', 'required'=>false],
        'year'      => ['type'=>'integer', 'required'=>false],
        'month'     => ['type'=>'integer', 'required'=>false],
        'tag'       => ['type'=>'string',  'required'=>false],
        'q'         => ['type'=>'string',  'required'=>false],
      ],
    ]);

    // Supports either:
    //  - /item?blog_id=3&attachment_id=123
    //  - /item?id=9999   (network item id, if your query supports it)
    register_rest_route('tbf-photofall/v1', '/item', [
      'methods'  => 'GET',
      'permission_callback' => [__CLASS__, 'perm_public'],
      'callback' => [__CLASS__, 'item'],
      'args' => [
        'id'            => ['type'=>'integer', 'required'=>false],
        'blog_id'       => ['type'=>'integer', 'required'=>false],
        'attachment_id' => ['type'=>'integer', 'required'=>false],
      ],
    ]);
  }

  public static function perm_public() {
    $settings = class_exists('TBF_NMI_Plugin') ? TBF_NMI_Plugin::instance()->get_settings() : [];
    if ( empty($settings['photofall_enabled']) ) return false;
    if ( ! empty($settings['photofall_public']) ) return true;
    return is_user_logged_in();
  }

  public static function list(WP_REST_Request $req) {
    $q = new TBF_NMI_PhotoFall_Query();

    $page = max(1, (int)($req->get_param('page') ?: 1));
    $pageSize = (int)($req->get_param('page_size') ?: 24);
    $pageSize = max(6, min(200, $pageSize));

    $res = $q->list([
      'route'     => (string)($req->get_param('route') ?: 'root'),
      'page'      => $page,
      'page_size' => $pageSize,
      'blog_id'   => (int)($req->get_param('blog_id') ?: 0),
      'year'      => (int)($req->get_param('year') ?: 0),
      'month'     => (int)($req->get_param('month') ?: 0),
      'tag'       => (string)($req->get_param('tag') ?: ''),
      'q'         => (string)($req->get_param('q') ?: ''),
    ]);

    // Normalize response keys (prevents confusing mismatches like requesting 5 but seeing 24).
    if (is_array($res)) {
      $res['page'] = (int)($res['page'] ?? $page);
      $res['page_size'] = (int)($res['page_size'] ?? $pageSize);
      if (!isset($res['items']) || !is_array($res['items'])) $res['items'] = [];
      if (!isset($res['total'])) $res['total'] = 0;
      if (!isset($res['has_more'])) $res['has_more'] = false;
    }

    return rest_ensure_response($res);
  }

  public static function item(WP_REST_Request $req) {
    $q = new TBF_NMI_PhotoFall_Query();

    $id = (int)($req->get_param('id') ?: 0);
    $blogId = (int)($req->get_param('blog_id') ?: 0);
    $attId  = (int)($req->get_param('attachment_id') ?: 0);

    $item = null;

    // Preferred: network item id (if your query supports it)
    if ($id > 0 && method_exists($q, 'get_item_by_id')) {
      $item = $q->get_item_by_id($id);
    }

    // Fallback: blog_id + attachment_id
    if (!$item && $blogId > 0 && $attId > 0) {
      $item = $q->get_item($blogId, $attId);
    }

    // Last-chance fallback: if someone passed the "attachment_id" but it was actually a network id
    if (!$item && $attId > 0 && method_exists($q, 'get_item_by_id')) {
      $item = $q->get_item_by_id($attId);
    }

    if (!$item) {
      return new WP_REST_Response(['message'=>'Not found'], 404);
    }

    return rest_ensure_response($item);
  }
}
