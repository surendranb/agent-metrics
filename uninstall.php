<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove all plugin options.
$inplainsite_options = array(
	'inplainsite_mcp_key',
	'agentlens_mcp_key',
	'agent_ready_mcp_key',
	'agent_ready_storage_version',
	'agent_ready_log_cursor',
	'agent_ready_parse_status',
	'agent_ready_parse_interval_minutes',
	'agent_ready_last_parse_attempt',
	'agent_ready_telemetry_enabled',
	'agent_ready_telemetry_install_id',
	'agent_ready_telemetry_first_parse',
	'agent_ready_telemetry_last_heartbeat',
	'agent_ready_telemetry_mcp_configured',
	'agent_ready_telemetry_consent',
	'agent_ready_telemetry_consent_remind',
	'agent_ready_advocacy_dismissed',
	'agent_ready_activity_enabled',
	'agent_ready_llms_txt_pinned',
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
	'am_advocacy_dismissed',
	'am_agent_activity_enabled',
	'am_llms_txt_pinned',
);
foreach ( $inplainsite_options as $inplainsite_opt ) {
	delete_option( $inplainsite_opt );
}
delete_transient( 'inplainsite_rollup' );
delete_transient( 'agentlens_rollup' );
delete_transient( 'agent_ready_rollup' );
delete_transient( 'am_rollup' );

// Remove MCP rate-limit transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_inplainsite_%' OR option_name LIKE '_transient_timeout_inplainsite_%' OR option_name LIKE '_transient_agentlens_%' OR option_name LIKE '_transient_timeout_agentlens_%' OR option_name LIKE '_transient_agent_ready_%' OR option_name LIKE '_transient_timeout_agent_ready_%' OR option_name LIKE '_transient_am_mcp_rate_%' OR option_name LIKE '_transient_timeout_am_mcp_rate_%' OR option_name LIKE '_transient_am_beacon_rate_%' OR option_name LIKE '_transient_timeout_am_beacon_rate_%'"
);

// Drop the hits table.
$agent_ready_table = esc_sql( $wpdb->prefix . 'agent_metrics_hits' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS `{$agent_ready_table}`" );

// Clean up any leftover cron hooks.
wp_clear_scheduled_hook( 'inplainsite_parse' );
wp_clear_scheduled_hook( 'inplainsite_telemetry_heartbeat' );
wp_clear_scheduled_hook( 'agentlens_parse' );
wp_clear_scheduled_hook( 'agentlens_telemetry_heartbeat' );
wp_clear_scheduled_hook( 'agent_ready_parse' );
wp_clear_scheduled_hook( 'agent_ready_telemetry_heartbeat' );
wp_clear_scheduled_hook( 'am_parse' );
wp_clear_scheduled_hook( 'am_daily_parse' );
wp_clear_scheduled_hook( 'am_telemetry_heartbeat' );
