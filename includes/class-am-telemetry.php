<?php
defined( 'ABSPATH' ) || exit;

class AM_Telemetry {

	const ENABLED = 'am_telemetry_enabled';
	const INSTALL = 'am_telemetry_install_id';
	const HEARTBEAT = 'am_telemetry_last_heartbeat';
	const URL = 'https://agent-metrics.builditwithai.xyz/v1/events';

	private static $properties = array(
		'telemetry_enabled' => array(),
		'first_parse'      => array( 'status' ),
		'parse_completed'  => array( 'status', 'duration_ms', 'lines_processed', 'skipped_lines' ),
		'mcp_configured'   => array( 'status' ),
		'plugin_heartbeat' => array( 'status', 'storage_rows_bucket' ),
		'mcp_started'      => array( 'status', 'client_name', 'client_version', 'protocol_version' ),
		'tool_executed'   => array( 'status', 'tool', 'latency_ms', 'client_name' ),
		'tool_error'      => array( 'status', 'tool', 'latency_ms', 'client_name' ),
	);

	public static function enabled() {
		return (bool) get_option( self::ENABLED, false );
	}

	public static function set_enabled( $enabled ) {
		$enabled = (bool) $enabled;
		$was_enabled = self::enabled();
		update_option( self::ENABLED, $enabled, false );
		if ( $enabled && ! $was_enabled ) {
			self::install_id();
			self::send( 'telemetry_enabled' );
		}
	}

	public static function maybe_heartbeat() {
		if ( ! self::enabled() || (int) get_option( self::HEARTBEAT, 0 ) > time() - DAY_IN_SECONDS ) {
			return;
		}
		update_option( self::HEARTBEAT, time(), false );
		self::send( 'plugin_heartbeat', array( 'status' => 'success' ) );
	}

	public static function send( $event, $properties = array(), $surface = 'plugin' ) {
		if ( ! self::enabled() || ! isset( self::$properties[ $event ] ) ) {
			return;
		}
		$allowed = array();
		foreach ( self::$properties[ $event ] as $key ) {
			if ( isset( $properties[ $key ] ) && is_scalar( $properties[ $key ] ) ) {
				$value = $properties[ $key ];
				$allowed[ $key ] = is_string( $value ) ? substr( sanitize_text_field( $value ), 0, 128 ) : $value;
			}
		}
		$allowed['product']        = 'agent-metrics';
		$allowed['surface']        = in_array( $surface, array( 'plugin', 'mcp' ), true ) ? $surface : 'plugin';
		$allowed['version']        = AM_VERSION;
		$allowed['schema_version'] = 1;
		$body = array(
			'event'      => $event,
			'distinct_id' => self::install_id(),
			'properties' => $allowed,
		);
		wp_remote_post(
			self::URL,
			array(
				'timeout'   => 0.1,
				'blocking'  => false,
				'sslverify' => true,
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => wp_json_encode( $body ),
			)
		);
	}

	public static function install_id() {
		$id = get_option( self::INSTALL, '' );
		if ( ! is_string( $id ) || ! $id ) {
			$id = 'am_' . wp_generate_uuid4();
			update_option( self::INSTALL, $id, false );
		}
		return $id;
	}
}
