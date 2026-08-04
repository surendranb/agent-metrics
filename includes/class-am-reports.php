<?php
defined( 'ABSPATH' ) || exit;

class AM_Reports {

	public static function get() {
		global $wpdb;
		$table = AM_Storage::table();
		$rollup = AM_Rollup::empty();

		$bots = $wpdb->get_results( "SELECT bot, MAX(operator) operator, MAX(intent) category, COUNT(*) hits FROM {$table} WHERE is_bot = 1 GROUP BY bot", ARRAY_A );
		foreach ( $bots as $row ) {
			$rollup['bots'][ $row['bot'] ] = array(
				'name'     => AM_Bot_Catalog::get( $row['bot'] )['name'] ?? $row['bot'],
				'operator' => $row['operator'],
				'category' => $row['category'] ?: 'unknown',
				'hits'     => (int) $row['hits'],
			);
		}

		$daily = $wpdb->get_results( "SELECT DATE(`timestamp`) day, bot, COUNT(*) hits FROM {$table} WHERE is_bot = 1 GROUP BY DATE(`timestamp`), bot ORDER BY day ASC", ARRAY_A );
		foreach ( $daily as $row ) {
			$rollup['days'][ $row['day'] ][ $row['bot'] ] = (int) $row['hits'];
		}

		$day_pages = $wpdb->get_results( "SELECT DATE(`timestamp`) day, path, COUNT(*) hits FROM {$table} WHERE is_bot = 1 GROUP BY DATE(`timestamp`), path ORDER BY day ASC", ARRAY_A );
		foreach ( $day_pages as $row ) {
			$rollup['day_pages'][ $row['day'] ][ $row['path'] ] = (int) $row['hits'];
			$rollup['pages'][ $row['path'] ] = ( $rollup['pages'][ $row['path'] ] ?? 0 ) + (int) $row['hits'];
		}

		$status = get_option( 'am_parse_status', array() );
		$rollup['generated']   = (int) ( $status['generated'] ?? time() );
		$rollup['log_path']    = $status['log_path'] ?? null;
		$rollup['error']       = $status['error'] ?? null;
		$rollup['diagnostics'] = $status['diagnostics'] ?? array();
		$rollup['total_lines'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$rollup['skipped']     = (int) ( $status['skipped'] ?? 0 );
		$rollup['recommended_interval_min'] = AM_Rollup::recommended_interval_minutes( $rollup );
		$rollup['interval_min'] = (int) get_option( 'am_parse_interval_minutes', 0 );
		$rollup['next_parse']   = $rollup['generated'] + AM_Rollup::interval();
		return $rollup;
	}
}
