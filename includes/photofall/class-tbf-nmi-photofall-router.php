<?php
/**
 * File: includes/photofall/class-tbf-nmi-photofall-router.php
 * Version: 4.1.2
 *
 * Rewrite rules + query vars for Photofall.
 */
if ( ! defined('ABSPATH') ) exit;

class TBF_NMI_PhotoFall_Router {

  public static function register() {
    add_filter('query_vars', [__CLASS__, 'query_vars']);

    $base = trim(TBF_NMI_PHOTOFALL_BASE, '/');

    // Archive
    add_rewrite_rule("^{$base}/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=root&tbf_pf_page=1', 'top');
    add_rewrite_rule("^{$base}/page/([0-9]{1,6})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=root&tbf_pf_page=$matches[1]', 'top');

    // By site
    add_rewrite_rule("^{$base}/site/([0-9]{1,10})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=site&tbf_pf_blog_id=$matches[1]&tbf_pf_page=1', 'top');
    add_rewrite_rule("^{$base}/site/([0-9]{1,10})/page/([0-9]{1,6})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=site&tbf_pf_blog_id=$matches[1]&tbf_pf_page=$matches[2]', 'top');

    // By year / month
    add_rewrite_rule("^{$base}/([0-9]{4})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=year&tbf_pf_year=$matches[1]&tbf_pf_page=1', 'top');
    add_rewrite_rule("^{$base}/([0-9]{4})/page/([0-9]{1,6})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=year&tbf_pf_year=$matches[1]&tbf_pf_page=$matches[2]', 'top');

    add_rewrite_rule("^{$base}/([0-9]{4})/([0-9]{2})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=month&tbf_pf_year=$matches[1]&tbf_pf_month=$matches[2]&tbf_pf_page=1', 'top');
    add_rewrite_rule("^{$base}/([0-9]{4})/([0-9]{2})/page/([0-9]{1,6})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=month&tbf_pf_year=$matches[1]&tbf_pf_month=$matches[2]&tbf_pf_page=$matches[3]', 'top');

    // By tag
    add_rewrite_rule("^{$base}/tag/([^/]+)/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=tag&tbf_pf_tag=$matches[1]&tbf_pf_page=1', 'top');
    add_rewrite_rule("^{$base}/tag/([^/]+)/page/([0-9]{1,6})/?$", 'index.php?tbf_pf_kind=archive&tbf_pf_route=tag&tbf_pf_tag=$matches[1]&tbf_pf_page=$matches[2]', 'top');

    // Detail pages:
    add_rewrite_rule("^{$base}/i/([0-9]{1,10})/([0-9]{1,15})/?$", 'index.php?tbf_pf_kind=image&tbf_pf_blog_id=$matches[1]&tbf_pf_att_id=$matches[2]', 'top');
    add_rewrite_rule("^{$base}/v/([0-9]{1,10})/([0-9]{1,15})/?$", 'index.php?tbf_pf_kind=video&tbf_pf_blog_id=$matches[1]&tbf_pf_att_id=$matches[2]', 'top');
  }

  public static function query_vars($vars) {
    $vars[] = 'tbf_pf_kind';
    $vars[] = 'tbf_pf_route';
    $vars[] = 'tbf_pf_page';
    $vars[] = 'tbf_pf_blog_id';
    $vars[] = 'tbf_pf_att_id';
    $vars[] = 'tbf_pf_year';
    $vars[] = 'tbf_pf_month';
    $vars[] = 'tbf_pf_tag';
    return $vars;
  }
}
