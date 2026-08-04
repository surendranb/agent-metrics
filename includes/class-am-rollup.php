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
		$status = get_option( 'am_parse_status', array() );
		if ( ! empty( $status['generated'] ) && time() - (int) $status['generated'] < self::interval() ) {
			return AM_Reports::get();
		}
		if ( time() - (int) get_option( self::GUARD ) < self::GUARD_TTL ) {
			return AM_Reports::get();
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
		$status = array(
			'generated'   => time(),
			'log_path'    => $probe['path'],
			'error'       => $probe['error'],
			'diagnostics' => $probe['diagnostics'],
			'skipped'     => 0,
		);
		if ( $probe['path'] ) {
			$cursor = AM_Storage::cursor();
			$inode  = (string) @fileinode( $probe['path'] );
			$offset = ( $cursor['path'] ?? null ) === $probe['path'] && ( $cursor['inode'] ?? '' ) === $inode ? (int) ( $cursor['offset'] ?? 0 ) : 0;
			$read = AM_Log_Reader::read_from( $probe['path'], $offset );
			foreach ( $read['lines'] as $record ) {
				$hit = AM_Parser::parse( $record['line'] );
				if ( ! $hit ) {
					$status['skipped']++;
					continue;
				}
				$bot = AM_Bot_Catalog::match( $hit['ua'] );
				AM_Storage::insert( $hit, $bot, $probe['path'], $read['inode'], $record['offset'] );
			}
			AM_Storage::save_cursor( array( 'path' => $probe['path'], 'inode' => $read['inode'], 'offset' => $read['offset'], 'updated' => time() ) );
		}
		update_option( 'am_parse_status', $status, false );
		return AM_Reports::get();
	}

	public static function invalidate() {
		delete_transient( self::TRANSIENT );
		$status = get_option( 'am_parse_status', array() );
		$status['generated'] = 0;
		update_option( 'am_parse_status', $status, false );
	}
}
