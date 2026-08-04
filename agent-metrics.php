<?php
/**
 * Plugin Name: AI Bot Traffic Analytics
 * Description: Reads server access logs and reports AI bot traffic (GPTBot, ClaudeBot, Google AI, etc.) with an admin dashboard and an MCP server so AI agents can query the same data.
 * Version: 0.1.0
 * Author: builditwithai.xyz
 * License: GPL-2.0-or-later
 * Text Domain: agent-metrics
 */

defined( 'ABSPATH' ) || exit;

define( 'AM_VERSION', '0.1.0' );
define( 'AM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require AM_PLUGIN_DIR . 'includes/class-am-bot-catalog.php';
require AM_PLUGIN_DIR . 'includes/class-am-parser.php';
require AM_PLUGIN_DIR . 'includes/class-am-log-reader.php';
require AM_PLUGIN_DIR . 'includes/class-am-prober.php';
require AM_PLUGIN_DIR . 'includes/class-am-rollup.php';
require AM_PLUGIN_DIR . 'includes/class-am-brief.php';
require AM_PLUGIN_DIR . 'includes/class-am-mcp-server.php';
require AM_PLUGIN_DIR . 'includes/class-am-admin.php';

register_activation_hook( __FILE__, 'am_activate' );
function am_activate() {
	am_ensure_setup();
	AM_Rollup::invalidate();
}

add_action( 'init', 'am_ensure_setup' );
function am_ensure_setup() {
	if ( ! get_option( 'am_mcp_key' ) ) {
		update_option( 'am_mcp_key', wp_generate_password( 32, false, false ) );
	}
	if ( ! wp_next_scheduled( 'am_parse' ) ) {
		wp_schedule_event( time() + AM_Rollup::interval(), 'am_parse', 'am_parse' );
	}
}

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['am_parse'] = array(
		'interval' => AM_Rollup::interval(),
		'display'  => 'Agent Metrics (configurable)',
	);
	return $schedules;
} );

add_action( 'am_parse', array( 'AM_Rollup', 'refresh' ) );
add_action( 'admin_menu', array( 'AM_Admin', 'menu' ) );
add_action( 'rest_api_init', array( 'AM_MCP_Server', 'init' ) );

register_deactivation_hook( __FILE__, 'am_deactivate' );
function am_deactivate() {
	wp_clear_scheduled_hook( 'am_parse' );
	wp_clear_scheduled_hook( 'am_daily_parse' );
}
