# Agent Metrics Telemetry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans (inline execution selected).

**Goal:** Add privacy-safe, opt-in WordPress telemetry and MCP telemetry through a Wrangler-managed Cloudflare Worker into the dedicated Agent Metrics PostHog project.

**Architecture:** The public plugin and embedded MCP endpoint send a shared allowlisted event envelope to a Cloudflare Worker. The Worker validates and forwards events to PostHog using a Wrangler secret. Telemetry is best-effort and never carries traffic data, content, credentials, arguments, or results.

**Tech Stack:** PHP/WordPress HTTP API, Cloudflare Workers, Wrangler, PostHog Capture API, Node built-in assertions for Worker tests.

## Global Constraints

- Use one PostHog project and differentiate clients with `surface` (`plugin` or `mcp`).
- WordPress telemetry is explicit opt-in and heartbeat frequency is at most once per day.
- Never send site URL, domain, IP, page path, user agent, raw logs, bot names, traffic counts, WordPress content, MCP arguments/results, prompts, credentials, or API keys.
- Add only coarse Cloudflare request geography to events; treat it as host/runtime location, not visitor or bot location.
- Store the PostHog project token only as a Wrangler secret.
- Telemetry failures must not affect parsing, dashboard rendering, MCP responses, or activation.
- Rotate the PostHog key supplied in chat before production deployment.

### Task 1: Worker Gateway

**Files:**
- Create: `telemetry-worker/wrangler.toml`
- Create: `telemetry-worker/src/index.js`
- Create: `telemetry-worker/test/index.test.js`

**Interfaces:**
- Consumes: `POST /v1/events` with `{event, distinct_id, properties}`.
- Produces: `202` for accepted events, `400` for invalid events, and a PostHog capture request containing only allowlisted fields.

- [ ] Write tests for valid events, unknown event rejection, unknown property rejection, oversized input rejection, and upstream failure handling.
- [ ] Implement a Worker that accepts POST JSON only, validates the event allowlist and property allowlist, adds `received_at` and `schema_version`, and forwards to PostHog `/i/v0/e/` using `env.POSTHOG_PROJECT_TOKEN` and `env.POSTHOG_HOST`.
- [ ] Use `wrangler.toml` with Worker name `agent-metrics-telemetry`, `main = "src/index.js"`, and custom domain `agent-metrics.builditwithai.xyz`.
- [ ] Run `node --test telemetry-worker/test/index.test.js`.

### Task 2: Plugin Telemetry

**Files:**
- Create: `includes/class-am-telemetry.php`
- Modify: `agent-metrics.php`
- Modify: `includes/class-am-admin.php`
- Create: `uninstall.php`
- Create: `tests/test-telemetry.php`

**Interfaces:**
- `AM_Telemetry::enabled(): bool`
- `AM_Telemetry::send(string $event, array $properties = array()): void`
- `AM_Telemetry::maybe_heartbeat(): void`

- [ ] Add tests for default-off behavior, opt-in persistence, daily heartbeat gating, uninstall cleanup, and failure not interrupting product code.
- [ ] Generate an anonymous install ID with WordPress randomness and store only the ID plus opt-in state and last heartbeat timestamp in options.
- [ ] Add a settings checkbox for anonymous diagnostics with clear privacy copy and a save nonce; do not enable it by default.
- [ ] Send only `telemetry_enabled`, `first_parse`, `parse_completed`, `mcp_configured`, and `plugin_heartbeat` with allowlisted metadata.
- [ ] Trigger `first_parse`/`parse_completed` after successful parsing and `mcp_configured` when the MCP panel is used, without sending traffic data.
- [ ] Schedule the heartbeat through the existing cron lifecycle and enforce once-per-day gating.
- [ ] Delete telemetry options in `uninstall.php`.
- [ ] Run the existing PHP lint/check suite plus `php tests/test-telemetry.php`.

### Task 3: MCP Telemetry

**Files:**
- Modify: `includes/class-am-telemetry.php`
- Modify: `includes/class-am-mcp-server.php`
- Modify: `agent-metrics.php`
- Create: `tests/test-mcp-telemetry.php`

**Interfaces:**
- `AM_Telemetry::send()` remains the only telemetry boundary.
- MCP events use `surface = mcp` and include only tool name, client/runtime category, protocol/version, status, and latency.

- [ ] Capture `mcp_started` after successful initialization without request payloads.
- [ ] Capture `tool_executed` and `tool_error` around tool dispatch with bounded tool name, status, and latency only.
- [ ] Ensure telemetry exceptions are caught and never alter JSON-RPC responses.
- [ ] Run MCP protocol smoke tests and `php tests/test-mcp-telemetry.php`.

### Task 4: Deploy and Verify

**Files:**
- Modify: `telemetry-worker/wrangler.toml`
- Modify: `README.md` with the anonymous diagnostics disclosure and telemetry endpoint policy.

- [ ] Rotate the exposed PostHog key and store the replacement with `wrangler secret put POSTHOG_PROJECT_TOKEN`.
- [ ] Deploy the Worker using `wrangler deploy telemetry-worker/src/index.js --config telemetry-worker/wrangler.toml` at `agent-metrics.builditwithai.xyz/v1/events`.
- [ ] Send one synthetic non-sensitive event and verify it appears in the dedicated PostHog project.
- [ ] Verify accepted events include coarse geography and `$ip = 0.0.0.0` in PostHog.
- [ ] Deploy the plugin to the GCP WordPress VM and enable diagnostics in the admin UI.
- [ ] Verify `plugin_heartbeat` and `mcp_started` arrive with `surface` filters and no forbidden properties.
- [ ] Run the complete local suite, PHP lint, Worker tests, live MCP smoke test, and `git diff --check`.
