const EVENT_PROPERTIES = {
  telemetry_enabled: new Set(['product', 'surface', 'version', 'schema_version']),
  first_parse: new Set(['product', 'surface', 'version', 'schema_version', 'status']),
  parse_completed: new Set(['product', 'surface', 'version', 'schema_version', 'status', 'duration_ms', 'lines_processed', 'skipped_lines']),
  mcp_configured: new Set(['product', 'surface', 'version', 'schema_version', 'status']),
  plugin_heartbeat: new Set(['product', 'surface', 'version', 'schema_version', 'status', 'storage_rows_bucket']),
  mcp_started: new Set(['product', 'surface', 'version', 'schema_version', 'status', 'client_name', 'client_version', 'protocol_version', 'client_ip']),
  tool_executed: new Set(['product', 'surface', 'version', 'schema_version', 'status', 'tool', 'latency_ms', 'client_name', 'client_ip']),
  tool_error: new Set(['product', 'surface', 'version', 'schema_version', 'status', 'tool', 'latency_ms', 'client_name', 'client_ip']),
};

const buckets = new Map();

function json(data, status) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'content-type': 'application/json; charset=utf-8' },
  });
}

function allowedRequest(request) {
  const ip = request.headers.get('cf-connecting-ip') || 'unknown';
  const now = Date.now();
  const bucket = buckets.get(ip) || { start: now, count: 0 };
  if (now - bucket.start >= 60_000) {
    bucket.start = now;
    bucket.count = 0;
  }
  bucket.count += 1;
  buckets.set(ip, bucket);
  return bucket.count <= 30;
}

function validScalar(value) {
  return (typeof value === 'string' && value.length <= 128) ||
    (typeof value === 'number' && Number.isFinite(value)) ||
    typeof value === 'boolean';
}

function validate(input) {
  if (!input || typeof input !== 'object' || Array.isArray(input)) return 'body must be an object';
  if (typeof input.event !== 'string' || !EVENT_PROPERTIES[input.event]) return 'unknown event';
  if (typeof input.distinct_id !== 'string' || !/^[A-Za-z0-9._:-]{8,128}$/.test(input.distinct_id)) return 'invalid distinct_id';
  if (!input.properties || typeof input.properties !== 'object' || Array.isArray(input.properties)) return 'properties must be an object';

  const allowed = EVENT_PROPERTIES[input.event];
  for (const [key, value] of Object.entries(input.properties)) {
    if (!allowed.has(key)) return `unknown property: ${key}`;
    if (!validScalar(value)) return `invalid property: ${key}`;
    if (key === 'client_ip' && !/^[0-9a-fA-F:.]+$/.test(value)) return 'invalid client_ip';
  }
  if (input.properties.product !== 'agent-metrics') return 'invalid product';
  if (!['plugin', 'mcp'].includes(input.properties.surface)) return 'invalid surface';
  return null;
}

function geoProperties(request) {
  const cf = request.cf || {};
  const geo = {};
  for (const [source, target] of [['continent', 'source_geo_continent'], ['country', 'source_geo_country'], ['region', 'source_geo_region'], ['city', 'source_geo_city']]) {
    if (typeof cf[source] === 'string' && cf[source].length <= 64) geo[target] = cf[source];
  }
  return geo;
}

async function forward(input, env, request) {
  const url = `${env.POSTHOG_HOST || 'https://us.i.posthog.com'}/i/v0/e/`;
  const properties = { ...input.properties };
  if (properties.client_ip) {
    properties.$ip = properties.client_ip;
    delete properties.client_ip;
  }
  const payload = {
    api_key: env.POSTHOG_PROJECT_TOKEN,
    event: input.event,
    distinct_id: input.distinct_id,
    properties: {
      ...properties,
      ...geoProperties(request),
      telemetry_received_at: new Date().toISOString(),
      '$ip': properties.$ip || '0.0.0.0',
    },
  };
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!response.ok) throw new Error(`PostHog returned ${response.status}`);
}

export async function handleRequest(request, env) {
  if (request.method !== 'POST') return json({ error: 'POST required' }, 405);
  if (!allowedRequest(request)) return json({ error: 'rate limit exceeded' }, 429);
  if (!env.POSTHOG_PROJECT_TOKEN) return json({ error: 'telemetry unavailable' }, 503);

  const contentLength = Number(request.headers.get('content-length') || 0);
  if (contentLength > 8192) return json({ error: 'payload too large' }, 413);

  let input;
  try {
    const raw = await request.text();
    if (raw.length > 8192) return json({ error: 'payload too large' }, 413);
    input = JSON.parse(raw);
  } catch {
    return json({ error: 'invalid JSON' }, 400);
  }
  const error = validate(input);
  if (error) return json({ error }, 400);

  try {
    await forward(input, env, request);
  } catch {
    return json({ error: 'upstream unavailable' }, 502);
  }
  return new Response(null, { status: 202 });
}

export default { fetch: handleRequest };
