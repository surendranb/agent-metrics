# Agent Ready: Developer Launch Kit & Distribution Assets

Ready-to-publish launch copy and distribution templates for Day 0 through Week 2.

---

## 1. Show HN (Hacker News)

**Title**: `Show HN: Agent Ready – Make any WordPress site AI agent-ready in 60s`

**URL / Text**:
> Hi HN,
>
> We built Agent Ready (open source GPL-2.0) to solve two related problems:
>
> 1. AI agents (Claude Code, Cursor, ChatGPT Operator) spend excessive tokens parsing megabytes of HTML layout bloat, header wrappers, and client scripts.
> 2. WordPress site owners have zero visibility into who is crawling them, which pages attract attention, and whether bots are training models or answering user queries.
>
> Agent Ready adds an agent-readiness layer to WordPress:
> - **Markdown Twins**: Every post/page serves a clean Markdown AST at `/{slug}.md`.
> - **HTTP Content Negotiation**: Sends markdown when `Accept: text/markdown` is requested on regular URLs (with `Vary: Accept`).
> - **SEO Safe**: Sends `X-Robots-Tag: noindex` and `Link: <canonical_url>; rel="canonical"`.
> - **Traffic-Curated llms.txt**: Serves `/llms.txt` and `/llms-full.txt`, dynamically scored by actual agent fetches.
> - **W3C WebMCP Bridge**: Registers browser tools (`get_page_content`, `search_site`, `get_site_map`) for in-browser agent assistants.
> - **AI Crawler Intelligence**: Parses origin access logs (Nginx/Apache) to identify 42+ crawlers with zero PHP visitor overhead, classifying them by intent (Training vs Search vs On-demand).
>
> Code: https://github.com/surendranb/agent-metrics  
> Live Demo / Showcase: https://agent-metrics.builditwithai.xyz  
>
> Would love feedback from anyone experimenting with the agentic web or WebMCP!

---

## 2. Product Hunt

**Product Name**: `Agent Ready for WordPress`  
**Tagline**: `Make WordPress agent-ready with markdown twins, llms.txt & bot analytics`  
**Category**: Developer Tools, Open Source, Artificial Intelligence, WordPress  

**Maker Comment**:
> Hey Product Hunt!
>
> WordPress powers over 40% of the web, but the web was designed for human eyeballs, not AI agents.
>
> When an AI assistant or crawler visits your site today, it chokes on thousands of DOM elements, script tags, and CSS classes. Even worse, site owners are left in the dark about which AI companies are crawling them and why.
>
> We created Agent Ready to bridge this gap:
> - Turn every page into a token-efficient Markdown twin (`/{slug}.md`).
> - Dynamically curate `/llms.txt` based on real agent traffic.
> - Expose browser tools through the emerging W3C WebMCP standard.
> - Parse access logs locally to see exact crawler activity without paying for expensive enterprise bot management.
>
> It's 100% free, local-first, and open source. Check it out and let us know what you think!

---

## 3. X (Twitter) / LinkedIn Build-in-Public Post

**Hook**:
> WordPress powers 40% of the web. Almost none of it is ready for AI agents.
>
> When Claude or ChatGPT fetches your site, they spend 90% of their tokens stripping away header wrappers, cookie banners, and CSS bloat.
>
> Today we're launching Agent Ready: an open-source WordPress plugin that makes your site first-class for the agentic web in 60s.
>
> Here's what it does under the hood:
>
> 1. **Markdown Twins**: Every page gets a clean markdown twin at `/{slug}.md` + native `Accept: text/markdown` content negotiation.
> 2. **SEO Protection**: Strict `X-Robots-Tag: noindex` + canonical Link headers so Google rankings never get cannibalized.
> 3. **Traffic-Scored llms.txt**: Dynamic `/llms.txt` ranked by actual agent hits.
> 4. **W3C WebMCP Bridge**: In-browser tools for AI assistants navigating your site.
> 5. **Zero-Overhead Bot Intelligence**: Parses your origin server access logs to track GPTBot, ClaudeBot, and 40+ crawlers by intent (Training vs Search vs On-demand).
>
> 100% local-first, zero CDN scripts, GPL-2.0.
>
> One command to try:
> `wp plugin install agent-metrics --activate`
>
> GitHub: https://github.com/surendranb/agent-metrics
> Live Demo: https://agent-metrics.builditwithai.xyz

---

## 4. Reddit Community Posts (`r/Wordpress`, `r/webdev`, `r/localllama`)

**Title**: `We built an open-source plugin to make WordPress agent-ready (Markdown twins, llms.txt, WebMCP) + local AI crawler analytics`

**Post Body**:
> Over the past few months, we noticed AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Bytespider) hitting our sites thousands of times a day.
>
> But typical analytics tools either treat them as generic spam or force you into a paid SaaS subscription. At the same time, when agents actually fetch our pages, they waste context windows parsing huge HTML payloads.
>
> We built **Agent Ready** as a free, 100% local WordPress plugin:
> - **Content Negotiation**: If an agent requests a page with `Accept: text/markdown`, it receives a clean markdown twin generated from Gutenberg block ASTs. Humans continue to see standard HTML.
> - **Canonical Safety**: Injects `rel="alternate"` and `rel="canonical"` headers with `noindex` so search engines don't flag duplicate content.
> - **llms.txt Generation**: Automatically builds `/llms.txt` and `/llms-full.txt`, scoring pages based on real bot interest.
> - **Access Log Parser**: Reads Nginx/Apache logs in the background without adding PHP runtime overhead to human visits.
> - **WebMCP Bridge**: Integrates with the browser Model Context Protocol API.
>
> The code is on GitHub: https://github.com/surendranb/agent-metrics
>
> Feedback and PRs welcome!

---

## 5. WordPress.org Plugin Review Submission Notes

**Admin Review Note**:
> Dear Plugin Review Team,
>
> Agent Ready enables AI agent readiness (markdown twins, llms.txt, WebMCP bridge) and provides local server access log parsing for AI crawlers.
>
> Review highlights:
> 1. **No External CDNs**: All scripts (Chart.js) are strictly vendored locally inside `assets/vendor/`.
> 2. **Strict Escaping & Nonces**: All database queries use `$wpdb->prepare()`, inputs are sanitized (`sanitize_text_field`), and outputs are escaped (`esc_html`, `esc_attr`).
> 3. **Clean Uninstall**: `uninstall.php` completely drops the custom table and deletes all options and transients.
> 4. **Privacy-First Diagnostics**: Optional telemetry is disabled by default and requires explicit admin opt-in with a one-time notice. It only sends plugin health/version metrics and strips all URLs, IPs, and site content.
>
> Thank you for your review!
