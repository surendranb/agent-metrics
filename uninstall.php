<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove all plugin options.
$options = array(
	'am_mcp_key',
	'am_storage_version',
	'am_log_cursor',
	'am_parse_status',
	'am_parse_interval_minutes',
	'am_last_parse_attempt',
	'am_telemetry_enabled',
	'am_telemetry_install_id',
	'am_telemetry_first_parse',
	'am_telemetry_last_heartbeat',
	'am_telemetry_mcp_configured',
	'am_telemetry_consent',
	'am_telemetry_consent_remind',
);
foreach ( $options as $opt ) {
	delete_option( $opt );
}
delete_transient( 'am_rollup' );

// Remove MCP rate-limit transients.
$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_am_mcp_rate_%' OR option_name LIKE '_transient_timeout_am_mcp_rate_%'"
);

// Drop the hits table.
$table = $wpdb->prefix . 'agent_metrics_hits';
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Clean up any leftover cron hooks.
wp_clear_scheduled_hook( 'am_parse' );
wp_clear_scheduled_hook( 'am_daily_parse' );
wp_clear_scheduled_hook( 'am_telemetry_heartbeat' );
