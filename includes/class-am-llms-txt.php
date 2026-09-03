<?php
/**
 * Surface A — llms.txt / llms-full.txt: hit-weighted site map, manual pins.
 *
 * Curation loop: the ordering below is computed from the same hits table the
 * metrics pipeline fills, so real agent interest reshapes what future agents
 * see first. Weighting rule (documented, stable):
 *   score = 10 * agent-activity rows on the page URL (LlmsTxt, MarkdownFetch, WebMCP:*)
 *         +  1 * search/on-demand bot rows on the page URL
 *   order: score DESC, then menu_order ASC, then title ASC. Zero-activity pages
 *   fall back to menu order. Pins (option `am_llms_pins`, array of page IDs,
 *   filter `am_llms_pins`) go first in pin order. Hard cap: 100 pages.
 *
 * @package agent-metrics
 */

defined( 'ABSPATH' ) || exit;

class AM_Llms_Txt {

	const OPTION   = 'am_llms_pins';
	const LIMIT    = 100;
	const W_AGENT  = 10;
	const W_SEARCH = 1;

	public static function maybe_serve() {
		if ( ! AM_Markdown::enabled() ) {
			return;
		}
		$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path    = wp_parse_url( $raw_uri, PHP_URL_PATH );
		$path    = is_string( $path ) ? rtrim( $path, '/' ) : '';
		$full    = '/llms-full.txt' === $path;
		if ( ! $full && '/llms.txt' !== $path && '/.well-known/llms.txt' !== $path ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		status_header( 200 );
		nocache_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text representation for llms.txt.
		echo $full ? self::full() : self::txt();
		exit;
	}

	public static function txt() {
		$lines   = array(
			'# ' . get_bloginfo( 'name' ),
			'',
			'> ' . get_bloginfo( 'description' ),
			'',
		);
		$pages   = self::pages();
		$lines[] = "## Pages\n";
		foreach ( $pages as $post ) {
			$lines[] = '- [' . get_the_title( $post ) . '](' . get_permalink( $post ) . '): ' . self::blurb( $post );
		}
		return implode( "\n", $lines ) . "\n";
	}

	public static function full() {
		$out = array();
		foreach ( self::pages() as $post ) {
			$doc = AM_Markdown::render( $post->ID );
			if ( '' !== $doc ) {
				$out[] = '<!-- page: ' . $post->post_name . ' -->' . "\n\n" . $doc;
			}
		}
		return implode( "\n\n---\n\n", $out ) . "\n";
	}

	/**
	 * Published page/post list in curated order. Scored per the class docblock.
	 *
	 * @return WP_Post[]
	 */
	public static function pages() {
		$posts = get_posts(
			array(
				'post_type'        => array( 'page', 'post' ),
				'post_status'      => 'publish',
				'numberposts'      => self::LIMIT,
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$by_key = array();
		foreach ( $posts as $i => $post ) {
			$permalink = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
			$by_key[ self::key( $permalink ) ]  = $i;
			$by_key[ self::key( '/' . $post->post_name ) ] = $i;
		}

		global $wpdb;
		$table = AM_Storage::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be a placeholder.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT path, SUM( CASE WHEN intent = %s THEN %d ELSE %d END ) AS score
				FROM {$table}
				WHERE intent = %s OR intent IN ('search','on-demand')
				GROUP BY path",
				'agent-activity',
				self::W_AGENT,
				self::W_SEARCH,
				'agent-activity'
			)
		);
		$scores = array_fill( 0, count( $posts ), 0 );
		foreach ( (array) $rows as $row ) {
			$key = self::key( $row->path );
			if ( isset( $by_key[ $key ] ) ) {
				$scores[ $by_key[ $key ] ] += (int) $row->score;
			}
		}

		$ordered = $posts;
		array_multisort(
			$scores, SORT_DESC, SORT_NUMERIC,
			array_map( 'intval', wp_list_pluck( $posts, 'menu_order' ) ), SORT_ASC, SORT_NUMERIC,
			array_map( 'strval', wp_list_pluck( $posts, 'post_title' ) ), SORT_ASC, SORT_STRING,
			$ordered
		);

		$pins = array();
		foreach ( (array) apply_filters( 'am_llms_pins', get_option( self::OPTION, array() ) ) as $id ) {
			foreach ( $ordered as $i => $post ) {
				if ( (int) $post->ID === (int) $id ) {
					$pins[] = $post;
					unset( $ordered[ $i ] );
					break;
				}
			}
		}
		return array_merge( $pins, array_values( $ordered ) );
	}

	private static function key( $path ) {
		$p = rtrim( (string) $path, '/' );
		if ( '.md' === substr( $p, -3 ) ) {
			$p = substr( $p, 0, -3 );
		}
		return $p;
	}

	private static function blurb( $post ) {
		$desc = $post->post_excerpt;
		if ( ! $desc ) {
			$desc = wp_strip_all_tags( preg_replace( '~</(p|li|td|th|h[1-6]|blockquote|figcaption)>~i', "\n", $post->post_content ) );
		}
		return wp_trim_words( preg_replace( '/\s+/', ' ', $desc ), 30, '…' );
	}
}
