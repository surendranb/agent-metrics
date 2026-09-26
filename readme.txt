=== InPlainSite – Clean Markdown & Visitor Telemetry ===
Contributors: surendran
Tags: ai, markdown, analytics, crawlers, mcp
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.5.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your WordPress website agent-ready with Markdown twins, llms.txt, WebMCP in-browser tools, and local AI crawler analytics.

== Description ==

InPlainSite makes WordPress first-class for the agentic web. It equips your site with native agent-ready infrastructure while giving you transparent intelligence into how AI crawlers and agents interact with your content.

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

Exposes a protected JSON-RPC endpoint (`/wp-json/inplainsite/v1/mcp`) so AI assistants (Claude Code, Cursor, OpenCode) can query site intelligence.

* Available tools: `log_status`, `daily_brief`, `bot_summary`, `bot_breakdown`, `bot_trend`, `top_pages`, `agent_activity_summary`.
* Available prompts: `daily-brief`, `weekly-report`, `trend-analysis`, `investigate-spike`.

= Privacy =

* InPlainSite is completely local-first. No site URLs, page paths, user agents, or traffic data are ever sent externally.
* The plugin reads your existing server access log — it does not create external tracking scripts, third-party network requests, or cookies.
* All data is stored directly in your local WordPress database.

== Installation ==

1. Upload the `inplainsite` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Open **InPlainSite** in the admin menu.

The plugin auto-discovers access logs in common locations (Nginx, Apache, cPanel). If your logs are in a custom path, add this to `wp-config.php`:

    define( 'INPLAINSITE_LOG_PATH', '/path/to/your/access.log' );

== Frequently Asked Questions ==

= What log formats are supported? =

Standard Apache/Nginx combined log format. The parser extracts the request method, path, status code, and user agent from each line.

= Does this work on managed hosting (WP Engine, Kinsta)? =

Only if raw access logs are readable by the web server user. Many managed hosts do not expose raw logs — check with your host.

= Does the plugin track static assets? =

No. CSS, JS, images, fonts, and WordPress internal paths are automatically filtered out. Only content URLs are tracked.

= Is the MCP endpoint secure? =

Yes. It requires authentication via `Authorization: Bearer` header or `X-InPlainSite-Key` header. It includes per-IP rate limiting (120 requests/minute). Regenerate the key from Settings if it is ever exposed.

== Screenshots ==

1. Overview dashboard with top bots, top pages, and trend charts.
2. Settings page with MCP connection snippets and parse frequency.

== Changelog ==

= 0.5.2 =
* **WP Review**: Complete local-first operation — external diagnostic telemetry and remote dependencies removed.
* **WP Review**: Unified prefixing to `InPlainSite` / `inplainsite_` / `INPLAINSITE_` across all constants, classes, hooks, options, transients, and REST endpoints.
* **WP Review**: Strict late output escaping across all admin templates and plain-text endpoints; zero PHPCS suppressions.
* **WP Review**: Fixed all documentation URLs and links.

= 0.5.1 =
* **Refinement**: Pre-release feedback adjustments for WordPress.org submission.

= 0.5.0 =
* **Agent Activity**: new measurement layer — `/{slug}.md` endpoint, `Accept: text/markdown` negotiation, `Link` headers, recorded as `agent-activity`/`MarkdownFetch` hits.
* **Agent Activity**: `/llms.txt`, `/.well-known/llms.txt`, and `/llms-full.txt` — ordering curated from real agent activity, manual pins supported.
* **WebMCP bridge**: registers read-only `get_page_content`, `search_site`, `get_site_map` tools via the W3C WebMCP API where the browser supports it; executions beaconed as declared events.
* **MCP**: new `agent_activity_summary` tool (totals, by-tool, by-page, trend); existing analytics tools unchanged.
* **Dashboard**: new Agent Activity section — counters, by-tool table, per-page table, 30-day trend; renders independently of access-log health.
* **Security**: rate-limit the public agent-activity beacon endpoint (60 requests/minute per IP; HTTP 429 over the cap).

= 0.4.1 =
* **Performance**: Cache dashboard rollup in a transient; cap log reads at 5000 lines per pass.
* **Performance**: Add index on bot column and 30-day retention pruning.
* **Compat**: WordPress Coding Standards fixes across all files.

= 0.4.0 =
* **Security**: Filter out static assets (CSS, JS, images, fonts) and WordPress internal paths — only content URLs are tracked.
* **Security**: Remove query parameter authentication from MCP endpoint (keys in URLs leak to logs).
* **Security**: Add per-IP rate limiting to MCP endpoint (120 req/min).
* **Security**: Complete uninstall cleanup — drops table and removes all options.

= 0.3.0 =
* MCP server with JSON-RPC protocol support.
* Bot catalog expanded to 40+ AI crawlers.
* Incremental log parsing with cursor-based resume.

= 0.2.0 =
* Admin dashboard with Chart.js visualizations.
* Daily brief with new bot and new page detection.
* Parse frequency auto-tuning based on traffic volume.

= 0.1.0 =
* Initial release. Log parsing, bot identification, and basic rollup.

== Upgrade Notice ==

= 0.5.2 =
WordPress review release: 100% local-first, unified prefixing, and security escaping enhancements.

= 0.5.0 =
Adds agent-activity measurement: markdown page endpoints, llms.txt curation, a WebMCP bridge, and a new dashboard section.
