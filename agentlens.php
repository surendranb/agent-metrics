<?php
/**
 * Plugin Name: AgentLens — Content & Traffic Analytics
 * Plugin URI: https://builditwithai.xyz/agentlens
 * Description: Make your WordPress website agent-ready with content negotiation for Markdown Twins, dynamic llms.txt, WebMCP client bridge, and local AI crawler analytics.
 * Version: 0.5.0
 * Author: surendran
 * Author URI: https://builditwithai.xyz
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: agentlens
 */

defined( 'ABSPATH' ) || exit;

define( 'AGENTLENS_VERSION', '0.5.0' );
define( 'AGENTLENS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENTLENS_FILE', __FILE__ );

if ( ! defined( 'AGENT_READY_VERSION' ) ) {
	define( 'AGENT_READY_VERSION', AGENTLENS_VERSION );
}
if ( ! defined( 'AGENT_READY_PLUGIN_DIR' ) ) {
	define( 'AGENT_READY_PLUGIN_DIR', AGENTLENS_PLUGIN_DIR );
}
if ( ! defined( 'AGENT_READY_FILE' ) ) {
	define( 'AGENT_READY_FILE', AGENTLENS_FILE );
}
if ( ! defined( 'AM_VERSION' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Backwards compatibility constant.
	define( 'AM_VERSION', AGENTLENS_VERSION );
}
if ( ! defined( 'AM_PLUGIN_DIR' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Backwards compatibility constant.
	define( 'AM_PLUGIN_DIR', AGENTLENS_PLUGIN_DIR );
}

require AGENTLENS_PLUGIN_DIR . 'includes/class-am-bot-catalog.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-parser.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-log-reader.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-storage.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-markdown.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-telemetry.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-prober.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-rollup.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-reports.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-brief.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-mcp-server.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-agent-activity.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-llms-txt.php';
require AGENTLENS_PLUGIN_DIR . 'includes/class-am-admin.php';

register_activation_hook( __FILE__, 'agentlens_activate' );
function agentlens_activate() {
	agentlens_ensure_setup();
	AM_Storage::install();
	AM_Rollup::invalidate();
	AM_Markdown::activate();
	if ( AM_Telemetry::enabled() ) {
		AM_Telemetry::send( 'plugin_activated' );
	}
}

add_action( 'init', 'agentlens_ensure_setup' );
function agentlens_ensure_setup() {
	AM_Storage::maybe_install();
	$mcp_key = get_option( 'agentlens_mcp_key' );
	if ( ! $mcp_key ) {
		$legacy_key = get_option( 'agent_ready_mcp_key', get_option( 'am_mcp_key' ) );
		$mcp_key    = $legacy_key ? $legacy_key : wp_generate_password( 32, false, false );
		update_option( 'agentlens_mcp_key', $mcp_key, false );
		update_option( 'agent_ready_mcp_key', $mcp_key, false );
		update_option( 'am_mcp_key', $mcp_key, false );
	}
	if ( ! wp_next_scheduled( 'agentlens_parse' ) && ! wp_next_scheduled( 'agent_ready_parse' ) && ! wp_next_scheduled( 'am_parse' ) ) {
		wp_schedule_event( time() + AM_Rollup::interval(), 'agentlens_parse', 'agentlens_parse' );
	}
	if ( ! wp_next_scheduled( 'agentlens_telemetry_heartbeat' ) && ! wp_next_scheduled( 'agent_ready_telemetry_heartbeat' ) && ! wp_next_scheduled( 'am_telemetry_heartbeat' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'agentlens_telemetry_heartbeat' );
	}
}

add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['agentlens_parse']   = array(
			'interval' => AM_Rollup::interval(),
			'display'  => 'AgentLens (configurable)',
		);
		$schedules['agent_ready_parse'] = $schedules['agentlens_parse'];
		$schedules['am_parse']          = $schedules['agentlens_parse'];
		return $schedules;
	}
);

add_action( 'agentlens_parse', array( 'AM_Rollup', 'refresh' ) );
add_action( 'agent_ready_parse', array( 'AM_Rollup', 'refresh' ) );
add_action( 'am_parse', array( 'AM_Rollup', 'refresh' ) );
add_action( 'agentlens_telemetry_heartbeat', array( 'AM_Telemetry', 'maybe_heartbeat' ) );
add_action( 'agent_ready_telemetry_heartbeat', array( 'AM_Telemetry', 'maybe_heartbeat' ) );
add_action( 'am_telemetry_heartbeat', array( 'AM_Telemetry', 'maybe_heartbeat' ) );

add_action( 'admin_menu', array( 'AM_Admin', 'menu' ) );
add_action( 'admin_init', array( 'AM_Admin', 'handle_consent' ) );
add_action( 'admin_notices', array( 'AM_Admin', 'consent_notice' ) );
add_action( 'admin_notices', array( 'AM_Admin', 'advocacy_notice' ) );
add_action( 'rest_api_init', array( 'AM_MCP_Server', 'init' ) );
add_action( 'rest_api_init', array( 'AM_Markdown', 'rest_init' ) );
add_action( 'init', array( 'AM_Markdown', 'init' ) );
add_filter( 'query_vars', array( 'AM_Markdown', 'query_vars' ) );
add_action( 'template_redirect', array( 'AM_Markdown', 'maybe_serve' ), 1 );
add_action( 'update_option_' . AM_Markdown::OPTION, array( 'AM_Markdown', 'flush' ) );
add_action( 'rest_api_init', array( 'AM_Agent_Activity', 'rest' ) );
add_action( 'template_redirect', array( 'AM_Agent_Activity', 'maybe_llms_txt' ), 1 );
add_action( 'template_redirect', array( 'AM_Llms_Txt', 'maybe_serve' ), 2 );
add_action( 'wp_enqueue_scripts', array( 'AM_Agent_Activity', 'enqueue' ) );

register_deactivation_hook( __FILE__, 'agentlens_deactivate' );
function agentlens_deactivate() {
	if ( AM_Telemetry::enabled() ) {
		AM_Telemetry::send( 'plugin_deactivated' );
	}
	wp_clear_scheduled_hook( 'agentlens_parse' );
	wp_clear_scheduled_hook( 'agentlens_telemetry_heartbeat' );
	wp_clear_scheduled_hook( 'agent_ready_parse' );
	wp_clear_scheduled_hook( 'agent_ready_telemetry_heartbeat' );
	wp_clear_scheduled_hook( 'am_parse' );
	wp_clear_scheduled_hook( 'am_daily_parse' );
	wp_clear_scheduled_hook( 'am_telemetry_heartbeat' );
}


