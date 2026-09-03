<?php
defined( 'ABSPATH' ) || exit;

class AM_Storage {

	const VERSION = '1.1';
	const OPTION  = 'am_storage_version';
	const CURSOR  = 'am_log_cursor';
	const RETENTION_DAYS = 30;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'agent_metrics_hits';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
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
			KEY is_bot (is_bot),
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
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'INSERT IGNORE INTO ' . self::table() . '
				(timestamp, method, path, status_code, user_agent, is_bot, operator, bot, intent, source_file, source_inode, source_offset)
				VALUES (%s, %s, %s, %d, %s, %d, %s, %s, %s, %s, %s, %d)',
				gmdate( 'Y-m-d H:i:s', $hit['ts'] ),
				$hit['method'],
				$hit['path'],
				$hit['status'],
				$hit['ua'],
				$bot ? 1 : 0,
				$bot ? $bot['operator'] : null,
				$bot ? $bot['slug'] : null,
				$bot ? $bot['category'] : null,
				$source_file,
				(string) $source_inode,
				$source_offset
			)
		);
		return 1 === $result;
	}

	public static function insert_agent_event( $path, $ua, $status = 200 ) {
		global $wpdb;
		// ponytail: microsecond offset avoids source_position collisions; INSERT IGNORE covers the tie case
		return $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'INSERT IGNORE INTO ' . self::table() . '
				(timestamp, method, path, status_code, user_agent, is_bot, operator, bot, intent, source_file, source_inode, source_offset)
				VALUES (%s, %s, %s, %d, %s, 1, %s, %s, %s, %s, %s, %d)',
				gmdate( 'Y-m-d H:i:s' ),
				'GET',
				$path,
				$status,
				$ua,
				'MarkdownFetch',
				'MarkdownFetch',
				'agent-activity',
				'agent-activity',
				'0',
				(int) ( microtime( true ) * 1000000 )
			)
		);
	}

	public static function prune( $days = self::RETENTION_DAYS ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE timestamp < %s', $cutoff ) );
	}
}
