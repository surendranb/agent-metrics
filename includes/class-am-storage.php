<?php
defined( 'ABSPATH' ) || exit;

class AM_Storage {

	const VERSION = '1.0';
	const OPTION  = 'am_storage_version';
	const CURSOR  = 'am_log_cursor';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'agent_metrics_hits';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table  = self::table();
		$collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timestamp datetime NOT NULL,
			method varchar(16) NOT NULL,
			path text NOT NULL,
			status_code smallint(5) unsigned NOT NULL DEFAULT 0,
			user_agent text NOT NULL,
			is_bot tinyint(1) unsigned NOT NULL DEFAULT 0,
			operator varchar(64) NULL,
			bot varchar(64) NULL,
			intent varchar(32) NULL,
			source_file varchar(255) NOT NULL,
			source_inode varchar(64) NOT NULL,
			source_offset bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_position (source_file(100),source_inode(40),source_offset),
			KEY timestamp (timestamp),
			KEY bot (bot),
			KEY operator (operator),
			KEY intent (intent),
			KEY path (path(191))
		) {$collate};";
		dbDelta( $sql );
		update_option( self::OPTION, self::VERSION, false );
	}

	public static function maybe_install() {
		if ( self::VERSION !== get_option( self::OPTION ) ) {
			self::install();
		}
	}

	public static function cursor() {
		$cursor = get_option( self::CURSOR, array() );
		return is_array( $cursor ) ? $cursor : array();
	}

	public static function save_cursor( $cursor ) {
		update_option( self::CURSOR, $cursor, false );
	}

	public static function insert( $hit, $bot, $source_file, $source_inode, $source_offset ) {
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . self::table() . ' WHERE source_file = %s AND source_inode = %s AND source_offset = %d LIMIT 1',
			$source_file,
			(string) $source_inode,
			$source_offset
		) );
		if ( $exists ) {
			return false;
		}
		$data = array(
			'timestamp'      => gmdate( 'Y-m-d H:i:s', $hit['ts'] ),
			'method'         => $hit['method'],
			'path'           => $hit['path'],
			'status_code'    => $hit['status'],
			'user_agent'     => $hit['ua'],
			'is_bot'         => $bot ? 1 : 0,
			'operator'       => $bot ? $bot['operator'] : null,
			'bot'            => $bot ? $bot['slug'] : null,
			'intent'         => $bot ? $bot['category'] : null,
			'source_file'    => $source_file,
			'source_inode'   => (string) $source_inode,
			'source_offset'  => $source_offset,
		);
		$format = array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d' );
		$result = $wpdb->insert( self::table(), $data, $format );
		return false !== $result;
	}
}
