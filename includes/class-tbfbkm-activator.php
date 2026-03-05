<?php
/**
 * File: includes/class-tbfbkm-activator.php
 * Version: 6.9.6 (Big King Database Architect)
 * Description: Creates and updates the database tables for the Network Index and SEO Usage Map.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Activator {

	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// --- 1. Big King Media Index Table ---
		// Stores the metadata for every image, video, and audio file in the network.
		$table_name = $wpdb->base_prefix . 'tbfbkm_index';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) NOT NULL,
			attachment_id bigint(20) NOT NULL,
			url_full text NOT NULL,
			url_medium text DEFAULT '',
			url_thumb text DEFAULT '',
			poster_url text DEFAULT '',
			mime varchar(100) DEFAULT '',
			media_type varchar(20) DEFAULT 'image',
			title text DEFAULT '',
			alt text DEFAULT '',
			caption text DEFAULT '',
			description longtext DEFAULT '',
			width int(11) DEFAULT 0,
			height int(11) DEFAULT 0,
			year int(4) DEFAULT 0,
			month int(2) DEFAULT 0,
			created_gmt datetime DEFAULT '0000-00-00 00:00:00',
			updated_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY blog_att (blog_id, attachment_id),
			KEY media_type (media_type),
			KEY created_gmt (created_gmt)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		// --- 2. Kaleeyon SEO Usage Map ---
		// Tracks where media is used to generate 'Featured In' backlinks.
		$map_table = $wpdb->base_prefix . 'tbfbkm_usage_map';

		$sql_map = "CREATE TABLE $map_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			media_url varchar(255) NOT NULL, 
			blog_id bigint(20) NOT NULL,
			post_id bigint(20) NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY media_lookup (media_url(191)),
			KEY post_lookup (blog_id, post_id)
		) $charset_collate;";

		dbDelta( $sql_map );

		// --- 3. Default Settings ---
		// Initialize Big King Media options if they don't exist.
		if ( ! get_option('tbfbkm_photofall_options') ) {
			update_option('tbfbkm_photofall_options', [
				'per_page' => 20,
				'caption_mode' => 'hover',
				'default_sort' => 'date_desc',
				'enable_xml_sitemaps' => 1,
				'seo_interlink_origin' => 1,
				'enable_world_ruler' => 0, // Queen Keilah Studio disabled by default
				'wr_visual_mode' => 'random',
				'wr_playlists_json' => '[{"name":"Queen Keilah Default","tracks":[]}]'
			]);
		}

		// --- 4. Version Tracking ---
		update_option('tbfbkm_db_version', TBFBKM_VER);
	}
}
