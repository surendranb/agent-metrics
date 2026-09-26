<?php
/**
 * Plugin Name: InPlainSite – Clean Markdown & Visitor Telemetry
 * Plugin URI: https://github.com/surendranb/agent-metrics
 * Description: Make your WordPress website agent-ready with content negotiation for Markdown Twins, dynamic llms.txt, WebMCP client bridge, and local AI crawler analytics.
 * Version: 0.5.2
 * Author: surendran
 * Author URI: https://builditwithai.xyz
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: inplainsite
 */

defined( 'ABSPATH' ) || exit;

define( 'INPLAINSITE_VERSION', '0.5.2' );
define( 'INPLAINSITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INPLAINSITE_FILE', __FILE__ );

require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-bot-catalog.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-parser.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-log-reader.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-storage.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-markdown.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-prober.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-rollup.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-reports.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-brief.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-mcp-server.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-agent-activity.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-llms-txt.php';
require INPLAINSITE_PLUGIN_DIR . 'includes/class-inplainsite-admin.php';

register_activation_hook( __FILE__, 'inplainsite_activate' );
function inplainsite_activate() {
	inplainsite_ensure_setup();
	InPlainSite_Storage::install();
	InPlainSite_Rollup::invalidate();
	InPlainSite_Markdown::activate();
}

add_action( 'init', 'inplainsite_ensure_setup' );
function inplainsite_ensure_setup() {
	InPlainSite_Storage::maybe_install();
	$mcp_key = get_option( 'inplainsite_mcp_key' );
	if ( ! $mcp_key ) {
		$mcp_key = wp_generate_password( 32, false, false );
		update_option( 'inplainsite_mcp_key', $mcp_key, false );
	}
	if ( ! wp_next_scheduled( 'inplainsite_parse' ) ) {
		wp_schedule_event( time() + InPlainSite_Rollup::interval(), 'inplainsite_parse', 'inplainsite_parse' );
	}
}

add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['inplainsite_parse'] = array(
			'interval' => InPlainSite_Rollup::interval(),
			'display'  => 'InPlainSite (configurable)',
		);
		return $schedules;
	}
);

add_action( 'inplainsite_parse', array( 'InPlainSite_Rollup', 'refresh' ) );

add_action( 'admin_menu', array( 'InPlainSite_Admin', 'menu' ) );
add_action( 'admin_init', array( 'InPlainSite_Admin', 'handle_actions' ) );
add_action( 'admin_notices', array( 'InPlainSite_Admin', 'advocacy_notice' ) );
add_action( 'rest_api_init', array( 'InPlainSite_MCP_Server', 'init' ) );
add_action( 'rest_api_init', array( 'InPlainSite_Markdown', 'rest_init' ) );
add_action( 'init', array( 'InPlainSite_Markdown', 'init' ) );
add_filter( 'query_vars', array( 'InPlainSite_Markdown', 'query_vars' ) );
add_action( 'template_redirect', array( 'InPlainSite_Markdown', 'maybe_serve' ), 1 );
add_action( 'update_option_' . InPlainSite_Markdown::OPTION, array( 'InPlainSite_Markdown', 'flush' ) );
add_action( 'rest_api_init', array( 'InPlainSite_Agent_Activity', 'rest' ) );
add_action( 'template_redirect', array( 'InPlainSite_Agent_Activity', 'maybe_llms_txt' ), 1 );
add_action( 'template_redirect', array( 'InPlainSite_Llms_Txt', 'maybe_serve' ), 2 );
add_action( 'wp_enqueue_scripts', array( 'InPlainSite_Agent_Activity', 'enqueue' ) );

register_deactivation_hook( __FILE__, 'inplainsite_deactivate' );
function inplainsite_deactivate() {
	wp_clear_scheduled_hook( 'inplainsite_parse' );
}
