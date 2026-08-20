<?php
defined( 'ABSPATH' ) || exit;

class AM_MCP_Server {

	const PROTOCOL = '2025-03-26';

	public static function init() {
		register_rest_route( 'agent-metrics/v1', '/mcp', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle' ),
			'permission_callback' => array( __CLASS__, 'auth' ),
		) );
	}

	public static function auth( $request ) {
		$key = get_option( 'am_mcp_key' );
		if ( ! $key ) {
			return false;
		}
		$header = $request->get_header( 'authorization' );
		if ( $header && preg_match( '/^Bearer\s+(\S+)$/i', $header, $m ) && hash_equals( $key, $m[1] ) ) {
			return true;
		}
		$header = $request->get_header( 'x-am-key' );
		if ( $header && hash_equals( $key, $header ) ) {
			return true;
		}
		return false;
	}

	public static function handle( $request ) {
		// ponytail: transient-based rate limiter — 120 requests/minute per IP; upgrade to Redis if needed
		$ip       = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP ) ?: 'unknown';
		$rate_key = 'am_mcp_rate_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 120 ) {
			return new WP_REST_Response( array(
				'jsonrpc' => '2.0',
				'error'   => array( 'code' => -32000, 'message' => 'Rate limit exceeded. Try again in a minute.' ),
			), 429 );
		}
		set_transient( $rate_key, $count + 1, 60 );

		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) || ( $body['jsonrpc'] ?? '' ) !== '2.0' || ! isset( $body['method'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid JSON-RPC request' ), 400 );
		}
		$id     = $body['id'] ?? null;
		$method = $body['method'];
		$params = $body['params'] ?? array();
		$started = microtime( true );

		try {
			$result = self::dispatch( $method, $params );
			if ( 'initialize' === $method ) {
				$client = is_array( $params['clientInfo'] ?? null ) ? $params['clientInfo'] : array();
				AM_Telemetry::send( 'mcp_started', array(
					'status'          => 'success',
					'client_name'     => $client['name'] ?? '',
					'client_version'  => $client['version'] ?? '',
					'protocol_version' => $params['protocolVersion'] ?? '',
				), 'mcp' );
			}
			if ( 'tools/call' === $method ) {
				AM_Telemetry::send( 'tool_executed', array(
					'status'    => 'success',
					'tool'      => $params['name'] ?? '',
					'latency_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
				), 'mcp' );
			}
		} catch ( Exception $e ) {
			if ( 'tools/call' === $method ) {
				AM_Telemetry::send( 'tool_error', array(
					'status'    => 'error',
					'tool'      => $params['name'] ?? '',
					'latency_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
				), 'mcp' );
			}
			return self::rpc( $id, null, array( 'code' => -32601, 'message' => $e->getMessage() ) );
		}
		return self::rpc( $id, $result, null );
	}

	private static function rpc( $id, $result, $error ) {
		$res = array( 'jsonrpc' => '2.0' );
		if ( null !== $id ) {
			$res['id'] = $id;
		}
		if ( $error ) {
			$res['error'] = $error;
		} else {
			$res['result'] = $result;
		}
		return rest_ensure_response( $res );
	}

	private static function dispatch( $method, $params ) {
		switch ( $method ) {
			case 'initialize':
				return array(
					'protocolVersion' => self::PROTOCOL,
					'capabilities'    => array(
						'tools'     => array( 'listChanged' => false ),
						'prompts'   => array( 'listChanged' => false ),
						'resources' => array( 'listChanged' => false ),
					),
					'serverInfo'      => array( 'name' => 'agent-metrics', 'version' => AM_VERSION ),
				);
			case 'ping':
				return new stdClass();
			case 'notifications/initialized':
				return new stdClass();
			case 'tools/list':
				return array( 'tools' => self::tools() );
			case 'tools/call':
				return self::call_tool( $params );
			case 'prompts/list':
				return array( 'prompts' => self::prompts() );
			case 'prompts/get':
				return self::get_prompt( $params );
			case 'resources/list':
				return array( 'resources' => self::resources() );
			case 'resources/read':
				return self::read_resource( $params );
			default:
				throw new Exception( 'Method not found: ' . $method );
		}
	}

	private static function tools() {
		return array(
			array(
				'name'        => 'log_status',
				'description' => 'Which log file the plugin reads, when it last parsed, and parse quality (total/skipped lines).',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
			),
			array(
				'name'        => 'daily_brief',
				'description' => 'The daily brief: most recent day with data — top 3 bots, top 3 pages, new bots, new pages, 30-day trend.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
			),
			array(
				'name'        => 'bot_summary',
				'description' => 'Aggregate AI bot traffic: total hits, unique bots, and per-bot hit counts.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'top_n' => array( 'type' => 'number', 'description' => 'Return only the top N bots (default 10).' ),
					),
				),
			),
			array(
				'name'        => 'bot_breakdown',
				'description' => 'Per-day, per-bot hit counts (30-day window).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'bot' => array( 'type' => 'string', 'description' => 'Optional bot slug to filter (e.g. gptbot).' ),
					),
				),
			),
			array(
				'name'        => 'bot_trend',
				'description' => 'Total AI bot hits per day (30-day window) for trend analysis.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
			),
			array(
				'name'        => 'top_pages',
				'description' => 'Pages most crawled by AI bots.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'number', 'description' => 'Number of pages (default 20).' ),
					),
				),
			),
		);
	}

	private static function call_tool( $params ) {
		$name  = $params['name'] ?? '';
		$args  = (array) ( $params['arguments'] ?? array() );
		$rollup = AM_Reports::get();
		if ( false === $rollup ) {
			throw new Exception( 'Log parse in progress or failed; retry shortly' );
		}
		switch ( $name ) {
			case 'log_status':
				$text = array(
					'log_path'    => $rollup['log_path'],
					'error'       => $rollup['error'],
					'generated'   => gmdate( 'c', $rollup['generated'] ),
					'age_secs'    => time() - $rollup['generated'],
					'interval_min'      => $rollup['interval_min'],
					'next_parse'  => gmdate( 'c', $rollup['next_parse'] ),
					'recommended_interval_min' => $rollup['recommended_interval_min'],
					'total_lines' => $rollup['total_lines'],
					'skipped'     => $rollup['skipped'],
					'bots'        => count( $rollup['bots'] ),
				);
				break;
			case 'daily_brief':
			$brief = AM_Brief::get( $rollup );
			$text  = null === $brief ? array( 'note' => 'No data yet.' ) : $brief;
			break;
		case 'bot_summary':
				$top_n  = max( 1, min( 100, (int) ( $args['top_n'] ?? 10 ) ) );
				$rows   = array();
				$total  = 0;
				foreach ( $rollup['bots'] as $slug => $b ) {
					$total += $b['hits'];
				}
				foreach ( $rollup['bots'] as $slug => $b ) {
					$rows[] = array(
						'bot'      => $slug,
						'name'     => $b['name'],
						'category' => $b['category'],
						'hits'     => $b['hits'],
						'share'    => $total ? round( 100 * $b['hits'] / $total, 1 ) : 0,
					);
				}
				usort( $rows, function ( $a, $b ) {
					return $b['hits'] <=> $a['hits'];
				} );
				$text = array( 'total_hits' => $total, 'unique_bots' => count( $rows ), 'bots' => array_slice( $rows, 0, $top_n ) );
				break;
			case 'bot_breakdown':
				$filter = isset( $args['bot'] ) ? strtolower( (string) $args['bot'] ) : '';
				$days   = array();
				foreach ( $rollup['days'] as $day => $slugs ) {
					$row = array( 'date' => $day, 'hits' => 0, 'by_bot' => array() );
					foreach ( $slugs as $slug => $n ) {
						if ( $filter && $slug !== $filter ) {
							continue;
						}
						$row['by_bot'][ $slug ] = $n;
						$row['hits'] += $n;
					}
					if ( $row['hits'] > 0 ) {
						$days[] = $row;
					}
				}
				usort( $days, function ( $a, $b ) {
					return strcmp( $a['date'], $b['date'] );
				} );
			$text = array( 'days' => array_slice( $days, -30 ) );
			break;
		case 'bot_trend':
				$days = array();
				foreach ( $rollup['days'] as $day => $slugs ) {
					$days[] = array( 'date' => $day, 'hits' => array_sum( $slugs ) );
				}
				usort( $days, function ( $a, $b ) {
					return strcmp( $a['date'], $b['date'] );
				} );
			$text = array( 'days' => array_slice( $days, -30 ) );
			break;
		case 'top_pages':
				$limit = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
				$pages = array();
				foreach ( $rollup['pages'] as $path => $n ) {
					$pages[] = array( 'path' => $path, 'hits' => $n );
				}
				usort( $pages, function ( $a, $b ) {
					return $b['hits'] <=> $a['hits'];
				} );
				$text = array( 'pages' => array_slice( $pages, 0, $limit ) );
				break;
			default:
				throw new Exception( 'Unknown tool: ' . $name );
		}
		return array(
			'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $text, JSON_PRETTY_PRINT ) ) ),
		);
	}

	private static function prompts() {
		return array(
			array(
				'name'        => 'weekly-report',
				'description' => 'Summarize AI bot crawling over the last 7 days: top bots, totals, notable changes.',
				'arguments'   => array(),
			),
			array(
				'name'        => 'trend-analysis',
				'description' => 'Analyze the 30-day AI bot traffic trend for spikes or declines.',
				'arguments'   => array(),
			),
			array(
				'name'        => 'investigate-spike',
				'description' => 'Dig into a traffic spike: which bot, which pages, which days.',
				'arguments'   => array(
					array( 'name' => 'bot', 'description' => 'Optional bot slug to focus on (e.g. gptbot).', 'required' => false ),
				),
			),
			array(
				'name'        => 'daily-brief',
				'description' => 'The day in review: top bots, top pages, what is new.',
				'arguments'   => array(),
			),
		);
	}

	public static function prompt_text( $name, $args = array() ) {
		switch ( $name ) {
			case 'weekly-report':
				return "You are analyzing AI bot traffic for a WordPress site. Use the agent-metrics tools.\n\n"
					. "1. Call bot_summary and bot_trend.\n"
					. "2. Summarize the last 7 days: total AI bot hits, top 3 bots, biggest changes vs earlier days.\n"
					. "3. Call top_pages and mention the most-crawled pages.\n"
					. "4. Keep it to 8-12 lines. Plain language, no jargon.";
			case 'trend-analysis':
				return "You are analyzing the 30-day AI bot traffic trend for a WordPress site.\n\n"
					. "1. Call bot_trend and bot_breakdown.\n"
					. "2. Identify spikes (day-over-day change > 50%) and declines.\n"
					. "3. Attribute each spike to a bot and, if possible, a page (call bot_breakdown and top_pages).\n"
					. "4. End with a one-line recommendation.";
			case 'investigate-spike':
				$focus = isset( $args['bot'] ) ? 'Focus on bot "' . $args['bot'] . '".' : 'No specific bot — investigate overall.';
				return "You are investigating a traffic spike on a WordPress site. $focus\n\n"
					. "1. Call bot_trend to find the spiky day(s).\n"
					. "2. Call bot_breakdown (pass the bot slug if you have one) to see which bot drove it.\n"
					. "3. Call top_pages to see which pages were crawled.\n"
					. "4. Report: spike date, bot, pages, and a likely cause (e.g. site submitted to a search index, model retraining).";
			case 'daily-brief':
				return "You are preparing the daily brief for a WordPress site owner.\n\n"
					. "1. Call the daily_brief tool.\n"
					. "2. Present: top 3 bots (name + hits), top 3 pages, new bots and new pages, and whether the trend is up or down vs the previous day.\n"
					. "3. Plain language, 6-10 lines, no jargon. If the tool returns no data, say the brief has no data yet.";
			default:
				throw new Exception( 'Unknown prompt: ' . $name );
		}
	}

	private static function get_prompt( $params ) {
		$name = $params['name'] ?? '';
		$args = (array) ( $params['arguments'] ?? array() );
		return array(
			'prompt' => array(
				'description' => $name,
				'messages'    => array( array( 'role' => 'user', 'content' => array( 'type' => 'text', 'text' => self::prompt_text( $name, $args ) ) ) ),
			),
		);
	}

	private static function resources() {
		return array(
			array(
				'uri'         => 'agent-metrics://summary',
				'name'        => 'Current rollup',
				'description' => 'The current cached traffic rollup as JSON.',
			),
			array(
				'uri'         => 'agent-metrics://docs/setup',
				'name'        => 'Setup & troubleshooting',
				'description' => 'How the plugin finds logs and what to do when it cannot.',
			),
		);
	}

	private static function read_resource( $params ) {
		$uri = $params['uri'] ?? '';
		if ( 'agent-metrics://summary' === $uri ) {
			$rollup = AM_Reports::get();
			$text   = false === $rollup ? 'Parse in progress or failed.' : wp_json_encode( $rollup, JSON_PRETTY_PRINT );
			return array( 'contents' => array( array( 'uri' => $uri, 'mimeType' => 'application/json', 'text' => $text ) ) );
		}
		if ( 'agent-metrics://docs/setup' === $uri ) {
			$text = "The plugin looks for server access logs in these locations, in order:\n"
				. "- AM_LOG_PATH or AM_LOG_DIR constant (developer override)\n"
				. "- /home/<user>/logs/access.log* (cPanel)\n"
				. "- /var/log/nginx/access.log*\n"
				. "- /var/log/apache2/access.log*\n"
				. "- /var/log/httpd/access_log*\n\n"
				. "It reads only the last ~2MB of the newest file. If none are readable, call log_status\n"
				. "and check the diagnostics: the site may be on a managed host (WP Engine, Kinsta) that\n"
				. "does not expose raw logs, or the files may need a permission fix.";
			return array( 'contents' => array( array( 'uri' => $uri, 'mimeType' => 'text/markdown', 'text' => $text ) ) );
		}
		throw new Exception( 'Unknown resource: ' . $uri );
	}
}
