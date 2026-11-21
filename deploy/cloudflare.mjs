#!/usr/bin/env node
// The Cloudflare side of scarletweb.io: DNS records, zone settings, the origin certificate and the list of
// Cloudflare addresses nginx trusts. It touches the zone scarletweb.io only.
//
//   node deploy/cloudflare.mjs ips                          the nginx include of Cloudflare ranges (stdout)
//   node deploy/cloudflare.mjs dns <ipv4> <ipv6>            proxied A and AAAA for the apex and www
//   node deploy/cloudflare.mjs settings                     always use https, minimum TLS 1.2, TLS 1.3, HSTS untouched
//   node deploy/cloudflare.mjs ssl off|flexible|full|strict the zone's SSL mode
//   node deploy/cloudflare.mjs aop on|off                   Authenticated Origin Pulls for the zone
//   node deploy/cloudflare.mjs cache-rules                  the Cache Rules: the public API and the public pages, for visitors without a session
//   node deploy/cloudflare.mjs origin-cert <csr> <out.pem>  an Origin CA certificate for the apex and *.scarletweb.io
//   node deploy/cloudflare.mjs status                       the settings above, as the zone has them
//
// SCARLET_SECRETS is the folder with cloudflare-token (default: E:/Dev/secrets/scarlet-web on Windows,
// ~/.config/scarlet-web elsewhere). The token is read from that file and never printed.
import { readFile, writeFile } from 'node:fs/promises';
import { homedir } from 'node:os';
import { join } from 'node:path';

const ZONE = 'scarletweb.io';
const [command, ...args] = process.argv.slice(2);

async function cloudflareRanges(family) {
  const text = await (await fetch(`https://www.cloudflare.com/ips-${family}`)).text();
  return text.split('\n').map((line) => line.trim()).filter(Boolean);
}

if (command === 'ips') {
  const ranges = [...(await cloudflareRanges('v4')), ...(await cloudflareRanges('v6'))];
  const lines = ['# Cloudflare\'s published ranges (https://www.cloudflare.com/ips). Regenerate: node deploy/cloudflare.mjs ips'];
  for (const range of ranges) lines.push(`set_real_ip_from ${range};`);
  lines.push('real_ip_header CF-Connecting-IP;', 'real_ip_recursive off;');
  console.log(lines.join('\n'));
  process.exit(0);
}

const secrets = process.env.SCARLET_SECRETS ?? (process.platform === 'win32' ? 'E:/Dev/secrets/scarlet-web' : join(homedir(), '.config/scarlet-web'));
const token = (await readFile(join(secrets, 'cloudflare-token'), 'utf8')).trim();

async function api(method, path, body) {
  const res = await fetch(`https://api.cloudflare.com/client/v4/${path}`, {
    method,
    headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json();
  if (!json.success) throw new Error(`${method} ${path}: ${JSON.stringify(json.errors)}`);
  return json.result;
}

const zones = await api('GET', `zones?name=${ZONE}`);
if (zones.length !== 1) throw new Error(`zone ${ZONE} not found with this token`);
const zone = zones[0].id;
const setting = (name, value) => api('PATCH', `zones/${zone}/settings/${name}`, { value });

async function record(type, name, content) {
  const found = (await api('GET', `zones/${zone}/dns_records?type=${type}&name=${name}`))[0];
  const body = { type, name, content, proxied: true, ttl: 1 };
  if (found) return api('PUT', `zones/${zone}/dns_records/${found.id}`, body);
  return api('POST', `zones/${zone}/dns_records`, body);
}

const HOST = `http.host eq "${ZONE}"`;
const NO_CREDENTIALS = 'not any(lower(http.request.headers.names[*])[*] eq "authorization") and not http.cookie contains "scarlet_session" and not http.cookie contains "remember_web_"';
const PAGES = ['/', '/demo', '/stats', '/map', '/search', '/developers', '/release-notes', '/download', '/leaderboards', '/talents', '/rulesets'];
const PAGE_PREFIXES = ['/c/', '/creatures/', '/items/', '/zones/', '/quests/', '/leaderboards/', '/talents/', '/rulesets/'];
const CACHE_RULES = [
  {
    description: 'Public API for callers without credentials',
    expression: `${HOST} and starts_with(http.request.uri.path, "/api/v1/public/") and ${NO_CREDENTIALS}`,
  },
  {
    description: 'Map tile manifest',
    expression: `${HOST} and http.request.uri.path eq "/map-tiles/manifest.json"`,
  },
  {
    description: 'Public pages for visitors without a session',
    expression: `${HOST} and (http.request.uri.path in {${PAGES.map((path) => `"${path}"`).join(' ')}} or ${PAGE_PREFIXES.map((prefix) => `starts_with(http.request.uri.path, "${prefix}")`).join(' or ')}) and ${NO_CREDENTIALS}`,
  },
].map((rule) => ({ ...rule, action: 'set_cache_settings', action_parameters: { cache: true, edge_ttl: { mode: 'bypass_by_default' }, browser_ttl: { mode: 'respect_origin' } }, enabled: true }));

switch (command) {
  case 'cache-rules': {
    const result = await api('PUT', `zones/${zone}/rulesets/phases/http_request_cache_settings/entrypoint`, { rules: CACHE_RULES });
    console.log(`cache-rules: ${result.rules.length} rules in ruleset ${result.id}, version ${result.version}`);
    break;
  }
  case 'dns': {
    const [v4, v6] = args;
    for (const name of [ZONE, `www.${ZONE}`]) {
      await record('A', name, v4);
      await record('AAAA', name, v6);
    }
    console.log('dns: proxied A and AAAA for', ZONE, 'and www.' + ZONE);
    break;
  }
  case 'settings':
    await setting('always_use_https', 'on');
    await setting('min_tls_version', '1.2');
    await setting('tls_1_3', 'on');
    await setting('automatic_https_rewrites', 'on');
    console.log('settings: always_use_https on, min_tls_version 1.2, tls_1_3 on');
    break;
  case 'ssl':
    await setting('ssl', args[0]);
    console.log('ssl:', args[0]);
    break;
  case 'aop':
    await setting('tls_client_auth', args[0]);
    console.log('aop:', args[0]);
    break;
  case 'origin-cert': {
    const [csrPath, outPath] = args;
    const result = await api('POST', 'certificates', {
      csr: await readFile(csrPath, 'utf8'),
      hostnames: [ZONE, `*.${ZONE}`],
      request_type: 'origin-ecc',
      requested_validity: 5475,
    });
    await writeFile(outPath, result.certificate);
    console.log(`origin-cert: id ${result.id} expires ${result.expires_on} written to ${outPath}`);
    break;
  }
  case 'status': {
    for (const name of ['ssl', 'always_use_https', 'min_tls_version', 'tls_1_3', 'tls_client_auth', 'security_header']) {
      const s = await api('GET', `zones/${zone}/settings/${name}`);
      console.log(`${name} = ${JSON.stringify(s.value)}`);
    }
    const cache = await api('GET', `zones/${zone}/rulesets/phases/http_request_cache_settings/entrypoint`).catch(() => ({ rules: [] }));
    for (const r of cache.rules ?? []) console.log(`cache rule: ${r.description} enabled=${r.enabled}`);
    for (const r of await api('GET', `zones/${zone}/dns_records`)) console.log(`${r.type} ${r.name} -> ${r.content} proxied=${r.proxied}`);
    break;
  }
  default:
    console.error('usage: cloudflare.mjs ips | dns <ipv4> <ipv6> | settings | ssl <mode> | aop on|off | cache-rules | origin-cert <csr> <out> | status');
    process.exit(2);
}
