# Agent Metrics

**See which AI bots crawl your WordPress site, what they request, and why.**

Agent Metrics is a free WordPress plugin for turning server access logs into an AI bot traffic report. It identifies bots such as GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Bytespider, Common Crawl, and Bingbot, then groups their activity by intent:

- **Training**: crawlers collecting material for model development.
- **Search**: crawlers building indexes used to answer current questions.
- **On-demand**: fetchers retrieving a page because a person asked an assistant for it.

The plugin keeps the data on your WordPress site. It does not send logs to a third-party analytics service.

## Why This Exists

AI crawlers are already hitting public websites, but ordinary analytics reports them as anonymous bot traffic. That hides the useful questions:

- Which AI companies are visiting?
- Are they training models, indexing search, or answering a user right now?
- Which pages are attracting attention?
- Did a new bot or page appear yesterday?
- Is traffic growing because of a crawl, a search index refresh, or an assistant fetch?

Agent Metrics answers those questions from the access log your server already produces.

## Dashboard

The WordPress admin screen includes:

- Top bots and top pages for the latest activity day.
- Newly detected bots and newly crawled pages.
- Intent totals for training, search, and on-demand traffic.
- A 30-day AI bot traffic line chart.
- A 30-day stacked chart grouped by intent.
- A full bot table with category filters.
- Parse status, log diagnostics, and recommended refresh frequency.

The chart library is vendored in the plugin. The dashboard does not depend on a CDN.

## Install

### WordPress admin

1. Download the repository as a ZIP from [GitHub](https://github.com/surendranb/agent-metrics).
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and activate **AI Bot Traffic Analytics**.
4. Open **AI Bot Traffic** in the admin menu.

### Server

```bash
cd wp-content/plugins
git clone https://github.com/surendranb/agent-metrics.git agent-metrics
```

Activate the plugin in WordPress, then open the **AI Bot Traffic** screen.

## Log Discovery

The plugin checks these locations in order:

1. `AM_LOG_PATH`, when defined in `wp-config.php`.
2. `AM_LOG_DIR`, when defined in `wp-config.php`.
3. `/home/<user>/logs/access*` for cPanel hosts.
4. `/var/log/nginx/access*`.
5. `/var/log/apache2/access*`.
6. `/var/log/httpd/access*`.

For a managed host, define the exact readable file path in `wp-config.php` before the WordPress bootstrap:

```php
define( 'AM_LOG_PATH', '/home/example/logs/access.log' );
```

The web-server user must be able to read the file. Open **AI Bot Traffic → Diagnostics** to see every path tested and why it was accepted or rejected.

## Supported Log Shape

The parser accepts common Apache/Nginx access-log lines, including timezone-aware timestamps. The request method, path, status code, and final quoted field are used to identify the user agent.

Example:

```text
203.0.113.42 - - [04/Aug/2026:09:15:00 +0530] "GET /docs/ HTTP/1.1" 200 8123 "-" "GPTBot/1.1 (+https://openai.com/gptbot)"
```

Cloudflare dashboard exports are not read directly. Use an origin access log or a normalized log file that preserves the request timestamp, path, status, and user agent.

## MCP Server

The plugin exposes a protected JSON-RPC MCP endpoint so an AI agent can query the same rollup shown in WordPress.

After activation, open **AI Bot Traffic → Settings** and copy the endpoint and generated API key. The endpoint is:

```text
https://your-site.example/wp-json/agent-metrics/v1/mcp
```

Available tools:

- `log_status`
- `daily_brief`
- `bot_summary`
- `bot_breakdown`
- `bot_trend`
- `top_pages`

Available prompts:

- `daily-brief`
- `weekly-report`
- `trend-analysis`
- `investigate-spike`

The settings screen includes copy-ready connection snippets for OpenCode, Claude Code, and Cursor. Treat the API key like a password. Regenerate it from Settings if it is exposed.

## Bot Catalog

The catalog is intentionally explicit instead of guessing from generic browser user agents. It currently covers major AI and AI-adjacent crawlers, including:

- OpenAI: GPTBot, OAI-SearchBot, ChatGPT-User.
- Anthropic: ClaudeBot, Claude-SearchBot, Claude-User, legacy Anthropic identifiers.
- Google and Apple: Google-Extended, GoogleOther, Applebot, Applebot-Extended.
- Perplexity, Mistral, Meta, Microsoft, Amazon, ByteDance, Common Crawl, and other known crawlers.

Some provider controls, such as Google-Extended and Applebot-Extended, are robots.txt tokens rather than independent HTTP crawlers. They are included for reporting and policy context, but not every token can appear as a distinct access-log user agent in real traffic.

## Development

This is a plain WordPress plugin. There is no frontend build step for the plugin itself.

Run PHP lint checks directly from the plugin repository:

```bash
php -l agent-metrics.php
for file in includes/*.php; do php -l "$file"; done
```

The full integration suite used during development lives outside this deployable plugin repository. It covers PHP behavior, parser and rollup logic, WordPress rendering, Chart.js initialization, and the authenticated MCP endpoint.

## Project Status

The plugin is an early real-world release. The local dashboard, parser, rollup, bot taxonomy, and MCP surface are working. The next validation step is installation on a real WordPress host with readable server logs, followed by checking crawler attribution against observed traffic and published bot IP ranges.

## License

GPL-2.0-or-later. See the plugin header in `agent-metrics.php`.
