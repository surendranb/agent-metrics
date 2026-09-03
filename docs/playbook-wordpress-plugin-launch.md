# WordPress Plugin Launch & Distribution Playbook
*The 1,000 Active Installs Blueprint for Studio Products*

## Overview & Value Metric
The target is **1,000 Active Installs** (retention and recurring utility) rather than raw download spikes. WordPress.org search (powered by Elasticsearch) weighs active installs, resolved support threads, keyword-optimized titles, and release freshness.

---

## 1. Competitive Landscape & Positioning Moat

Empirical analysis of existing WordPress + AI plugins (*42A AI Visibility*, *Known Agents / Dark Visitors*, *Bot Traffic Shield*, *Official WordPress MCP Adapter*, *Worddown*):

| Dimension | **Agent Ready** (Us) | **42A AI Visibility** | **Known Agents** (Dark Visitors) | **Bot Traffic Shield** | **Official WP MCP** |
|---|---|---|---|---|---|
| **Core Philosophy** | **Readiness + Intelligence** (Serve agents & track traffic) | **GEO / SEO Visibility** (Optimize for AI answers) | **Bot Tracking** (Log crawlers via API) | **Defensive Blocking** (Block AI scrapers) | **Agent Control** (Abilities API bridge) |
| **Architecture** | **100% Local-First** (Zero-overhead server logs) | SaaS / Cloud dependent | SaaS API sync | Local blocking rules | Local server bridge |
| **Markdown Twins** | **Native AST Converter** (`/{slug}.md` + Content Negotiation) | Basic markdown pages | No | No | No |
| **llms.txt Curation** | **Dynamic & Traffic-Weighted** (Scored by agent hits) | Static / Configurable | No | No | No |
| **Browser WebMCP** | **Yes (W3C WebMCP Bridge)** | No | No | No | No |
| **Bot Intent Analysis** | **42+ Bots** (Training vs Search vs On-Demand) | AI search engine focus | Broad crawler list | Scraper blocking focus | N/A |
| **Pricing / Lock-in** | **100% Free & Open Source (GPL-2.0)** | Freemium / Paid SaaS | Paid SaaS tiers | Free / Freemium | Free / Open Source |

### Our Core Differentiation & Moat
1. **The Closed Feedback Loop**: We are the only plugin where **Agent Readiness** (serving markdown & WebMCP) and **Agent Intelligence** (tracking bot traffic) feed each other — real agent requests dynamically score and curate `llms.txt`.
2. **Zero-Overhead Log Parsing**: We read existing Nginx/Apache/cPanel server logs instead of hooking into every live PHP request, guaranteeing zero site slowdown.
3. **No SaaS Lock-in**: Everything stays on the user's WordPress installation; no mandatory cloud account or external API subscription.

---

## 2. Verified Keyword Intelligence & ASO Matrix

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 THE ASO KEYWORD PYRAMID                                │
│                                                                                        │
│   [Title & Top 5 Tags]       ai, markdown, analytics, crawlers, mcp                    │
│   [Excerpt & Headers]        ai bots, llms.txt, webmcp, markdown twin, agent ready     │
│   [Body & Long-tail FAQ]     gptbot, claudebot, bot traffic, content negotiation, GEO   │
│   [Web & Alternatives]       dark visitors alternative, robots.txt for ai, ai sitemap  │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### Tier 1: Core Ranking Anchors (Title & Public Tags)
- **Target Title**: `Agent Ready — Markdown Twins, llms.txt, WebMCP & AI Agent Analytics`
- **Top 5 Public Tags**: `ai`, `markdown`, `analytics`, `crawlers`, `mcp`

### Tier 2: Category & Feature Keywords (Excerpt & Headers)
- `markdown twin`, `llms.txt`, `llms-full.txt`, `webmcp`, `agent ready`, `content negotiation`, `ai bot tracker`, `ai crawlers`, `server log parser`, `gptbot`, `claudebot`, `perplexitybot`.

### Tier 3: Long-Tail Problem Queries (FAQ Section)
- *"How to serve markdown to AI crawlers in WordPress?"*
- *"How to auto-generate and curate llms.txt based on traffic?"*
- *"How to track GPTBot and ClaudeBot without Cloudflare?"*
- *"How to prevent duplicate content SEO penalties with markdown twins?"*
- *"What is WebMCP and how does WordPress support it?"*

### Tier 4: Alternative & Comparative Queries (Marketing & SEO)
- `Dark Visitors alternative for WordPress`
- `Cloudflare AI bot analytics free alternative`
- `WordPress robots.txt for AI agents`
- `WordPress AI paywall vs open agent readiness`
- `Roots post-content-to-markdown alternative with full block AST support`

---

## 3. The 8-Phase Execution Protocol

```
Phase 0: ASO & Value Metric  ➔  Phase 1: Code & Security Audit  ➔  Phase 2: Privacy-Safe Telemetry
                                                                           │
Phase 5: Marketing & Launch  ➔  Phase 4: Multi-Surface Release  ➔  Phase 3: 60s "Aha" Activation
         │
         ▼
Phase 6: Retention Flywheel  ➔  Phase 7: E2E Verification Gate  ➔  1,000 Active Installs
```

### Phase 0: Positioning, Intent & ASO
Front-load title, 150-char excerpt, 12 Elasticsearch tags, and long-tail FAQ answers.

### Phase 1: WordPress Quality, Security & Standards Audit
WPCS compliance, strict sanitization/escaping, nonce & capability checks, clean `uninstall.php`, transient caching.

### Phase 2: Privacy-Safe Telemetry & The Funnel
Explicit consent notice, Cloudflare Edge Gateway (`/v1/events`) stripping PII, PostHog D1/D7/D30 active install cohort tracking.

### Phase 3: The 60-Second "Aha" Activation
Zero-config log discovery, instant active markdown twins and `/llms.txt`, native light UI, copyable curl verification commands.

### Phase 4: Multi-Surface Distribution
- WordPress.org SVN repository deployment with high-res assets (`icon-256x256.png`, `banner-1544x500.png`, screenshots).
- GitHub Releases with clean `agent-metrics-0.5.0.zip`.
- Studio Showcase Subdomain (`agent-metrics.builditwithai.xyz` / `agent-ready.builditwithai.xyz`).
- WP-CLI command: `wp plugin install agent-metrics --activate`.

### Phase 5: High-Signal Developer Marketing Channels

| Timing | Channel | Tactic |
|---|---|---|
| **Day 0** | **Show HN / Product Hunt** | *Show HN: Make any WordPress site AI Agent-Ready in 60s*. Focus on Markdown twins + WebMCP bridge. |
| **Day 0–1** | **X & LinkedIn** | Technical build-in-public breakdown with live curl examples, code snippets, and architecture diagrams. |
| **Week 1** | **Reddit (`r/Wordpress`, `r/webdev`, `r/localllama`)** | Technical case study: "Why we built Markdown Twins & WebMCP for WordPress". |
| **Week 2** | **AI Tool Directories** | Submit to *There's An AI For That*, *Futurepedia*, *Toolify*, *PulseMCP*, and *Glama*. |
| **Weeks 3–4** | **The Agency Multiplier** | Reach out to 20 boutique WordPress agencies managing 20–100 client sites (1 agency = 50 installs). |
| **Ongoing** | **Content Marketing** | Publish quarterly crawler benchmarks comparing GPTBot vs ClaudeBot vs Perplexity crawling habits. |

### Phase 6: The Retention & Review Flywheel (Road to 1,000)
- In-plugin quiet footer utility chips (Star on GitHub, Share on X, Rate 5 Stars).
- 14-Day value-milestone advocacy banner (shown only after ≥ 7 days & ≥ 50 bot hits logged).
- `< 24-hour support SLA` on WordPress.org support forum for Elasticsearch ranking boost.
- Bi-weekly freshness updates with every WordPress minor/major release.

### Phase 7: Deterministic Verification Gate
All automated tests must pass 100% prior to cutting release tags or submitting SVN builds.

---

## 4. Official WordPress.org Detailed Plugin Guidelines Compliance Checklist

Strict mapping against the 18 official rules from [developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/):

| # | Guideline Requirement | Developer Verification Check | Agent Ready Compliance Proof |
|---|---|---|---|
| **1** | **GPL Compatibility** | All code, libraries, and images must be GPLv2+ compatible. | Licensed under GPL-2.0-or-later. Included `Chart.js` is MIT licensed (GPL-compatible). |
| **2** | **Developer Responsibility** | Ensure all code and third-party APIs comply with guidelines; no circumventing rules. | 100% verifiable code; zero bypass or evasive routines. |
| **3** | **Stable Version in Directory** | Official WordPress.org directory is the canonical source of truth for users. | SVN repository deployment matches Git release tags (`v0.5.0`). |
| **4** | **Human-Readable Code** | No obfuscation, packing, minification mangling, or obscured variable names. | Clean PSR-12 / WPCS formatted code; public GitHub repository linked in readme. |
| **5** | **No Trialware** | No locked features behind paywalls; no artificial quotas or timed lockouts. | 100% free and fully functional out of the box; zero premium upsell locks. |
| **6** | **Legitimate SaaS Boundary** | SaaS must offer substantive functionality; no arbitrary code extraction. | Core log parsing, markdown twins, and WebMCP run 100% locally on the host. |
| **7** | **No Tracking Without Consent** | Explicit user opt-in required before contacting external servers. | Telemetry is disabled by default; requires explicit admin consent toggle. Zero external CDNs. |
| **8** | **No Remote Executable Code** | No loading external JS/CSS from CDNs, no remote eval, no admin iframes. | All assets (`chart.umd.min.js`, `webmcp-bridge.js`) are locally vendored inside `assets/`. |
| **9** | **Honest & Moral Conduct** | No keyword stuffing, no fake reviews, no sockpuppeting, no copyright theft. | Authentic technical documentation; zero spam or blackhat SEO. |
| **10** | **No Public Site Backlinks** | No "Powered by" or credit badges on the public frontend without explicit opt-in. | Zero public site injections; does not modify frontend HTML content or inject backlink badges. |
| **11** | **No Dashboard Hijacking** | Admin notices must be limited, contextual, and dismissible. No admin ads. | Advocacy chips are restricted strictly to plugin settings page. Notices are permanently dismissible. |
| **12** | **Spam-Free Readmes** | Maximum 5 tags total; no competitor brand names in tags; no keyword stuffing. | Exactly 5 tags (`ai, markdown, analytics, crawlers, mcp`); verified by Plugin Check tool. |
| **13** | **Use Default WP Libraries** | Do not bundle libraries already included in WordPress (jQuery, etc.). | Uses core WordPress functions and APIs; only vendors non-core Chart.js. |
| **14** | **Avoid Frequent Commits** | SVN is a release repository, not a development scratchpad. | Commits pushed to SVN only on tagged production releases with descriptive changelogs. |
| **15** | **Version Number Increment** | `Version` in plugin header and `Stable tag` in `readme.txt` must match and increment. | Both set to `0.5.0` across all metadata headers. |
| **16** | **Complete Functional Plugin** | Complete, functional plugin zip must be submitted (no placeholder reservations). | Full 28-file production archive (`agent-metrics-0.5.0.zip`) with working engines. |
| **17** | **Respect Trademarks & Slugs** | Slugs cannot begin with "wordpress-" or unauthorized third-party trademark names. | Slug is `agent-metrics`. Title does not infringe trademarks. |
| **18** | **Directory Maintenance Respect** | Acknowledge WordPress.org Plugin Team authority and security mandates. | Verified against official Plugin Check tool (PCP) with 0 errors. |

