import test from 'node:test';
import assert from 'node:assert/strict';
import { handleRequest } from '../src/index.js';

const env = { POSTHOG_PROJECT_TOKEN: 'test-token', POSTHOG_HOST: 'https://posthog.test' };

function request(body, headers = {}) {
  return new Request('https://agent-metrics.test/v1/events', {
    method: 'POST',
    headers: { 'content-type': 'application/json', ...headers },
    body: JSON.stringify(body),
  });
}

const base = {
  event: 'mcp_started',
  distinct_id: 'am_12345678',
  properties: {
    product: 'agent-metrics',
    surface: 'mcp',
    version: '0.2.0',
    schema_version: 1,
    status: 'success',
  },
};

test('accepts an allowlisted event and forwards only the canonical payload', async () => {
  const originalFetch = globalThis.fetch;
  let forwarded;
  globalThis.fetch = async (url, options) => {
    forwarded = { url, options, body: JSON.parse(options.body) };
    return new Response('', { status: 200 });
  };
  const geoRequest = request(base);
  Object.defineProperty(geoRequest, 'cf', { value: { continent: 'AS', country: 'IN', region: 'TN', city: 'Chennai' } });
  const response = await handleRequest(geoRequest, env);
  globalThis.fetch = originalFetch;
  assert.equal(response.status, 200);
  assert.equal(forwarded.url, 'https://posthog.test/capture/');
  assert.equal(forwarded.body.event, 'mcp_started');
  assert.equal(forwarded.body.properties.surface, 'mcp');
  assert.equal(forwarded.body.properties.telemetry_received_at !== undefined, true);
  assert.equal(forwarded.body.properties.$ip, '0.0.0.0');
  assert.equal(forwarded.body.properties.source_geo_country, 'IN');
  assert.equal(forwarded.body.properties.source_geo_city, 'Chennai');
});

test('rejects unknown properties', async () => {
  const response = await handleRequest(request({ ...base, properties: { ...base.properties, secret: 'nope' } }), env);
  assert.equal(response.status, 400);
});

test('rejects unknown events', async () => {
  const invalid = {
    url: 'https://agent-metrics.test/v1/events',
    method: 'POST',
    headers: new Headers({ 'content-length': '1' }),
    text: async () => JSON.stringify({ ...base, event: 'raw_log' }),
  };
  const response = await handleRequest(invalid, env);
  assert.equal(response.status, 400);
});

test('returns upstream failure with status detail', async () => {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async () => new Response('', { status: 500 });
  const response = await handleRequest(request(base), env);
  globalThis.fetch = originalFetch;
  assert.equal(response.status, 502);
  assert.deepEqual(await response.json(), { error: 'upstream unavailable', details: 'PostHog returned 500' });
});

test('fails loud (503, no forward) when the PostHog token binding is missing', async () => {
  const originalFetch = globalThis.fetch;
  let forwarded = false;
  globalThis.fetch = async () => { forwarded = true; return new Response('', { status: 200 }); };
  const response = await handleRequest(request(base), { POSTHOG_HOST: 'https://posthog.test' });
  globalThis.fetch = originalFetch;
  assert.equal(response.status, 503);
  assert.equal(forwarded, false);
  assert.equal((await response.json()).error.includes('binding missing'), true);
});
