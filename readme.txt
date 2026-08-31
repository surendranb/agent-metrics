=== AI Bot Traffic Analytics ===
Contributors: surendranb
Tags: ai, bots, analytics, mcp, crawlers
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 0.5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See which AI bots crawl your WordPress site, what they request, and why — with a built-in MCP server for AI agents.

== Description ==

Agent Metrics turns your server access logs into an AI bot traffic report. It identifies bots such as GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Bytespider, and 40+ others, then groups their activity by intent:

* **Training** — crawlers collecting material for model development.
* **Search** — crawlers building indexes used to answer current questions.
* **On-demand** — fetchers retrieving a page because a person asked an assistant for it.

All data stays on your WordPress site. Traffic data never leaves it; optional diagnostics are covered in the Privacy section below.

= Dashboard =

* Top bots and top pages for the latest activity day.
* Newly detected bots and newly crawled pages.
* Intent breakdown: training vs search vs on-demand.
* 30-day trend charts (line and stacked area).
* Full bot table with category filters.
* Parse status, log diagnostics, and recommended refresh frequency.

= MCP Server =

The plugin exposes a protected JSON-RPC MCP endpoint so AI agents (Claude Code, OpenCode, Cursor, etc.) can query the same data shown in WordPress.

Available tools: `log_status`, `daily_brief`, `bot_summary`, `bot_breakdown`, `bot_trend`, `top_pages`.

Available prompts: `daily-brief`, `weekly-report`, `trend-analysis`, `investigate-spike`.

= Privacy =

* No site URLs, page paths, user agents, or traffic data are ever sent externally.
* Optional anonymous diagnostics (disabled by default) share only version, event type, status, latency, and error messages. Full event list:
  * `telemetry_enabled`, `plugin_activated`, `plugin_deactivated` — no additional fields.
  * `first_parse`, `parse_completed` — status, duration, lines processed, skipped lines, error message.
  * `mcp_configured`, `plugin_heartbeat` — status, storage row count, PHP version, WordPress version.
  * `mcp_started` — status, client name, client version, protocol version.
  * `tool_executed`, `tool_error` — status, tool name, latency, client name, error message.
* Diagnostics are sent to a Cloudflare Worker which forwards to PostHog; the Worker validates and drops anything not on the allowlist.
* The plugin reads your existing server access log — it does not create new tracking scripts or cookies.

== Installation ==

1. Upload the `agent-metrics` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Open **AI Bot Traffic** in the admin menu.

The plugin auto-discovers access logs in common locations (Nginx, Apache, cPanel). If your logs are in a custom path, add this to `wp-config.php`:

    define( 'AM_LOG_PATH', '/path/to/your/access.log' );

== Frequently Asked Questions ==

= What log formats are supported? =

Standard Apache/Nginx combined log format. The parser extracts the request method, path, status code, and user agent from each line.

= Does this work on managed hosting (WP Engine, Kinsta)? =

Only if raw access logs are readable by the web server user. Many managed hosts do not expose raw logs — check with your host.

= Does the plugin track static assets? =

No. As of v0.4.0, CSS, JS, images, fonts, and WordPress internal paths are automatically filtered out. Only content URLs are tracked.

= Is the MCP endpoint secure? =

Yes. It requires authentication via `Authorization: Bearer` header or `X-AM-Key` header. It includes per-IP rate limiting (120 requests/minute). Regenerate the key from Settings if it is ever exposed.

== Screenshots ==

1. Overview dashboard with top bots, top pages, and trend charts.
2. Settings page with MCP connection snippets and parse frequency.

== Changelog ==

= 0.5.0 =
* **Agent Activity**: new measurement layer — `/{slug}.md` endpoint, `Accept: text/markdown` negotiation, `Link` headers, recorded as `agent-activity`/`MarkdownFetch` hits.
* **Agent Activity**: `/llms.txt`, `/.well-known/llms.txt`, and `/llms-full.txt` — ordering curated from real agent activity, manual pins supported.
* **WebMCP bridge**: registers read-only `get_page_content`, `search_site`, `get_site_map` tools via the W3C WebMCP API where the browser supports it; executions beaconed as declared events.
* **MCP**: new `agent_activity_summary` tool (totals, by-tool, by-page, trend); existing analytics tools unchanged.
* **Dashboard**: new Agent Activity section — counters, by-tool table, per-page table, 30-day trend; renders independently of access-log health.

= 0.4.1 =
* **Privacy**: One-time consent notice for optional diagnostics — Enable / Remind me later / Decline.
* **Privacy**: Explicit consent is recorded when diagnostics are enabled from Settings; declining silences the notice.
* **Telemetry**: Add activate/deactivate lifecycle events and PHP/WordPress versions to heartbeat.
* **Telemetry**: Error events include the raw error message for faster debugging (capped, no site data).
* **Performance**: Cache dashboard rollup in a transient; cap log reads at 5000 lines per pass.
* **Performance**: Add index on bot column and 30-day retention pruning.
* **Security**: MCP key no longer autoloads; uninstall removes telemetry consent options and rate-limit transients.
* **Compat**: WordPress Coding Standards fixes across all files (462 auto-fixes).

= 0.4.0 =
* **Security**: Filter out static assets (CSS, JS, images, fonts) and WordPress internal paths — only content URLs are tracked.
* **Security**: Remove query parameter authentication from MCP endpoint (keys in URLs leak to logs).
* **Security**: Add per-IP rate limiting to MCP endpoint (120 req/min).
* **Security**: Remove client IP from telemetry events (GDPR compliance).
* **Security**: Complete uninstall cleanup — drops table and removes all options.
* **Security**: Remove unused client_ip() helper method.
* Version bump to 0.4.0.

= 0.3.0 =
* MCP server with JSON-RPC protocol support.
* Anonymous opt-in telemetry with strict property allowlisting.
* Bot catalog expanded to 40+ AI crawlers.
* Incremental log parsing with cursor-based resume.

= 0.2.0 =
* Admin dashboard with Chart.js visualizations.
* Daily brief with new bot and new page detection.
* Parse frequency auto-tuning based on traffic volume.

= 0.1.0 =
* Initial release. Log parsing, bot identification, and basic rollup.

== Upgrade Notice ==

= 0.4.1 =
Adds a one-time consent notice for optional diagnostics and includes error messages in telemetry events. Recommended update.

= 0.4.0 =
Security hardening release. Fixes static asset tracking, removes PII from telemetry, adds MCP rate limiting. Recommended for all users.
