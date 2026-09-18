<?php
/**
 * Surface B — WebMCP bridge support: beacon REST route, inferred LlmsTxt events, front-end enqueue.
 *
 * @package agent-metrics
 */

defined( 'ABSPATH' ) || exit;

class InPlainSite_Agent_Activity {

	const INTENT = 'agent-activity';
	const TOOLS  = array( 'get_page_content', 'search_site', 'get_site_map' );

	public static function rest() {
		$args = array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'record' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		);
		register_rest_route( 'inplainsite/v1', '/agent-activity', $args );
		register_rest_route( 'agentlens/v1', '/agent-activity', $args );
		register_rest_route( 'agent-ready-website/v1', '/agent-activity', $args );
		register_rest_route( 'agent-metrics/v1', '/agent-activity', $args );
	}

	public static function check_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public static function record( $request ) {
		// ponytail: transient-based rate limiter — 60 requests/minute per IP; upgrade to Redis if needed
		$raw_ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip       = filter_var( $raw_ip, FILTER_VALIDATE_IP );
		$ip       = false === $ip ? 'unknown' : $ip;
		$rate_key = 'am_beacon_rate_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 60 ) {
			return new WP_REST_Response(
				array( 'error' => 'Rate limit exceeded. Try again in a minute.' ),
				429
			);
		}
		set_transient( $rate_key, $count + 1, 60 );

		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid JSON' ), 400 );
		}
		$tool = sanitize_key( (string) ( $body['tool'] ?? '' ) );
		if ( ! in_array( $tool, self::TOOLS, true ) ) {
			return new WP_REST_Response( array( 'error' => 'unknown tool' ), 422 );
		}
		self::insert( 'WebMCP:' . $tool, sanitize_key( (string) ( $body['slug'] ?? '' ) ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function maybe_llms_txt() {
		if ( ! AM_Markdown::enabled() ) {
			return;
		}
		$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path    = wp_parse_url( $raw_uri, PHP_URL_PATH );
		$path    = is_string( $path ) ? rtrim( $path, '/' ) : '';
		if ( ! in_array( $path, array( '/llms.txt', '/.well-known/llms.txt', '/llms-full.txt' ), true ) ) {
			return;
		}
		self::insert( 'LlmsTxt', '' );
	}

	public static function summary( $days = 30 ) {
		global $wpdb;
		$days   = max( 1, min( 365, (int) $days ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table  = esc_sql( AM_Storage::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be a placeholder.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot, path, DATE(`timestamp`) day, COUNT(*) n FROM {$table} WHERE intent = %s AND `timestamp` >= %s GROUP BY bot, path, day", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::INTENT,
				$cutoff
			),
			ARRAY_A
		);
		$summary = array(
			'totals'  => array(
				'markdown_fetches'   => 0,
				'llms_txt_downloads' => 0,
				'webmcp_executions'  => 0,
			),
			'by_tool' => array(),
			'by_page' => array(),
			'trend'   => array(),
		);
		$pages   = array();
		$per_day = array();
		foreach ( $rows as $r ) {
			$bot = (string) $r['bot'];
			$n   = (int) $r['n'];
			if ( 'MarkdownFetch' === $bot ) {
				$summary['totals']['markdown_fetches'] += $n;
			} elseif ( 'LlmsTxt' === $bot ) {
				$summary['totals']['llms_txt_downloads'] += $n;
			} elseif ( 0 === strpos( $bot, 'WebMCP:' ) ) {
				$summary['totals']['webmcp_executions'] += $n;
				$summary['by_tool'][ $bot ]              = ( $summary['by_tool'][ $bot ] ?? 0 ) + $n;
			}
			// ponytail: LlmsTxt rows carry path "/" (site-level, not a page) — excluded from by_page
			if ( 'LlmsTxt' !== $bot ) {
				$pages[ $r['path'] ] = ( $pages[ $r['path'] ] ?? 0 ) + $n;
			}
			$per_day[ $r['day'] ] = ( $per_day[ $r['day'] ] ?? 0 ) + $n;
		}
		arsort( $pages );
		foreach ( $pages as $page => $count ) {
			$summary['by_page'][] = array(
				'page'  => $page,
				'count' => $count,
			);
		}
		ksort( $per_day );
		foreach ( $per_day as $date => $count ) {
			$summary['trend'][] = array(
				'date'  => $date,
				'count' => $count,
			);
		}
		return $summary;
	}

	public static function enqueue() {
		if ( ! AM_Markdown::enabled() ) {
			return;
		}
		$plugin_file = defined( 'INPLAINSITE_FILE' ) ? INPLAINSITE_FILE : ( defined( 'AGENTLENS_FILE' ) ? AGENTLENS_FILE : ( defined( 'AGENT_READY_FILE' ) ? AGENT_READY_FILE : AM_PLUGIN_DIR . 'inplainsite.php' ) );
		wp_enqueue_script(
			'inplainsite-webmcp-bridge',
			plugins_url( 'assets/js/webmcp-bridge.js', $plugin_file ),
			array(),
			defined( 'INPLAINSITE_VERSION' ) ? INPLAINSITE_VERSION : AM_VERSION,
			array( 'strategy' => 'defer' )
		);
		wp_localize_script(
			'inplainsite-webmcp-bridge',
			'amAgentActivity',
			array(
				'slug'  => is_singular() ? (string) get_post_field( 'post_name' ) : '',
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	private static function insert( $bot, $slug ) {
		global $wpdb;
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 2000 ) : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			AM_Storage::table(),
			array(
				'timestamp'     => current_time( 'mysql', true ),
				'method'        => 'GET',
				'path'          => $slug ? '/' . $slug : '/',
				'status_code'   => 200,
				'user_agent'    => $ua,
				'is_bot'        => 1,
				'operator'      => 'WebMCP' === $bot ? 'WebMCP' : null,
				'bot'           => $bot,
				'intent'        => self::INTENT,
				'source_file'   => 'agent-activity',
				'source_inode'  => 'agent-activity',
				// ponytail: random offset only to satisfy the source_position unique key — no log-file semantics here
				'source_offset' => random_int( 1, PHP_INT_MAX ),
			)
		);
	}
}

class_alias( 'InPlainSite_Agent_Activity', 'AgentLens_Agent_Activity' );
class_alias( 'InPlainSite_Agent_Activity', 'Agent_Ready_Agent_Activity' );
class_alias( 'InPlainSite_Agent_Activity', 'AM_Agent_Activity' );
