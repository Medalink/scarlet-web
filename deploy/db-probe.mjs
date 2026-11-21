#!/usr/bin/env node
// A request loop for a database move: reads and one signed write per tick against a running site, every failure
// timestamped, a summary at the end. It uses one registered install (key kept in --state) and rewrites its link code with the
// same signed POST /api/v1/installs/link-code the app uses, so every write is a real upload-API write to Postgres.
//
//   node deploy/db-probe.mjs --base https://scarletweb.io --seconds 120
//   node deploy/db-probe.mjs --base https://scarlet-load-app --host scarletweb.io --insecure --seconds 120
//   node deploy/db-probe.mjs --base https://scarletweb.io --forget     delete nothing; prints the install id to remove afterwards
//
// Options: --seconds N (default 60), --reads-per-sec N (10), --writes-per-sec N (1), --state FILE (a key file in the temp
// folder), --insecure (accept the self-signed certificate of the rehearsal droplet), --host NAME (the Host header and TLS name).
// Reads: the pages /release-notes and /stats (both read Postgres; the public API answers 429 above its per-address limit).
// A read fails on a status other than 200 or an error.
// A write fails on a status other than 201 and 429 is counted apart (the install's own rate limit, 240 a minute).
import { createPrivateKey, generateKeyPairSync, createHash, sign } from 'node:crypto';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { request } from 'node:https';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const args = process.argv.slice(2);
const option = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
const base = new URL(option('--base', 'https://scarletweb.io'));
const hostName = option('--host', base.hostname);
const seconds = Number(option('--seconds', 60));
const readsPerSecond = Number(option('--reads-per-sec', 10));
const writesPerSecond = Number(option("--writes-per-sec", 1));
const insecure = args.includes('--insecure');
const statePath = option('--state', join(process.env.TEMP ?? tmpdir(), `scarlet-db-probe-${hostName}.json`));

function send(method, path, headers = {}, body = '') {
  return new Promise((resolve) => {
    const started = Date.now();
    const req = request({
      hostname: base.hostname, port: base.port || 443, path, method, servername: hostName, rejectUnauthorized: !insecure, timeout: 10000,
      headers: { host: hostName, 'user-agent': 'scarlet-db-probe', ...headers, ...(body ? { 'content-length': Buffer.byteLength(body) } : {}) },
    }, (res) => {
      let size = 0;
      res.on('data', (c) => { size += c.length; });
      res.on('end', () => resolve({ status: res.statusCode, ms: Date.now() - started, size }));
    });
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', (e) => resolve({ status: 0, error: e.message, ms: Date.now() - started }));
    if (body) req.write(body);
    req.end();
  });
}

async function install() {
  if (existsSync(statePath)) return JSON.parse(readFileSync(statePath, 'utf8'));
  const { privateKey, publicKey } = generateKeyPairSync('ed25519');
  const raw = publicKey.export({ type: 'spki', format: 'der' }).subarray(-32).toString('base64');
  const body = JSON.stringify({ public_key: raw, name: 'dbmove-probe' });
  const res = await new Promise((resolve) => {
    const req = request({ hostname: base.hostname, port: base.port || 443, path: '/api/v1/installs', method: 'POST', servername: hostName, rejectUnauthorized: !insecure,
      headers: { host: hostName, 'content-type': 'application/json', 'content-length': Buffer.byteLength(body) } }, (r) => {
      let data = '';
      r.on('data', (c) => { data += c; });
      r.on('end', () => resolve({ status: r.statusCode, data }));
    });
    req.on('error', (e) => resolve({ status: 0, data: e.message }));
    req.end(body);
  });
  if (res.status !== 201 && res.status !== 200) throw new Error(`could not register the probe install: ${res.status} ${res.data.slice(0, 200)}`);
  const state = { id: JSON.parse(res.data).install.id, key: privateKey.export({ type: 'pkcs8', format: 'pem' }) };
  writeFileSync(statePath, JSON.stringify(state), { mode: 0o600 });
  return state;
}

const state = await install();
const key = createPrivateKey(state.key);
let counter = 0;
function signedHeaders(method, path, body) {
  const t = Math.floor(Date.now() / 1000);
  const message = `${method}\n${path}\n${t}\n${createHash('sha256').update(body).digest('hex')}`;
  return { 'content-type': 'application/json', 'scarlet-install': state.id, 'scarlet-time': String(t), 'scarlet-signature': sign(null, Buffer.from(message), key).toString('base64') };
}

const stats = { read: { ok: 0, fail: 0 }, write: { ok: 0, fail: 0, limited: 0 } };
const failures = [];
const started = Date.now();
const stamp = () => new Date().toISOString().slice(11, 23);
function record(kind, label, res) {
  const ok = kind === 'read' ? res.status === 200 : res.status === 201;
  if (ok) { stats[kind].ok++; return; }
  if (kind === 'write' && res.status === 429) { stats.write.limited++; return; }
  stats[kind].fail++;
  failures.push({ at: stamp(), kind, label, status: res.status, error: res.error, ms: res.ms });
  console.log(`${stamp()} FAIL ${kind} ${label} status=${res.status}${res.error ? ` ${res.error}` : ''} ${res.ms} ms`);
}

const reads = ['/release-notes', '/stats'];
let tick = 0;
let readCarry = 0;
let writeCarry = 0;
const pending = new Set();
const timer = setInterval(() => {
  const elapsed = (Date.now() - started) / 1000;
  if (elapsed >= seconds) return;
  tick++;
  readCarry += readsPerSecond / 5;
  writeCarry += writesPerSecond / 5;
  for (; readCarry >= 1; readCarry--) {
    const path = reads[tick % reads.length];
    const p = send('GET', path).then((res) => record('read', path, res));
    pending.add(p); p.finally(() => pending.delete(p));
  }
  for (; writeCarry >= 1; writeCarry--) {
    const body = JSON.stringify({ probe: ++counter });
    const p = send('POST', '/api/v1/installs/link-code', signedHeaders('POST', '/api/v1/installs/link-code', body), body).then((res) => record('write', 'link-code', res));
    pending.add(p); p.finally(() => pending.delete(p));
  }
}, 200);

await new Promise((resolve) => setTimeout(resolve, seconds * 1000 + 100));
clearInterval(timer);
await Promise.all(pending);
const summary = { host: hostName, seconds, reads: stats.read, writes: stats.write, install: state.id, failures };
console.log(`SUMMARY reads ok=${stats.read.ok} failed=${stats.read.fail}; writes ok=${stats.write.ok} failed=${stats.write.fail} rate-limited=${stats.write.limited}`);
if (failures.length) {
  const writeFails = failures.filter((f) => f.kind === 'write');
  if (writeFails.length) console.log(`write failures from ${writeFails[0].at} to ${writeFails.at(-1).at}`);
}
console.log(`JSON ${JSON.stringify(summary)}`);
process.exit(stats.read.fail === 0 ? 0 : 1);
