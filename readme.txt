=== Agent Ready — Markdown Twins, llms.txt, WebMCP & AI Agent Analytics ===
Contributors: surendranb
Tags: ai, markdown, analytics, crawlers, mcp
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make WordPress agent-ready with Markdown twins, llms.txt, and WebMCP, while tracking AI crawler traffic and bot intent from server logs.

== Description ==

Agent Ready makes WordPress first-class for the agentic web. It equips your site with native agent-ready infrastructure while giving you transparent intelligence into how AI crawlers and agents interact with your content.

= 1. Agent Readiness =

* **Markdown Twins**: Every post and page serves a clean Markdown twin at `/{slug}.md`.
* **Content Negotiation**: Supports `Accept: text/markdown` with proper `Vary: Accept` headers.
* **SEO Safe**: Outputs `X-Robots-Tag: noindex` and `Link: <canonical_url>; rel="canonical"` to eliminate duplicate content risks.
* **Dynamic llms.txt**: Serves `/llms.txt`, `/.well-known/llms.txt`, and `/llms-full.txt`. Curation order is dynamically scored from real agent requests and crawler traffic, with support for manual page pins.
* **W3C WebMCP Bridge**: Registers in-browser agent tools (`get_page_content`, `search_site`, `get_site_map`) via the WebMCP API for browser-based AI assistants.

= 2. AI Agent & Bot Analytics =

* **Server Access Log Parsing**: Discovers and parses origin access logs with zero visitor-facing PHP overhead.
* **42+ AI Crawlers Identified**: Accurately detects GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Bytespider, CCBot, Applebot, and more.
* **Intent Classification**: Groups activity into Training, Search, and On-demand assistant fetches.
* **Unified Agent Activity**: Measures both background crawler hits and declared agent actions (markdown fetches, llms.txt reads, WebMCP tool executions).
* **30-Day Trends**: Visualizes volume and intent distribution using locally vendored Chart.js.

= 3. MCP Analytics Server =

Exposes a protected JSON-RPC endpoint (`/wp-json/agent-metrics/v1/mcp`) so AI assistants (Claude Code, Cursor, OpenCode) can query site intelligence.

* Available tools: `log_status`, `daily_brief`, `bot_summary`, `bot_breakdown`, `bot_trend`, `top_pages`, `agent_activity_summary`.
* Available prompts: `daily-brief`, `weekly-report`, `trend-analysis`, `investigate-spike`.

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
* **Security**: rate-limit the public agent-activity beacon endpoint (60 requests/minute per IP; HTTP 429 over the cap).

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

= 0.5.0 =
Adds agent-activity measurement: markdown page endpoints, llms.txt curation, a WebMCP bridge, and a new dashboard section. Also rate-limits the public beacon endpoint.

= 0.4.1 =
Adds a one-time consent notice for optional diagnostics and includes error messages in telemetry events. Recommended update.

= 0.4.0 =
Security hardening release. Fixes static asset tracking, removes PII from telemetry, adds MCP rate limiting. Recommended for all users.
