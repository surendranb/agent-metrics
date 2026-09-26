<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove all plugin options.
$inplainsite_options = array(
	'inplainsite_mcp_key',
	'inplainsite_storage_version',
	'inplainsite_log_cursor',
	'inplainsite_parse_status',
	'inplainsite_parse_interval_minutes',
	'inplainsite_last_parse_attempt',
	'inplainsite_advocacy_dismissed',
	'inplainsite_activity_enabled',
	'inplainsite_llms_txt_pinned',
);
foreach ( $inplainsite_options as $inplainsite_opt ) {
	delete_option( $inplainsite_opt );
}
delete_transient( 'inplainsite_rollup' );

// Remove MCP & beacon rate-limit transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_inplainsite_%' OR option_name LIKE '_transient_timeout_inplainsite_%'"
);

// Drop the hits table.
$inplainsite_table = esc_sql( $wpdb->prefix . 'agent_metrics_hits' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS `{$inplainsite_table}`" );

// Clean up any leftover cron hooks.
wp_clear_scheduled_hook( 'inplainsite_parse' );
