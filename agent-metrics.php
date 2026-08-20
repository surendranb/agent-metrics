<?php
/**
 * Plugin Name: AI Bot Traffic Analytics
 * Plugin URI: https://builditwithai.xyz/agent-metrics
 * Description: Reads server access logs and reports AI bot traffic with an admin dashboard and an MCP server for AI agents.
 * Version: 0.4.1
 * Author: builditwithai.xyz
 * Author URI: https://builditwithai.xyz
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: agent-metrics
 */

defined( 'ABSPATH' ) || exit;

define( 'AM_VERSION', '0.4.1' );
define( 'AM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require AM_PLUGIN_DIR . 'includes/class-am-bot-catalog.php';
require AM_PLUGIN_DIR . 'includes/class-am-parser.php';
require AM_PLUGIN_DIR . 'includes/class-am-log-reader.php';
require AM_PLUGIN_DIR . 'includes/class-am-storage.php';
require AM_PLUGIN_DIR . 'includes/class-am-telemetry.php';
require AM_PLUGIN_DIR . 'includes/class-am-prober.php';
require AM_PLUGIN_DIR . 'includes/class-am-rollup.php';
require AM_PLUGIN_DIR . 'includes/class-am-reports.php';
require AM_PLUGIN_DIR . 'includes/class-am-brief.php';
require AM_PLUGIN_DIR . 'includes/class-am-mcp-server.php';
require AM_PLUGIN_DIR . 'includes/class-am-admin.php';

register_activation_hook( __FILE__, 'am_activate' );
function am_activate() {
	am_ensure_setup();
	AM_Storage::install();
	AM_Rollup::invalidate();
	if ( AM_Telemetry::enabled() ) {
		AM_Telemetry::send( 'plugin_activated' );
	}
}

add_action( 'init', 'am_ensure_setup' );
function am_ensure_setup() {
	AM_Storage::maybe_install();
	if ( ! get_option( 'am_mcp_key' ) ) {
		update_option( 'am_mcp_key', wp_generate_password( 32, false, false ), false );
	}
	if ( ! wp_next_scheduled( 'am_parse' ) ) {
		wp_schedule_event( time() + AM_Rollup::interval(), 'am_parse', 'am_parse' );
	}
	if ( ! wp_next_scheduled( 'am_telemetry_heartbeat' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'am_telemetry_heartbeat' );
	}
}

add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['am_parse'] = array(
			'interval' => AM_Rollup::interval(),
			'display'  => 'Agent Metrics (configurable)',
		);
		return $schedules;
	}
);

add_action( 'am_parse', array( 'AM_Rollup', 'refresh' ) );
add_action( 'am_telemetry_heartbeat', array( 'AM_Telemetry', 'maybe_heartbeat' ) );
add_action( 'admin_menu', array( 'AM_Admin', 'menu' ) );
add_action( 'admin_init', array( 'AM_Admin', 'handle_consent' ) );
add_action( 'admin_notices', array( 'AM_Admin', 'consent_notice' ) );
add_action( 'rest_api_init', array( 'AM_MCP_Server', 'init' ) );

register_deactivation_hook( __FILE__, 'am_deactivate' );
function am_deactivate() {
	if ( AM_Telemetry::enabled() ) {
		AM_Telemetry::send( 'plugin_deactivated' );
	}
	wp_clear_scheduled_hook( 'am_parse' );
	wp_clear_scheduled_hook( 'am_daily_parse' );
	wp_clear_scheduled_hook( 'am_telemetry_heartbeat' );
}
