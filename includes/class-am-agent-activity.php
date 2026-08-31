<?php
/**
 * Surface B — WebMCP bridge support: beacon REST route, inferred LlmsTxt events, front-end enqueue.
 *
 * @package agent-metrics
 */

defined( 'ABSPATH' ) || exit;

class AM_Agent_Activity {

	const INTENT = 'agent-activity';
	const TOOLS  = array( 'get_page_content', 'search_site', 'get_site_map' );

	public static function rest() {
		register_rest_route(
			'agent-metrics/v1',
			'/agent-activity',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'record' ),
			'permission_callback' => '__return_true',
		)
	);
}

	public static function record( $request ) {
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
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$path = is_string( $path ) ? rtrim( $path, '/' ) : '';
		if ( ! in_array( $path, array( '/llms.txt', '/.well-known/llms.txt', '/llms-full.txt' ), true ) ) {
			return;
		}
		self::insert( 'LlmsTxt', '' );
	}

	public static function summary( $days = 30 ) {
		global $wpdb;
		$days   = max( 1, min( 365, (int) $days ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table  = AM_Storage::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be a placeholder.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot, path, DATE(`timestamp`) day, COUNT(*) n FROM {$table} WHERE intent = %s AND `timestamp` >= %s GROUP BY bot, path, day",
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
		wp_enqueue_script(
			'am-webmcp-bridge',
			plugins_url( 'assets/js/webmcp-bridge.js', AM_PLUGIN_DIR . 'agent-metrics.php' ),
			array(),
			AM_VERSION,
			array( 'strategy' => 'defer' )
		);
		wp_localize_script(
			'am-webmcp-bridge',
			'amAgentActivity',
			array( 'slug' => is_singular() ? (string) get_post_field( 'post_name' ) : '' )
		);
	}

	private static function insert( $bot, $slug ) {
		global $wpdb;
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 2000 ) : '';
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
