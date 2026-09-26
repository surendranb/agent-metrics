<?php
defined( 'ABSPATH' ) || exit;

class InPlainSite_Rollup {

	const TRANSIENT   = 'inplainsite_rollup';
	const GUARD       = 'inplainsite_last_parse_attempt';
	const GUARD_TTL   = 120;
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
				unset( $existing['days'][ $day ] );
			}
		}
		foreach ( array_keys( $existing['day_pages'] ) as $day ) {
			if ( $day < $cutoff ) {
				unset( $existing['day_pages'][ $day ] );
			}
		}

		$active_bots = array();
		foreach ( $existing['days'] as $slugs ) {
			foreach ( $slugs as $slug => $n ) {
				$active_bots[ $slug ] = true;
			}
		}
		foreach ( array_keys( $existing['bots'] ) as $slug ) {
			if ( empty( $active_bots[ $slug ] ) ) {
				unset( $existing['bots'][ $slug ] );
			}
		}

		$active_pages = array();
		foreach ( $existing['day_pages'] as $paths ) {
			foreach ( $paths as $path => $n ) {
				$active_pages[ $path ] = ( $active_pages[ $path ] ?? 0 ) + $n;
			}
		}
		$existing['pages'] = $active_pages;

		return $existing;
	}

	public static function get() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$status = get_option( 'inplainsite_parse_status', array() );
		if ( ! empty( $status['generated'] ) && time() - (int) $status['generated'] < self::interval() ) {
			return self::cache( InPlainSite_Reports::get() );
		}
		if ( time() - (int) get_option( self::GUARD ) < self::GUARD_TTL ) {
			return self::cache( InPlainSite_Reports::get() );
		}
		update_option( self::GUARD, time() );
		return self::refresh();
	}

	public static function cache( $report ) {
		set_transient( self::TRANSIENT, $report, self::interval() );
		return $report;
	}

	public static function interval() {
		$min = (int) get_option( 'inplainsite_parse_interval_minutes', 0 );
		if ( 0 === $min ) {
			$last = get_transient( self::TRANSIENT );
			$min  = ( is_array( $last ) && ! empty( $last['recommended_interval_min'] ) ) ? (int) $last['recommended_interval_min'] : 5;
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
		$probe  = InPlainSite_Prober::probe();
		$status = array(
			'generated'   => time(),
			'log_path'    => $probe['path'],
			'error'       => $probe['error'],
			'diagnostics' => $probe['diagnostics'],
			'skipped'     => 0,
		);
		if ( $probe['path'] ) {
			$cursor = InPlainSite_Storage::cursor();
			$inode  = (string) @fileinode( $probe['path'] );
			$offset = ( $cursor['path'] ?? null ) === $probe['path'] && ( $cursor['inode'] ?? '' ) === $inode ? (int) ( $cursor['offset'] ?? 0 ) : 0;
			$read   = InPlainSite_Log_Reader::read_from( $probe['path'], $offset );
			foreach ( $read['lines'] as $record ) {
				$hit = InPlainSite_Parser::parse( $record['line'] );
				if ( ! $hit ) {
					++$status['skipped'];
					continue;
				}
				// ponytail: skip static assets and WP internals — only track content URLs
				if ( self::is_noise( $hit['path'] ) ) {
					++$status['skipped'];
					continue;
				}
				$bot = InPlainSite_Bot_Catalog::match( $hit['ua'] );
				InPlainSite_Storage::insert( $hit, $bot, $probe['path'], $read['inode'], $record['offset'] );
			}
			InPlainSite_Storage::save_cursor(
				array(
					'path'    => $probe['path'],
					'inode'   => $read['inode'],
					'offset'  => $read['offset'],
					'updated' => time(),
				)
			);
		}
		update_option( 'inplainsite_parse_status', $status, false );
		InPlainSite_Storage::prune();
		return self::cache( InPlainSite_Reports::get() );
	}

	public static function invalidate() {
		delete_transient( self::TRANSIENT );
		$status              = get_option( 'inplainsite_parse_status', array() );
		$status['generated'] = 0;
		update_option( 'inplainsite_parse_status', $status, false );
	}

	/**
	 * Returns true if the path is a static asset or WordPress internal route.
	 * ponytail: extension list covers every common web asset; upgrade path is
	 * a user-configurable exclusion list if someone needs to track .json or similar.
	 */
	private static function is_noise( $path ) {
		$path = strtolower( $path );
		// Strip query string for extension check.
		$clean = strtok( $path, '?' );

		// Static asset extensions.
		$static = array(
			'.css',
			'.js',
			'.map',
			'.png',
			'.jpg',
			'.jpeg',
			'.gif',
			'.ico',
			'.svg',
			'.webp',
			'.avif',
			'.bmp',
			'.woff',
			'.woff2',
			'.ttf',
			'.eot',
			'.otf',
			'.mp4',
			'.webm',
			'.ogg',
			'.mp3',
			'.wav',
			'.pdf',
			'.zip',
			'.gz',
			'.tar',
			'.rar',
		);
		foreach ( $static as $ext ) {
			if ( substr( $clean, -strlen( $ext ) ) === $ext ) {
				return true;
			}
		}

		// WordPress internal paths.
		if ( preg_match( '#^/(wp-admin|wp-includes|wp-cron|wp-json/wp/|wp-json/oembed)#', $path ) ) {
			return true;
		}

		return false;
	}
}
