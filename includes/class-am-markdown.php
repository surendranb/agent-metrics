<?php
defined( 'ABSPATH' ) || exit;

class AM_Markdown {

	const OPTION    = 'am_agent_activity';
	const QUERY_VAR = 'am_markdown';

	public static function enabled() {
		return '1' === (string) get_option( self::OPTION, '1' );
	}

	public static function init() {
		add_rewrite_rule( '^(.+?)\.md/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function activate() {
		self::init();
		flush_rewrite_rules( false );
	}

	public static function flush() {
		flush_rewrite_rules( false );
	}

	public static function rest_init() {
		if ( ! self::enabled() ) {
			return;
		}
		register_rest_route(
			'agent-metrics/v1',
			'/page-markdown',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'callback'            => array( __CLASS__, 'rest_get' ),
			)
		);
	}

	public static function rest_get( $request ) {
		$post = self::find( $request->get_param( 'slug' ) );
		if ( ! $post ) {
			return new WP_Error( 'am_not_found', 'Page not found.', array( 'status' => 404 ) );
		}
		return array(
			'slug'      => $post->post_name,
			'title'     => get_the_title( $post ),
			'markdown'  => self::render( $post->ID ),
			'canonical' => get_permalink( $post ),
		);
	}

	public static function maybe_serve() {
		global $wp;
		if ( ! self::enabled() ) {
			// .md rewrite still matches while off — 404 instead of canonical-redirecting agents to HTML
			if ( ! empty( $wp->query_vars[ self::QUERY_VAR ] ) ) {
				self::not_found();
			}
			return;
		}
		$slug = isset( $wp->query_vars[ self::QUERY_VAR ] ) ? $wp->query_vars[ self::QUERY_VAR ] : '';
		if ( $slug ) {
			self::serve( sanitize_text_field( $slug ), false );
			return;
		}
		if ( ! is_singular( array( 'page', 'post' ) ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		// ponytail: front page has no path in its permalink — fall back to the slug so /home.md resolves
		$path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
		$path = rtrim( (string) $path, '/' );
		if ( '' === $path ) {
			$path = '/' . $post->post_name;
		}
		$md_url = home_url( $path . '.md' );
		header( 'Link: <' . esc_url( $md_url ) . '>; rel="alternate"; type="text/markdown", <' . esc_url( home_url( '/llms.txt' ) ) . '>; rel="describedby"' );
		header( 'Vary: Accept' );
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		if ( false !== stripos( $accept, 'text/markdown' ) ) {
			self::serve( $post->post_name, true );
		}
	}

	private static function serve( $slug, $negotiated ) {
		$post = self::find( $slug );
		if ( ! $post ) {
			self::not_found();
		}
		$html_url = get_permalink( $post );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Link: <' . esc_url( $html_url ) . '>; rel="canonical"' );
		header( 'Vary: Accept' );
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		AM_Storage::insert_agent_event(
			$negotiated ? wp_parse_url( $html_url, PHP_URL_PATH ) : '/' . $slug . '.md',
			$ua
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Direct text/markdown output.
		echo self::render( $post->ID );
		exit;
	}

	private static function not_found() {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		exit;
	}

	public static function find( $slug ) {
		$post = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}
		return $post;
	}

	public static function render( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return '';
		}
		$title     = get_the_title( $post );
		$canonical = get_permalink( $post );
		$desc = $post->post_excerpt;
		if ( ! $desc ) {
			$desc = wp_trim_words( wp_strip_all_tags( preg_replace( '~</(p|li|td|th|h[1-6]|blockquote|figcaption)>~i', "\n", $post->post_content ) ), 30, '…' );
		}
		$date = gmdate( 'Y-m-d', strtotime( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date ) );
		$body = self::blocks_to_markdown( parse_blocks( $post->post_content ) );
		$ld   = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'Article',
			'headline'     => $title,
			'description'  => $desc,
			'url'          => $canonical,
			'datePublished' => gmdate( 'c', strtotime( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date ) ),
		);
		return '---' . "\n"
			. 'title: ' . self::yaml( $title ) . "\n"
			. 'description: ' . self::yaml( $desc ) . "\n"
			. 'canonical: ' . self::yaml( $canonical ) . "\n"
			. 'date: ' . $date . "\n"
			. '---' . "\n\n"
			. $body . "\n\n"
			. '<script type="application/ld+json">' . "\n"
			. wp_json_encode( $ld, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
			. '</script>' . "\n";
	}

	private static function yaml( $value ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $value ) . '"';
	}

	public static function blocks_to_markdown( $blocks ) {
		$out = array();
		foreach ( (array) $blocks as $block ) {
			$md = self::block( $block );
			if ( '' !== $md ) {
				$out[] = $md;
			}
		}
		return implode( "\n\n", $out );
	}

	private static function block( $b ) {
		if ( ! is_array( $b ) || empty( $b['blockName'] ) ) {
			return '';
		}
		$name = $b['blockName'];
		$html = isset( $b['innerHTML'] ) ? self::strip_comments( $b['innerHTML'] ) : '';

		switch ( $name ) {
			case 'core/heading':
				$level = max( 1, min( 6, (int) ( $b['attrs']['level'] ?? 2 ) ) );
				return str_repeat( '#', $level ) . ' ' . self::inline( $html );
			case 'core/paragraph':
				return self::inline( $html );
			case 'core/list':
				return self::list_items( $b, ! empty( $b['attrs']['ordered'] ), '' );
			case 'core/code':
				$code = html_entity_decode( (string) ( $b['attrs']['content'] ?? self::text( $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				return "```\n" . rtrim( $code, "\n" ) . "\n```";
			case 'core/preformatted':
				return "```\n" . rtrim( self::entity_decode( self::strip_tags_keep_newlines( $html ) ), "\n" ) . "\n```";
			case 'core/quote':
				return self::quote( $b );
			case 'core/table':
				return self::table( $b );
			case 'core/image':
				return self::image( $b, $html );
			case 'core/html':
			case 'core/shortcode':
			case 'core/freeform':
			case 'core/separator':
			case 'core/spacer':
			case 'core/more':
			case 'core/nextpage':
				return '';
			default:
				return self::inline( $html );
		}
	}

	private static function list_items( $block, $ordered, $indent ) {
		$lines = array();
		$n     = 0;
		$items = $block['innerBlocks'];
		if ( $items ) {
			foreach ( $items as $item ) {
				$n++;
				$marker    = $ordered ? $n . '. ' : '- ';
				$inner     = $item['innerHTML'] ?? '';
				$own_parts = array();
				foreach ( $item['innerContent'] as $part ) {
					if ( null !== $part ) {
						$own_parts[] = $part;
					}
				}
				$own = implode( '', $own_parts );
				foreach ( $item['innerBlocks'] as $sub ) {
					$own = str_replace( (string) ( $sub['innerHTML'] ?? '' ), '', $own );
				}
				$lines[] = $indent . $marker . self::inline( self::strip_comments( $own ) );
				$nested  = '';
				foreach ( $item['innerBlocks'] as $sub ) {
					if ( 'core/list' === ( $sub['blockName'] ?? '' ) ) {
						$nested = self::list_items( $sub, ! empty( $sub['attrs']['ordered'] ), $indent . '  ' );
					}
				}
				if ( $nested ) {
					$lines[] = $nested;
				}
			}
		} else {
			if ( preg_match_all( '~<li[^>]*>(.*?)</li>~is', $block['innerHTML'] ?? '', $m ) ) {
				foreach ( $m[1] as $li ) {
					$n++;
					$lines[] = $indent . ( $ordered ? $n . '. ' : '- ' ) . self::inline( $li );
				}
			}
		}
		return implode( "\n", $lines );
	}

	private static function quote( $b ) {
		$inner = self::blocks_to_markdown( $b['innerBlocks'] );
		if ( ! $inner ) {
			$inner = self::inline( preg_replace( array( '~<blockquote[^>]*>|</blockquote>~i', '~<cite[^>]*>.*?</cite>~is' ), '', (string) ( $b['innerHTML'] ?? '' ) ) );
		}
		$out = '';
		foreach ( preg_split( '/\n/', $inner ) as $line ) {
			$out .= '> ' . $line . "\n";
		}
		$citation = (string) ( $b['attrs']['citation'] ?? '' );
		if ( '' === $citation && preg_match( '~<cite[^>]*>(.*?)</cite>~is', $b['innerHTML'] ?? '', $m ) ) {
			$citation = $m[1];
		}
		if ( '' !== trim( self::text( $citation ) ) ) {
			$out .= '> — ' . self::text( $citation ) . "\n";
		}
		return rtrim( $out );
	}

	private static function table( $b ) {
		$attrs = $b['attrs'] ?? array();
		$rows  = array();
		if ( ! empty( $attrs['head'] ) || ! empty( $attrs['body'] ) ) {
			$head = array();
			foreach ( (array) ( $attrs['head'] ?? array() ) as $row ) {
				$head = self::cells( $row['cells'] ?? array() );
			}
			foreach ( (array) ( $attrs['body'] ?? array() ) as $row ) {
				$rows[] = self::cells( $row['cells'] ?? array() );
			}
			if ( ! $head && $rows ) {
				$head = array_shift( $rows );
			}
			if ( ! $head && ! $rows ) {
				return '';
			}
			$lines = array( '| ' . implode( ' | ', $head ) . ' |' );
		} else {
			if ( ! preg_match_all( '~<tr[^>]*>(.*?)</tr>~is', $b['innerHTML'] ?? '', $tr ) ) {
				return '';
			}
			$lines = array();
			foreach ( $tr[1] as $i => $row ) {
				if ( preg_match_all( '~<t[hd][^>]*>(.*?)</t[hd]>~is', $row, $td ) ) {
					$rows[] = array_map( array( __CLASS__, 'inline' ), $td[1] );
				}
			}
			if ( ! $rows ) {
				return '';
			}
			$head = array_shift( $rows );
			$lines[] = '| ' . implode( ' | ', $head ) . ' |';
		}
		$lines[] = '| ' . implode( ' | ', array_fill( 0, count( $head ), '---' ) ) . ' |';
		foreach ( $rows as $row ) {
			$lines[] = '| ' . implode( ' | ', $row ) . ' |';
		}
		return implode( "\n", $lines );
	}

	private static function cells( $cells ) {
		$out = array();
		foreach ( (array) $cells as $cell ) {
			$out[] = self::inline( (string) ( $cell['content'] ?? '' ) );
		}
		return $out;
	}

	private static function image( $b, $html ) {
		$alt = (string) ( $b['attrs']['alt'] ?? '' );
		$src = (string) ( $b['attrs']['url'] ?? '' );
		if ( preg_match( '~<img[^>]*~is', $html, $m ) ) {
			$tag = $m[0];
			if ( preg_match( '~\salt=["\']([^"\']*)["\']~i', $tag, $a ) && '' === $alt ) {
				$alt = $a[1];
			}
			if ( preg_match( '~\ssrc=["\']([^"\']*)["\']~i', $tag, $s ) && '' === $src ) {
				$src = $s[1];
			}
		}
		if ( ! $src ) {
			return '';
		}
		return '[' . self::text( $alt ) . '](' . $src . ')';
	}

	private static function inline( $html ) {
		$html = self::strip_comments( (string) $html );
		$html = preg_replace_callback(
			'~<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)</a>~is',
			function ( $m ) {
				return '[' . self::text( $m[2] ) . '](' . $m[1] . ')';
			},
			$html
		);
		$html = preg_replace_callback(
			'~<img[^>]*~is',
			function ( $m ) {
				$alt = preg_match( '~\salt=["\']([^"\']*)["\']~i', $m[0], $a ) ? $a[1] : '';
				$src = preg_match( '~\ssrc=["\']([^"\']*)["\']~i', $m[0], $s ) ? $s[1] : '';
				return $src ? '[' . self::text( $alt ) . '](' . $src . ')' : '';
			},
			$html
		);
		$html = preg_replace( '~<br\s*/?>~i', ' ', $html );
		return self::text( $html );
	}

	private static function text( $html ) {
		$t = wp_strip_all_tags( self::strip_comments( (string) $html ) );
		$t = self::entity_decode( $t );
		$t = preg_replace( '/\s+/', ' ', $t );
		return trim( $t );
	}

	private static function strip_tags_keep_newlines( $html ) {
		return wp_strip_all_tags( (string) $html );
	}

	private static function entity_decode( $s ) {
		return html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	private static function strip_comments( $html ) {
		return preg_replace(
			array( '~<!--\s*/?wp:.*?-->~s', '~<(script|style)\b[^>]*>.*?</\1>~is', '~<(script|style)\b[^>]*/?>~is' ),
			'',
			(string) $html
		);
	}
}
