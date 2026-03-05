<?php
/**
 * File: includes/api/class-tbfbkm-rest.php
 * Version: 4.0.0
 *
 * Public REST endpoints for Photofall infinite scroll.
 */
if ( ! defined('ABSPATH') ) exit;

class TBFBKM_REST {

  public static function register_routes() {
    register_rest_route('tbf-photofall/v1', '/list', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_public'],
      'callback' => [__CLASS__, 'list'],
      'args' => [
        'route' => ['type'=>'string'],
        'page' => ['type'=>'integer'],
        'page_size' => ['type'=>'integer'],
        'blog_id' => ['type'=>'integer'],
        'year' => ['type'=>'integer'],
        'month' => ['type'=>'integer'],
        'tag' => ['type'=>'string'],
        'q' => ['type'=>'string'],
      ],
    ]);

    register_rest_route('tbf-photofall/v1', '/item', [
      'methods' => 'GET',
      'permission_callback' => [__CLASS__, 'perm_public'],
      'callback' => [__CLASS__, 'item'],
      'args' => [
        'blog_id' => ['type'=>'integer', 'required'=>true],
        'attachment_id' => ['type'=>'integer', 'required'=>true],
      ],
    ]);
  }

  public static function perm_public() {
    $settings = class_exists('TBFBKM_Plugin') ? TBFBKM_Plugin::instance()->get_settings() : [];
    if ( empty($settings['photofall_enabled']) ) return false;
    if ( ! empty($settings['photofall_public']) ) return true;
    return is_user_logged_in();
  }

  public static function list(WP_REST_Request $req) {
    $q = new TBFBKM_PhotoFall_Query();
    $res = $q->list([
      'route' => $req->get_param('route') ?: 'root',
      'page' => (int)($req->get_param('page') ?: 1),
      'page_size' => (int)($req->get_param('page_size') ?: 96),
      'blog_id' => (int)($req->get_param('blog_id') ?: 0),
      'year' => (int)($req->get_param('year') ?: 0),
      'month' => (int)($req->get_param('month') ?: 0),
      'tag' => (string)($req->get_param('tag') ?: ''),
      'q' => (string)($req->get_param('q') ?: ''),
    ]);
    return rest_ensure_response($res);
  }

  public static function item(WP_REST_Request $req) {
    $blogId = (int)$req->get_param('blog_id');
    $attId  = (int)$req->get_param('attachment_id');
    $q = new TBFBKM_PhotoFall_Query();
    $item = $q->get_item($blogId, $attId);
    if (!$item) return new WP_REST_Response(['message'=>'Not found'], 404);
    return rest_ensure_response($item);
  }
}

