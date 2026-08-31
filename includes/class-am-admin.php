<?php
defined( 'ABSPATH' ) || exit;

class AM_Admin {

	private static $charts = array();

	const CONSENT      = 'am_telemetry_consent';
	const CONSENT_RMD  = 'am_telemetry_consent_remind';

	public static function menu() {
		add_menu_page( 'AI Bot Traffic', 'AI Bot Traffic', 'manage_options', 'agent-metrics', array( __CLASS__, 'render' ), 'dashicons-chart-area', 26 );
	}

	public static function handle_consent() {
		$choice = isset( $_GET['am_consent'] ) ? sanitize_key( $_GET['am_consent'] ) : '';
		if ( ! in_array( $choice, array( 'yes', 'later', 'no' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'am_consent' );
		if ( 'yes' === $choice ) {
			AM_Telemetry::set_enabled( true );
			update_option( self::CONSENT, 'yes', false );
		} elseif ( 'later' === $choice ) {
			update_option( self::CONSENT, 'later', false );
			update_option( self::CONSENT_RMD, time() + 7 * DAY_IN_SECONDS, false );
		} else {
			update_option( self::CONSENT, 'no', false );
		}
		wp_safe_redirect( remove_query_arg( array( 'am_consent', '_wpnonce' ) ) );
		exit;
	}

	public static function consent_notice() {
		if ( ! current_user_can( 'manage_options' ) || AM_Telemetry::enabled() ) {
			return;
		}
		$consent = get_option( self::CONSENT, '' );
		if ( 'yes' === $consent || 'no' === $consent ) {
			return;
		}
		if ( 'later' === $consent && (int) get_option( self::CONSENT_RMD, 0 ) > time() ) {
			return;
		}
		$yes    = wp_nonce_url( add_query_arg( 'am_consent', 'yes' ), 'am_consent' );
		$later  = wp_nonce_url( add_query_arg( 'am_consent', 'later' ), 'am_consent' );
		$no     = wp_nonce_url( add_query_arg( 'am_consent', 'no' ), 'am_consent' );
		$privacy = 'https://builditwithai.xyz/privacy/';
		?>
		<div class="notice notice-info" style="background:#fef6e4;border-left-color:#8bd3dd;padding:14px 16px">
			<p style="margin:0 0 8px;font-weight:600;color:#001858">Help improve AI Bot Traffic Analytics</p>
			<p style="margin:0 0 8px;color:#172c66">Optional anonymous diagnostics help me fix bugs and plan features. With your consent, the plugin shares health events only: version numbers, parse status and timing, tool usage, and error messages. <a href="<?php echo esc_url( $privacy ); ?>" target="_blank" rel="noopener noreferrer">Read the full list of events and fields</a>.</p>
			<p style="margin:0;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
				<a href="<?php echo esc_url( $yes ); ?>" class="button button-primary" style="background:#f582ae;border-color:#f582ae;color:#001858">Enable diagnostics</a>
				<a href="<?php echo esc_url( $later ); ?>" class="button">Remind me later</a>
				<a href="<?php echo esc_url( $no ); ?>" class="button">Decline</a>
			</p>
		</div>
		<?php
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_script( 'am-chart', plugins_url( 'assets/vendor/chart.umd.min.js', AM_PLUGIN_DIR . 'agent-metrics.php' ), array(), '4.4.9', true );
		if ( isset( $_POST['am_action'] ) && check_admin_referer( 'am_admin' ) ) {
			if ( 'refresh' === $_POST['am_action'] ) {
				AM_Rollup::invalidate();
			}
			if ( 'regenerate_key' === $_POST['am_action'] ) {
				update_option( 'am_mcp_key', wp_generate_password( 32, false, false ), false );
			}
			if ( 'settings' === $_POST['am_action'] ) {
				$val   = (int) ( $_POST['am_parse_interval_minutes'] ?? 0 );
				$valid = array( 0, 5, 15, 30, 60, 180 );
				update_option( 'am_parse_interval_minutes', in_array( $val, $valid, true ) ? $val : 0 );
				AM_Telemetry::set_enabled( ! empty( $_POST['am_telemetry_enabled'] ) );
				update_option( self::CONSENT, AM_Telemetry::enabled() ? 'yes' : get_option( self::CONSENT, '' ), false );
				update_option( AM_Markdown::OPTION, ! empty( $_POST['am_agent_activity'] ) ? '1' : '0', false );
			}
		}
		$rollup = AM_Rollup::get();
		$tab    = isset( $_GET['am_tab'] ) && in_array( $_GET['am_tab'], array( 'overview', 'bots', 'pages', 'trends', 'settings' ), true ) ? $_GET['am_tab'] : 'overview';
		?>
		<div class="wrap" style="background:#fef6e4;min-height:100vh;margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#172c66">
			<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
				<div>
					<h1 style="margin:0;color:#001858">AI Bot Traffic Analytics</h1>
					<p style="margin:4px 0 0;color:#172c66">Server access logs -> AI bot activity</p>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'am_admin' ); ?>
					<input type="hidden" name="am_action" value="refresh">
					<button type="submit" class="button" style="background:#f582ae;border:none;color:#001858;font-weight:600;padding:6px 16px;border-radius:8px;cursor:pointer">Parse logs now</button>
				</form>
			</div>
			<div style="display:flex;gap:6px;margin:18px 0 16px;border-bottom:2px solid #f3d2c1;padding-bottom:0">
				<?php
				$tabs = array(
					'overview' => 'Overview',
					'bots'     => 'Bots',
					'pages'    => 'Pages',
					'trends'   => 'Trends',
					'settings' => 'Settings',
				);
				foreach ( $tabs as $slug => $label ) :
					$active = $slug === $tab;
					?>
					<a href="<?php echo esc_url( self::tab_url( $slug ) ); ?>"
						style="padding:8px 16px;border-radius:10px 10px 0 0;text-decoration:none;font-weight:600;<?php echo $active ? 'background:#8bd3dd;color:#001858;' : 'background:#f3d2c1;color:#172c66;'; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
			<?php self::render_mcp_panel(); ?>
			<?php self::render_tab( $tab, $rollup ); ?>
			<?php self::emit_chart_js(); ?>
			<script>
			document.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '.am-copy' );
				if ( b ) {
					navigator.clipboard.writeText( b.dataset.copy ).then( function () {
						var old = b.textContent; b.textContent = 'copied';
						setTimeout( function () { b.textContent = old; }, 1200 );
					} );
				}
			} );
			</script>
		</div>
		<?php
	}

	public static function tab_url( $tab ) {
		return add_query_arg( 'am_tab', $tab, admin_url( 'admin.php?page=agent-metrics' ) );
	}

	private static function render_tab( $tab, $rollup ) {
		$brief = is_array( $rollup ) ? AM_Brief::get( $rollup ) : null;
		if ( ! is_array( $rollup ) ) {
			self::render_parsing_notice();
		} elseif ( $rollup['error'] ) {
			self::render_diagnostics( $rollup );
			if ( 'overview' === $tab ) {
				self::render_agent_activity();
			}
		} elseif ( ! $brief && 'settings' !== $tab ) {
			self::render_empty_state();
		} else {
			switch ( $tab ) {
				case 'settings':
					self::render_settings( $rollup );
					break;
				case 'bots':
					self::render_bots( $rollup );
					break;
				case 'pages':
					self::render_pages( $rollup, $brief );
					break;
				case 'trends':
					self::render_trends( $rollup );
					break;
				default:
					self::render_overview( $brief, $rollup );
			}
		}
	}

	private static function render_parsing_notice() {
		?>
		<div class="notice notice-info" style="background:#8bd3dd;color:#001858;padding:14px;border-radius:10px">
			Parse in progress (a request already started within the last 2 minutes). Refresh in a minute.
		</div>
		<?php
	}

	private static function render_diagnostics( $rollup ) {
		?>
		<div style="background:#f3d2c1;color:#001858;padding:18px;border-radius:10px">
			<strong>No readable log file found.</strong>
			<p>Checked these locations:</p>
			<ul style="margin:8px 0 0;padding-left:20px">
				<?php foreach ( $rollup['diagnostics'] as $d ) : ?>
					<li><?php echo esc_html( $d['label'] . ' — ' . $d['status'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p style="margin:10px 0 0">If you are on cPanel, enable raw logs under <em>Metrics &gt; Raw Access Logs</em> and check the path under <em>Metrics &gt; Errors</em> or the file manager. Managed hosts (WP Engine, Kinsta) may not expose raw logs — this plugin cannot help there.</p>
		</div>
		<?php
	}

	private static function render_empty_state() {
		?>
		<div style="background:#fffffe;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,24,88,.08);text-align:center">
			<h2 style="color:#001858;margin:0 0 8px">No AI bot traffic in the logs yet</h2>
			<p style="color:#172c66;margin:0">The brief fills in as soon as your logs show bot traffic. If you just installed, give it a day.</p>
		</div>
		<?php
	}

	private static function render_settings( $rollup ) {
		if ( AM_Telemetry::enabled() && ! get_option( 'am_telemetry_mcp_configured', false ) ) {
			update_option( 'am_telemetry_mcp_configured', true, false );
			AM_Telemetry::send( 'mcp_configured', array( 'status' => 'success' ) );
		}
		$key       = get_option( 'am_mcp_key', '' );
		$endpoint  = rest_url( 'agent-metrics/v1/mcp' );
		$interval  = (int) get_option( 'am_parse_interval_minutes', 0 );
		$auto      = 0 === $interval;
		$current   = $auto ? AM_Rollup::interval() / MINUTE_IN_SECONDS : $interval;
		$rec       = ! empty( $rollup['recommended_interval_min'] ) ? (int) $rollup['recommended_interval_min'] : 30;
		$telemetry = AM_Telemetry::enabled();
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<h2 style="margin:0 0 10px;color:#001858">Parse frequency</h2>
			<form method="post" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
				<?php wp_nonce_field( 'am_admin' ); ?>
				<input type="hidden" name="am_action" value="settings">
				<select name="am_parse_interval_minutes" style="padding:6px 10px;border-radius:8px;border:1px solid #8bd3dd;background:#fff;color:#001858">
					<option value="0" <?php selected( $interval, 0 ); ?>>Auto — adapt to traffic</option>
					<option value="5" <?php selected( $interval, 5 ); ?>>Every 5 minutes</option>
					<option value="15" <?php selected( $interval, 15 ); ?>>Every 15 minutes</option>
					<option value="30" <?php selected( $interval, 30 ); ?>>Every 30 minutes</option>
					<option value="60" <?php selected( $interval, 60 ); ?>>Every hour</option>
					<option value="180" <?php selected( $interval, 180 ); ?>>Every 3 hours</option>
				</select>
				<button type="submit" class="button" style="background:#8bd3dd;border:none;color:#001858;font-weight:600;padding:6px 14px;border-radius:8px;cursor:pointer">Save</button>
				<span style="color:#172c66;font-size:13px">
					<?php if ( $auto ) : ?>
						Auto mode — parsing every <?php echo esc_html( $current ); ?> min based on current volume. Recommended for this traffic level: <strong>every <?php echo esc_html( $rec ); ?> min</strong>.
					<?php else : ?>
						Fixed — parsing every <?php echo esc_html( $current ); ?> min. Recommended for this traffic level: <strong>every <?php echo esc_html( $rec ); ?> min</strong>.
					<?php endif; ?>
				</span>
				<label style="display:flex;align-items:center;gap:6px;color:#172c66;font-size:13px">
					<input type="checkbox" name="am_telemetry_enabled" value="1" <?php checked( $telemetry ); ?>>
					Share anonymous diagnostics (plugin and MCP health only)
				</label>
				<label style="display:flex;align-items:center;gap:6px;color:#172c66;font-size:13px">
					<input type="checkbox" name="am_agent_activity" value="1" <?php checked( AM_Markdown::enabled() ); ?>>
					Agent activity surfaces (markdown, llms.txt, WebMCP bridge)
				</label>
			</form>
			<p style="margin:10px 0 0;color:#172c66;font-size:12px">Optional diagnostics include version, latency, parse health, and error messages. They never include site URLs, page paths, logs, traffic data, or MCP payloads. Cron runs when the site is visited; dashboard and agent reads refresh on demand if data is older than the interval.</p>
			<p style="margin:6px 0 0;color:#172c66;font-size:12px">Agent activity surfaces let AI agents read pages as markdown ({slug}.md), discover the site via /llms.txt, and use in-page WebMCP tools. When off, none of these are served or enqueued.</p>
		</div>

		<div style="background:#fffffe;border-radius:12px;padding:16px;margin-top:14px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
				<div>
					<h2 style="margin:0 0 6px;color:#001858">MCP server for AI agents</h2>
					<p style="margin:0;color:#172c66;font-size:13px">Agents can query this same data over MCP. Connect with <code style="background:#f3d2c1;border-radius:4px;padding:1px 5px">opencode</code>, Claude Code, etc. using the endpoint and key below.</p>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'am_admin' ); ?>
					<input type="hidden" name="am_action" value="regenerate_key">
					<button type="submit" class="button" style="background:#8bd3dd;border:none;color:#001858;font-weight:600;padding:6px 14px;border-radius:8px;cursor:pointer">Regenerate key</button>
				</form>
			</div>
			<p style="margin:12px 0 4px;color:#172c66;font-size:13px">Endpoint</p>
			<code style="background:#fef6e4;border-radius:6px;padding:6px 10px;display:inline-block;color:#001858"><?php echo esc_html( $endpoint ); ?></code>
			<p style="margin:12px 0 4px;color:#172c66;font-size:13px">API key</p>
			<code style="background:#fef6e4;border-radius:6px;padding:6px 10px;display:inline-block;color:#001858"><?php echo esc_html( $key ); ?></code>
			<p style="margin:12px 0 0;color:#172c66;font-size:13px">Tools: <strong>log_status, daily_brief, bot_summary, bot_breakdown, bot_trend, top_pages, agent_activity_summary</strong>. Prompts: <strong>daily-brief, weekly-report, trend-analysis, investigate-spike</strong>.</p>
			<p style="margin:4px 0 0;color:#172c66;font-size:13px">Log file: <code style="background:#fef6e4;border-radius:4px;padding:1px 5px"><?php echo esc_html( $rollup['log_path'] ); ?></code></p>
		</div>
		<?php
	}

	private static function render_bots( $rollup ) {
		$cat      = isset( $_GET['am_cat'] ) && in_array( $_GET['am_cat'], array( 'training', 'search', 'on-demand' ), true ) ? $_GET['am_cat'] : '';
		$bot_rows = array();
		$total    = 0;
		foreach ( $rollup['bots'] as $slug => $b ) {
			if ( $cat && $b['category'] !== $cat ) {
				continue;
			}
			$total     += $b['hits'];
			$bot_rows[] = array(
				'slug'     => $slug,
				'name'     => $b['name'],
				'category' => $b['category'],
				'hits'     => $b['hits'],
			);
		}
		usort(
			$bot_rows,
			function ( $a, $b ) {
				return $b['hits'] <=> $a['hits'];
			}
		);
		$max_bot = $bot_rows ? max( array_column( $bot_rows, 'hits' ) ) : 1;
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
				<h2 style="margin:0;color:#001858">Bots</h2>
				<div style="display:flex;gap:6px">
					<?php
					$chips = array(
						''          => 'All',
						'training'  => 'Training',
						'search'    => 'Search',
						'on-demand' => 'On-demand',
					);
					foreach ( $chips as $val => $label ) :
						$active = $val === $cat;
						?>
						<a href="<?php echo esc_url( $val ? add_query_arg( 'am_cat', $val, self::tab_url( 'bots' ) ) : self::tab_url( 'bots' ) ); ?>"
							style="padding:4px 12px;border-radius:999px;text-decoration:none;font-size:12px;font-weight:600;<?php echo $active ? 'background:#f582ae;color:#001858;' : 'background:#f3d2c1;color:#172c66;'; ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
			<table style="width:100%;border-collapse:collapse">
				<thead><tr style="text-align:left;color:#172c66">
					<th style="padding:6px 8px;font-size:12px;text-transform:uppercase">Bot</th>
					<th style="padding:6px 8px;font-size:12px;text-transform:uppercase">Type</th>
					<th style="padding:6px 8px;font-size:12px;text-transform:uppercase;text-align:right">Hits</th>
					<th style="padding:6px 8px;font-size:12px;text-transform:uppercase">Share</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $bot_rows as $r ) : ?>
					<tr style="border-top:1px solid #f3d2c1">
						<td style="padding:8px;font-weight:600;color:#001858"><?php echo esc_html( $r['name'] ); ?></td>
						<td style="padding:8px;color:#172c66"><?php echo esc_html( $r['category'] ); ?></td>
						<td style="padding:8px;text-align:right;color:#001858"><?php echo esc_html( number_format( $r['hits'] ) ); ?></td>
						<td style="padding:8px">
							<div style="background:#f3d2c1;border-radius:6px;height:8px;width:100%">
								<div style="background:#8bd3dd;border-radius:6px;height:8px;width:<?php echo esc_attr( round( 100 * $r['hits'] / $max_bot ) ); ?>%"></div>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $bot_rows ) : ?>
					<tr><td colspan="4" style="padding:16px;color:#172c66">No AI bot hits<?php echo $cat ? ' in this category' : ''; ?> (yet).</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<p style="margin:10px 0 0;color:#172c66;font-size:12px"><?php echo esc_html( number_format( $total ) ); ?> total hits in this view.</p>
		</div>
		<?php
	}

	private static function render_pages( $rollup, $brief ) {
		$page_rows = array();
		foreach ( $rollup['pages'] as $path => $n ) {
			$page_rows[] = array(
				'path' => $path,
				'hits' => $n,
			);
		}
		usort(
			$page_rows,
			function ( $a, $b ) {
				return $b['hits'] <=> $a['hits'];
			}
		);
		$page_rows = array_slice( $page_rows, 0, 20 );
		$max_page  = $page_rows ? max( array_column( $page_rows, 'hits' ) ) : 1;
		$new_paths = array();
		foreach ( $brief['new_pages'] ?? array() as $p ) {
			$new_paths[ $p['path'] ] = true;
		}
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<h2 style="margin:0 0 10px;color:#001858">Top pages crawled by AI bots</h2>
			<?php foreach ( $page_rows as $p ) : ?>
				<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
					<span style="flex:1;font-size:12px;color:#172c66;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo esc_attr( $p['path'] ); ?>"><?php echo esc_html( $p['path'] ); ?></span>
					<?php if ( isset( $new_paths[ $p['path'] ] ) ) : ?>
						<span style="background:#f582ae;color:#001858;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px">NEW</span>
					<?php endif; ?>
					<div style="width:70px;background:#f3d2c1;border-radius:6px;height:10px">
						<div style="background:#8bd3dd;border-radius:6px;height:10px;width:<?php echo esc_attr( round( 100 * $p['hits'] / $max_page ) ); ?>%"></div>
					</div>
					<span style="width:36px;text-align:right;font-size:12px;color:#001858"><?php echo esc_html( number_format( $p['hits'] ) ); ?></span>
				</div>
			<?php endforeach; ?>
			<?php if ( ! $page_rows ) : ?>
				<p style="color:#172c66">No data yet.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_trends( $rollup ) {
		$days = $rollup['days'];
		ksort( $days );
		$days = array_slice( $days, -30, 30, true );
		$cats = array(
			'training'  => '#f582ae',
			'search'    => '#8bd3dd',
			'on-demand' => '#f3d2c1',
		);
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<h2 style="margin:0 0 10px;color:#001858">30-day trend by type</h2>
			<?php if ( ! $days ) : ?>
				<p style="color:#172c66">No data yet.</p>
			<?php endif; ?>
			<?php
			foreach ( $days as $day => $slugs ) :
				$tot = array_sum( $slugs );
				$seg = array();
				foreach ( $cats as $cat => $color ) {
					$sum = 0;
					foreach ( $slugs as $slug => $n ) {
						if ( ( $rollup['bots'][ $slug ]['category'] ?? 'unknown' ) === $cat ) {
							$sum += $n;
						}
					}
					if ( $sum > 0 ) {
						$seg[] = array(
							'color' => $color,
							'w'     => round( 100 * $sum / $tot, 1 ),
						);
					}
				}
				?>
				<div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
					<span style="width:84px;font-size:12px;color:#172c66"><?php echo esc_html( $day ); ?></span>
					<div style="flex:1;display:flex;background:#f3d2c1;border-radius:6px;height:10px;overflow:hidden">
						<?php foreach ( $seg as $s ) : ?>
							<div style="background:<?php echo esc_attr( $s['color'] ); ?>;width:<?php echo esc_attr( $s['w'] ); ?>%"></div>
						<?php endforeach; ?>
					</div>
					<span style="width:40px;text-align:right;font-size:12px;color:#001858"><?php echo esc_html( number_format( $tot ) ); ?></span>
				</div>
			<?php endforeach; ?>
			<div style="display:flex;gap:14px;margin-top:10px;font-size:12px;color:#172c66">
				<?php foreach ( $cats as $cat => $color ) : ?>
					<span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?php echo esc_attr( $color ); ?>;margin-right:4px"></span><?php echo esc_html( $cat ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_overview( $brief, $rollup ) {
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<h2 style="margin:0 0 12px;color:#001858"><?php echo esc_html( ucfirst( $brief['label'] ) ); ?> — they were here</h2>
			<?php self::render_yesterday_tables( $brief ); ?>
		</div>

		<?php self::render_hits_line( $brief ); ?>
		<?php self::render_type_area( $rollup ); ?>
		<?php self::render_agent_activity(); ?>
		<?php
	}

	private static function render_yesterday_tables( $brief ) {
		$tables = array(
			'Top bots'  => array_map(
				function ( $b ) {
					return array( $b['name'], number_format( $b['hits'] ) );
				},
				array_slice( $brief['top_bots'], 0, 5 )
			),
			'Top pages' => array_map(
				function ( $p ) {
					return array( $p['path'], number_format( $p['hits'] ) );
				},
				array_slice( $brief['top_pages'], 0, 5 )
			),
			'New bots'  => array_map(
				function ( $b ) {
					return array( $b['name'], number_format( $b['hits'] ) );
				},
				array_slice( $brief['new_bots'], 0, 5 )
			),
			'New pages' => array_map(
				function ( $p ) {
					return array( $p['path'], number_format( $p['hits'] ) );
				},
				array_slice( $brief['new_pages'], 0, 5 )
			),
			'Intent'    => array_map(
				function ( $cat, $n ) {
					$label = array(
						'training'  => 'Training',
						'search'    => 'Search',
						'on-demand' => 'On-demand',
					);
					return array( $label[ $cat ] ?? ucfirst( $cat ), number_format( $n ) );
				},
				array_keys( $brief['intents'] ),
				$brief['intents']
			),
		);
		?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
			<?php foreach ( $tables as $title => $rows ) : ?>
				<div>
					<h3 style="margin:0 0 8px;font-size:14px;color:#001858"><?php echo esc_html( $title ); ?></h3>
					<table style="width:100%;border-collapse:collapse;font-size:13px">
						<?php if ( $rows ) : ?>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td style="padding:5px 8px;border-bottom:1px solid #f3d2c1;color:#172c66;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:0;width:100%"><?php echo esc_html( $row[0] ); ?></td>
									<td style="padding:5px 8px;border-bottom:1px solid #f3d2c1;color:#001858;font-weight:600;text-align:right;white-space:nowrap"><?php echo esc_html( $row[1] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td style="padding:5px 8px;color:#172c66">—</td></tr>
						<?php endif; ?>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_agent_activity() {
		$s     = AM_Agent_Activity::summary( 30 );
		$t     = $s['totals'];
		$total = array_sum( $t );
		$cards = array(
			array( 'Markdown fetches', $t['markdown_fetches'] ),
			array( 'llms.txt downloads', $t['llms_txt_downloads'] ),
			array( 'WebMCP executions', $t['webmcp_executions'] ),
		);
		$pages    = array_slice( $s['by_page'], 0, 10 );
		$max_page = $pages ? max( array_column( $pages, 'count' ) ) : 1;
		$tools    = $s['by_tool'];
		arsort( $tools );
		$max_tool = $tools ? max( $tools ) : 1;
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;margin-top:14px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<h2 style="margin:0 0 4px;color:#001858">Agent Activity</h2>
			<p style="margin:0 0 12px;color:#172c66;font-size:13px">Markdown fetches, llms.txt downloads, and in-page WebMCP tool executions — last 30 days.</p>
			<?php if ( ! $total ) : ?>
				<p style="color:#172c66">No agent activity yet — this fills in when agents fetch markdown, download llms.txt, or run WebMCP tools on your pages.</p>
			<?php else : ?>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px">
				<?php foreach ( $cards as $c ) : ?>
					<div style="background:#fef6e4;border-radius:10px;padding:12px;text-align:center">
						<div style="font-size:24px;font-weight:700;color:#001858"><?php echo esc_html( number_format( $c[1] ) ); ?></div>
						<div style="font-size:12px;color:#172c66"><?php echo esc_html( $c[0] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px">
				<div>
					<h3 style="margin:0 0 8px;font-size:14px;color:#001858">WebMCP tools</h3>
					<?php if ( ! $tools ) : ?>
						<p style="color:#172c66;font-size:13px">No WebMCP executions yet.</p>
					<?php else : ?>
						<?php foreach ( $tools as $tool => $n ) : ?>
							<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
								<span style="flex:1;font-size:12px;color:#172c66;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo esc_attr( $tool ); ?>"><?php echo esc_html( $tool ); ?></span>
								<div style="width:70px;background:#f3d2c1;border-radius:6px;height:10px">
									<div style="background:#f582ae;border-radius:6px;height:10px;width:<?php echo esc_attr( round( 100 * $n / $max_tool ) ); ?>%"></div>
								</div>
								<span style="width:36px;text-align:right;font-size:12px;color:#001858"><?php echo esc_html( number_format( $n ) ); ?></span>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<div>
					<h3 style="margin:0 0 8px;font-size:14px;color:#001858">Top pages by agents</h3>
					<?php foreach ( $pages as $p ) : ?>
						<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
							<span style="flex:1;font-size:12px;color:#172c66;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo esc_attr( $p['page'] ); ?>"><?php echo esc_html( $p['page'] ); ?></span>
							<div style="width:70px;background:#f3d2c1;border-radius:6px;height:10px">
								<div style="background:#8bd3dd;border-radius:6px;height:10px;width:<?php echo esc_attr( round( 100 * $p['count'] / $max_page ) ); ?>%"></div>
							</div>
							<span style="width:36px;text-align:right;font-size:12px;color:#001858"><?php echo esc_html( number_format( $p['count'] ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
			$id             = 'am-chart-agent';
			self::$charts[] = array(
				'id'     => $id,
				'config' => array(
					'type'    => 'line',
					'data'    => array(
						'labels'   => array_column( $s['trend'], 'date' ),
						'datasets' => array(
							array(
								'label'                => 'Agent events',
								'data'                 => array_column( $s['trend'], 'count' ),
								'borderColor'          => '#001858',
								'backgroundColor'      => '#001858',
								'pointBackgroundColor' => '#f582ae',
								'pointRadius'          => 3,
								'tension'              => 0.25,
								'fill'                 => false,
							),
						),
					),
					'options' => array(
						'responsive' => true,
						'plugins'    => array( 'legend' => array( 'display' => false ) ),
						'scales'     => array(
							'y' => array(
								'beginAtZero' => true,
								'ticks'       => array( 'precision' => 0 ),
								'grid'        => array( 'color' => '#f3d2c1' ),
							),
							'x' => array( 'grid' => array( 'display' => false ) ),
						),
					),
				),
			);
			?>
			<h3 style="margin:14px 0 10px;font-size:14px;color:#001858">30-day trend</h3>
			<canvas id="<?php echo esc_attr( $id ); ?>" style="width:100%;max-height:220px"></canvas>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_mcp_panel() {
		$key      = get_option( 'am_mcp_key', '' );
		$endpoint = rest_url( 'agent-metrics/v1/mcp' );
		$opencode = wp_json_encode(
			array(
				'mcp' => array(
					'agent-metrics' => array(
						'type'    => 'remote',
						'url'     => $endpoint,
						'headers' => array( 'Authorization' => 'Bearer ' . $key ),
					),
				),
			),
			JSON_UNESCAPED_SLASHES
		);
		$claude   = 'claude mcp add agent-metrics --transport http ' . $endpoint . ' --header "Authorization: Bearer ' . $key . '"';
		$cursor   = 'cursor mcp add agent-metrics --transport http ' . $endpoint . ' --header "Authorization: Bearer ' . $key . '"';
		$clients  = array(
			'opencode'    => array(
				'monogram' => 'oc',
				'bg'       => '#001858',
				'fg'       => '#fef6e4',
				'config'   => $opencode,
			),
			'Claude Code' => array(
				'monogram' => 'CC',
				'bg'       => '#f582ae',
				'fg'       => '#001858',
				'config'   => $claude,
			),
			'Cursor'      => array(
				'monogram' => 'Cu',
				'bg'       => '#8bd3dd',
				'fg'       => '#001858',
				'config'   => $cursor,
			),
		);
		$names    = array_keys( $clients );
		$default  = $clients['opencode'];
		?>
		<div style="display:flex;align-items:center;gap:12px;background:#fffffe;border-radius:12px;padding:10px 14px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,24,88,.08);flex-wrap:wrap">
			<div style="display:flex;gap:8px">
				<?php foreach ( $clients as $name => $c ) : ?>
					<button type="button" class="am-logo" data-config="<?php echo esc_attr( $c['config'] ); ?>"
						title="<?php echo esc_attr( $name ); ?>"
						style="width:38px;height:38px;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:13px;color:<?php echo esc_attr( $c['fg'] ); ?>;background:<?php echo esc_attr( $c['bg'] ); ?>"><?php echo esc_html( $c['monogram'] ); ?></button>
				<?php endforeach; ?>
			</div>
			<input id="am-mcp-config" type="text" readonly
				style="flex:1;min-width:200px;max-width:560px;height:38px;background:#fef6e4;border:1px solid #f3d2c1;border-radius:8px;padding:0 10px;font-family:ui-monospace,Menlo,monospace;font-size:12px;color:#001858"
				value="<?php echo esc_attr( $default['config'] ); ?>">
			<button type="button" id="am-mcp-copy" class="button am-copy"
				style="background:#f582ae;border:none;color:#001858;font-weight:600;padding:8px 18px;border-radius:8px;cursor:pointer">copy</button>
		</div>
		<script>
		(function () {
			var box  = document.getElementById( 'am-mcp-config' );
			var copy = document.getElementById( 'am-mcp-copy' );
			document.querySelectorAll( '.am-logo' ).forEach( function ( l ) {
				l.addEventListener( 'click', function () {
					box.value = l.dataset.config;
					copy.dataset.copy = box.value;
					box.focus();
				} );
			} );
			copy.dataset.copy = box.value;
		})();
		</script>
		<?php
	}

	private static function render_hits_line( $brief ) {
		$trend = $brief['trend'];
		$days  = $trend['days'];
		if ( ! $days ) {
			return;
		}
		$delta          = $trend['prev_total'] > 0 ? round( 100 * ( $trend['total'] - $trend['prev_total'] ) / $trend['prev_total'] ) : null;
		$id             = 'am-chart-line';
		self::$charts[] = array(
			'id'     => $id,
			'config' => array(
				'type'    => 'line',
				'data'    => array(
					'labels'   => array_column( $days, 'date' ),
					'datasets' => array(
						array(
							'label'                => 'Bot hits',
							'data'                 => array_column( $days, 'hits' ),
							'borderColor'          => '#f582ae',
							'backgroundColor'      => '#f582ae',
							'pointBackgroundColor' => '#001858',
							'pointRadius'          => 3,
							'tension'              => 0.25,
							'fill'                 => false,
						),
					),
				),
				'options' => array(
					'responsive' => true,
					'plugins'    => array( 'legend' => array( 'display' => false ) ),
					'scales'     => array(
						'y' => array(
							'beginAtZero' => true,
							'ticks'       => array( 'precision' => 0 ),
							'grid'        => array( 'color' => '#f3d2c1' ),
						),
						'x' => array( 'grid' => array( 'display' => false ) ),
					),
				),
			),
		);
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;margin-top:14px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<h3 style="margin:0 0 10px;color:#001858">30-day trend — bot hits per day</h3>
			<canvas id="<?php echo esc_attr( $id ); ?>" style="width:100%;max-height:260px"></canvas>
			<?php if ( null !== $delta ) : ?>
				<p style="margin:8px 0 0;color:#172c66;font-size:13px"><?php echo $delta >= 0 ? '▲' : '▼'; ?> <?php echo esc_html( abs( $delta ) ); ?>% vs the previous day</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_type_area( $rollup ) {
		$days = $rollup['days'];
		ksort( $days );
		$days = array_slice( $days, -30, 30, true );
		if ( ! $days ) {
			return;
		}
		$cats     = array(
			'training'  => '#f582ae',
			'search'    => '#8bd3dd',
			'on-demand' => '#f3d2c1',
		);
		$labels   = array_keys( $days );
		$datasets = array();
		foreach ( $cats as $cat => $color ) {
			$series = array();
			foreach ( $days as $slugs ) {
				$n = 0;
				foreach ( $slugs as $slug => $h ) {
					if ( ( $rollup['bots'][ $slug ]['category'] ?? '' ) === $cat ) {
						$n += $h;
					}
				}
				$series[] = $n;
			}
			$datasets[] = array(
				'label'           => $cat,
				'data'            => $series,
				'backgroundColor' => $color . '99',
				'borderColor'     => $color,
				'fill'            => true,
				'tension'         => 0.25,
				'stack'           => 'hits',
			);
		}
		$id             = 'am-chart-area';
		self::$charts[] = array(
			'id'     => $id,
			'config' => array(
				'type'    => 'line',
				'data'    => array(
					'labels'   => $labels,
					'datasets' => $datasets,
				),
				'options' => array(
					'responsive' => true,
					'plugins'    => array( 'legend' => array( 'position' => 'bottom' ) ),
					'scales'     => array(
						'y' => array(
							'stacked'     => true,
							'beginAtZero' => true,
							'ticks'       => array( 'precision' => 0 ),
							'grid'        => array( 'color' => '#f3d2c1' ),
						),
						'x' => array(
							'stacked' => true,
							'grid'    => array( 'display' => false ),
						),
					),
				),
			),
		);
		?>
		<div style="background:#fffffe;border-radius:12px;padding:16px;margin-top:14px;box-shadow:0 1px 3px rgba(0,24,88,.08)">
			<h3 style="margin:0 0 10px;color:#001858">30-day trend — by bot type</h3>
			<canvas id="<?php echo esc_attr( $id ); ?>" style="width:100%;max-height:260px"></canvas>
		</div>
		<?php
	}

	private static function emit_chart_js() {
		if ( ! self::$charts ) {
			return;
		}
		$js = 'document.addEventListener("DOMContentLoaded",function(){window.AM_CHARTS=' . wp_json_encode( self::$charts )
			. ';AM_CHARTS.forEach(function(c){var el=document.getElementById(c.id);if(el&&window.Chart){new Chart(el,c.config);}});});';
		wp_add_inline_script( 'am-chart', $js, 'after' );
	}
}
