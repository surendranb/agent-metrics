<?php
defined( 'ABSPATH' ) || exit;

class AM_Brief {

	public static function get( $rollup = null ) {
		if ( null === $rollup ) {
			$rollup = AM_Rollup::get();
			if ( false === $rollup ) {
				return null;
			}
		}
		if ( empty( $rollup['days'] ) ) {
			return null;
		}
		$dates = array_keys( $rollup['days'] );
		sort( $dates );
		$date = end( $dates );

		$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
		if ( $yesterday === $date ) {
			$label = 'yesterday';
		} elseif ( gmdate( 'Y-m-d' ) === $date ) {
			$label = 'today';
		} else {
			$label = 'last activity: ' . $date;
		}

		$seen_before = array();
		foreach ( $dates as $d ) {
			if ( $d >= $date ) {
				break;
			}
			foreach ( $rollup['days'][ $d ] as $slug => $n ) {
				$seen_before[ $slug ] = true;
			}
		}

		$top_bots = array();
		$new_bots = array();
		foreach ( $rollup['days'][ $date ] as $slug => $n ) {
			$row        = array(
				'bot'      => $slug,
				'name'     => $rollup['bots'][ $slug ]['name'] ?? $slug,
				'category' => $rollup['bots'][ $slug ]['category'] ?? 'unknown',
				'hits'     => $n,
			);
			$top_bots[] = $row;
			if ( ! isset( $seen_before[ $slug ] ) ) {
				$new_bots[] = $row;
			}
		}
		usort(
			$top_bots,
			function ( $a, $b ) {
				return $b['hits'] <=> $a['hits'];
			}
		);
		usort(
			$new_bots,
			function ( $a, $b ) {
				return $b['hits'] <=> $a['hits'];
			}
		);

		$intents = array();
		foreach ( $rollup['days'][ $date ] as $slug => $n ) {
			$cat             = $rollup['bots'][ $slug ]['category'] ?? 'unknown';
			$intents[ $cat ] = ( $intents[ $cat ] ?? 0 ) + $n;
		}
		arsort( $intents );

		$day_pages = $rollup['day_pages'][ $date ] ?? array();
		$top_pages = array();
		$new_pages = array();
		foreach ( $day_pages as $path => $n ) {
			$top_pages[] = array(
				'path' => $path,
				'hits' => $n,
			);
		}
		usort(
			$top_pages,
			function ( $a, $b ) {
				return $b['hits'] <=> $a['hits'];
			}
		);

		$seen_pages = array();
		foreach ( $dates as $d ) {
			if ( $d >= $date ) {
				break;
			}
			foreach ( ( $rollup['day_pages'][ $d ] ?? array() ) as $path => $n ) {
				$seen_pages[ $path ] = true;
			}
		}
		foreach ( $day_pages as $path => $n ) {
			if ( ! isset( $seen_pages[ $path ] ) ) {
				$new_pages[] = array(
					'path' => $path,
					'hits' => $n,
				);
			}
		}
		usort(
			$new_pages,
			function ( $a, $b ) {
				return $b['hits'] <=> $a['hits'];
			}
		);

		$trend_days = array();
		foreach ( $dates as $d ) {
			$trend_days[] = array(
				'date' => $d,
				'hits' => array_sum( $rollup['days'][ $d ] ),
			);
		}
		$trend_days = array_slice( $trend_days, -30 );

		return array(
			'date'      => $date,
			'label'     => $label,
			'top_bots'  => array_slice( $top_bots, 0, 3 ),
			'top_pages' => array_slice( $top_pages, 0, 3 ),
			'new_bots'  => $new_bots,
			'new_pages' => $new_pages,
			'intents'   => $intents,
			'trend'     => array(
				'days'       => $trend_days,
				'total'      => array_sum( $rollup['days'][ $date ] ),
				'prev_total' => isset( $dates[ count( $dates ) - 2 ] ) ? array_sum( $rollup['days'][ $dates[ count( $dates ) - 2 ] ] ) : 0,
			),
		);
	}
}
