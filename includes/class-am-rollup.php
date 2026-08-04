<?php
defined( 'ABSPATH' ) || exit;

class AM_Rollup {

	const TRANSIENT = 'am_rollup';
	const GUARD     = 'am_last_parse_attempt';
	const GUARD_TTL = 120;
	const WINDOW_DAYS = 30;

	public static function empty() {
		return array(
			'generated'   => time(),
			'log_path'    => null,
			'error'       => null,
			'diagnostics' => array(),
			'total_lines' => 0,
			'skipped'     => 0,
			'bots'        => array(),
			'days'        => array(),
			'day_pages'   => array(),
			'pages'       => array(),
		);
	}

	public static function merge( $existing, $incremental ) {
		foreach ( $incremental['bots'] as $slug => $b ) {
			if ( ! isset( $existing['bots'][ $slug ] ) ) {
				$existing['bots'][ $slug ] = $b;
			} else {
				$existing['bots'][ $slug ]['hits'] += $b['hits'];
			}
		}
		foreach ( $incremental['days'] as $day => $slugs ) {
			foreach ( $slugs as $slug => $n ) {
				$existing['days'][ $day ][ $slug ] = ( $existing['days'][ $day ][ $slug ] ?? 0 ) + $n;
			}
		}
		foreach ( $incremental['day_pages'] as $day => $paths ) {
			foreach ( $paths as $path => $n ) {
				$existing['day_pages'][ $day ][ $path ] = ( $existing['day_pages'][ $day ][ $path ] ?? 0 ) + $n;
			}
		}
		foreach ( $incremental['pages'] as $path => $n ) {
			$existing['pages'][ $path ] = ( $existing['pages'][ $path ] ?? 0 ) + $n;
		}
		$existing['total_lines'] += $incremental['total_lines'];
		$existing['skipped']     += $incremental['skipped'];

		$cutoff = gmdate( 'Y-m-d', time() - ( self::WINDOW_DAYS - 1 ) * DAY_IN_SECONDS );
		foreach ( array_keys( $existing['days'] ) as $day ) {
			if ( $day < $cutoff ) {
				unset( $existing['days'][ $day ], $existing['day_pages'][ $day ] );
			}
		}
		$bot_meta = $existing['bots'];
		$totals   = array();
		foreach ( $existing['days'] as $slugs ) {
			foreach ( $slugs as $slug => $n ) {
				$totals[ $slug ] = ( $totals[ $slug ] ?? 0 ) + $n;
			}
		}
		$existing['bots'] = array();
		foreach ( $totals as $slug => $n ) {
			$existing['bots'][ $slug ] = array(
				'name'     => $bot_meta[ $slug ]['name'] ?? $slug,
				'category' => $bot_meta[ $slug ]['category'] ?? 'unknown',
				'hits'     => $n,
			);
		}
		$existing['pages'] = array();
		foreach ( $existing['day_pages'] as $paths ) {
			foreach ( $paths as $path => $n ) {
				$existing['pages'][ $path ] = ( $existing['pages'][ $path ] ?? 0 ) + $n;
			}
		}
		return $existing;
	}

	public static function get() {
		$rollup = get_transient( self::TRANSIENT );
		if ( false !== $rollup && time() - $rollup['generated'] < self::interval() ) {
			return $rollup;
		}
		if ( time() - (int) get_option( self::GUARD ) < self::GUARD_TTL ) {
			return $rollup;
		}
		update_option( self::GUARD, time() );
		return self::refresh();
	}

	public static function interval() {
		$min = (int) get_option( 'am_parse_interval_minutes', 0 );
		if ( 0 === $min ) {
			$last = get_transient( self::TRANSIENT );
			$min  = ( is_array( $last ) && ! empty( $last['recommended_interval_min'] ) ) ? (int) $last['recommended_interval_min'] : 30;
		}
		return max( 5, $min ) * MINUTE_IN_SECONDS;
	}

	public static function recommended_interval_minutes( $rollup ) {
		$hits = 0;
		foreach ( $rollup['bots'] as $b ) {
			$hits += $b['hits'];
		}
		// ponytail: hits-per-tail heuristic; switch to growth-rate when logs are large enough to matter
		if ( $hits >= 500 ) {
			return 5;
		}
		if ( $hits >= 100 ) {
			return 15;
		}
		if ( $hits >= 10 ) {
			return 30;
		}
		return 180;
	}

	public static function refresh() {
		$probe = AM_Prober::probe();
		$incr  = array(
			'total_lines' => 0,
			'skipped'     => 0,
			'bots'        => array(),
			'days'        => array(),
			'day_pages'   => array(),
			'pages'       => array(),
		);
		if ( $probe['path'] ) {
			foreach ( AM_Log_Reader::read( $probe['path'] ) as $line ) {
				$incr['total_lines']++;
				$hit = AM_Parser::parse( $line );
				if ( ! $hit ) {
					$incr['skipped']++;
					continue;
				}
				$bot = AM_Bot_Catalog::match( $hit['ua'] );
				if ( ! $bot ) {
					continue;
				}
				$slug = $bot['slug'];
				$day  = gmdate( 'Y-m-d', $hit['ts'] );

				if ( ! isset( $incr['bots'][ $slug ] ) ) {
					$incr['bots'][ $slug ] = array( 'name' => $bot['name'], 'category' => $bot['category'], 'hits' => 0 );
				}
				$incr['bots'][ $slug ]['hits']++;
				$incr['days'][ $day ][ $slug ]            = ( $incr['days'][ $day ][ $slug ] ?? 0 ) + 1;
				$incr['day_pages'][ $day ][ $hit['path'] ] = ( $incr['day_pages'][ $day ][ $hit['path'] ] ?? 0 ) + 1;
				$incr['pages'][ $hit['path'] ]            = ( $incr['pages'][ $hit['path'] ] ?? 0 ) + 1;
			}
		}

		$existing = get_transient( self::TRANSIENT );
		$fresh    = ! is_array( $existing ) || time() - ( $existing['generated'] ?? 0 ) >= self::WINDOW_DAYS * DAY_IN_SECONDS;
		$rollup   = self::merge( $fresh ? self::empty() : $existing, $incr );
		$rollup['generated']   = time();
		$rollup['log_path']    = $probe['path'];
		$rollup['error']       = $probe['error'];
		$rollup['diagnostics'] = $probe['diagnostics'];
		$rollup['recommended_interval_min'] = self::recommended_interval_minutes( $rollup );
		$rollup['interval_min']             = (int) get_option( 'am_parse_interval_minutes', 0 );
		$rollup['next_parse']               = $rollup['generated'] + self::interval();
		set_transient( self::TRANSIENT, $rollup, self::WINDOW_DAYS * DAY_IN_SECONDS );
		return $rollup;
	}

	public static function invalidate() {
		delete_transient( self::TRANSIENT );
	}
}
