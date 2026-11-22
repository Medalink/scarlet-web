#!/usr/bin/env node
// Creates the scarlet droplets on DigitalOcean: the ssh key, the tags, the firewall, the volume, then the droplet, in that
// order, so the firewall guards the droplet from its first boot. Run from a machine that holds the secrets folder.
//
//   node deploy/provision.mjs                 the app droplet scarlet-web (first install)
//   node deploy/provision.mjs db              the database droplet scarlet-db with its 50 GB volume and the firewall scarlet-web-db
//   node deploy/provision.mjs web --env load  the rehearsal app droplet scarlet-load-app
//   node deploy/provision.mjs db --env load   the rehearsal database droplet scarlet-load-db
//   node deploy/provision.mjs gen --env load  the load generator droplet scarlet-load-gen (it alone may reach the rehearsal app on 80 and 443)
//   node deploy/provision.mjs destroy --env load   delete the rehearsal droplets, volume, firewalls and tags (without --set only scarlet-load-app, -db, -gen, -db-data and their tags)
//   node deploy/provision.mjs retag           give the app droplet the tag scarlet-app, let 5432 in from that tag only and take
//                                             scarlet-web off the database droplet
//   node deploy/provision.mjs web --name scarlet-app-2    a further app droplet (scarlet-app-<x>; in the load environment
//                                             scarlet-load-<x> or, with --set, scarlet-load-<set>-<x>), tagged like the first
//   node deploy/provision.mjs redis --droplet scarlet-web   the firewall scarlet-web-redis: tcp 6379-6380 from the tag scarlet-app
//                                             to the droplet tagged scarlet-redis (bin/deploy hosts prepare runs it before Redis
//                                             listens on the VPC address)
//   --set <name>      with --env load: the rehearsal droplets are scarlet-load-<name>-app, -db, -gen; destroy touches only those
//   add --plan to print what would be created
//
// SCARLET_SECRETS is the folder with do-token, ops_ed25519.pub and tailscale-authkey (default: E:/Dev/secrets/scarlet-web
// on Windows, ~/.config/scarlet-web elsewhere). Nothing is printed from those files and nothing is written back.
// It touches only resources named scarlet-web, scarlet-db or scarlet-load*; the app droplet is tagged scarlet-web and scarlet-app,
// the database droplet scarlet-db (never scarlet-web, so the public firewall does not apply to it). The rehearsal droplets carry
// only scarlet-load* tags, so no production firewall or tag rule ever reaches them. It never edits another
// droplet, firewall or key. The database droplet is reachable on tcp 5432 from the app droplet's tag only; there is no
// inbound 22 anywhere: ssh goes over Tailscale, and a new node is signed here when Tailnet Lock is on.
import { execFile } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const run = promisify(execFile);
const args = process.argv.slice(2);
const flag = (name) => args.includes(name);
const option = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
const verb = args.find((a) => ['web', 'db', 'gen', 'destroy', 'retag', 'redis'].includes(a)) ?? 'web';
const env = option('--env', 'prod');
const plan = flag('--plan');
if (!['prod', 'load'].includes(env)) throw new Error('--env is prod or load');

const REGION = 'nyc3';
const IMAGE = 'ubuntu-24-04-x64';
const TAG = 'scarlet-web';
const APP_TAG = 'scarlet-app';
const prod = env === 'prod';
const set = option('--set', '');
if (set && (prod || !/^[a-z0-9]+$/.test(set))) throw new Error('--set is one lowercase word and needs --env load');
const base = set ? `scarlet-load-${set}` : 'scarlet-load';
const prefix = `${base}-`;
const extraName = option('--name', '');
const roles = {
  web: {
    name: extraName || (prod ? 'scarlet-web' : `${prefix}app`), size: 's-2vcpu-4gb', template: 'cloud-init.yaml', backups: prod,
    tags: prod ? [TAG, APP_TAG] : [base, `${prefix}app`], firewall: prod ? 'scarlet-web' : `${prefix}app`, firewallTag: prod ? TAG : `${prefix}app`,
  },
  db: {
    name: prod ? 'scarlet-db' : `${prefix}db`, size: 's-1vcpu-2gb', template: 'cloud-init-db.yaml', backups: false,
    tags: prod ? ['scarlet-db'] : [base, `${prefix}db`], firewall: prod ? 'scarlet-web-db' : `${prefix}db`, firewallTag: prod ? 'scarlet-db' : `${prefix}db`,
    volume: { name: prod ? 'scarlet-db-data' : `${prefix}db-data`, gigabytes: 50, tags: prod ? [TAG] : [base] },
    appTag: prod ? APP_TAG : `${prefix}app`,
  },
  gen: {
    name: `${prefix}gen`, size: 's-4vcpu-8gb', template: 'cloud-init.yaml', backups: false,
    tags: [base, `${prefix}gen`], firewall: `${prefix}gen`, firewallTag: `${prefix}gen`,
  },
};

const secrets = process.env.SCARLET_SECRETS ?? (process.platform === 'win32' ? 'E:/Dev/secrets/scarlet-web' : join(homedir(), '.config/scarlet-web'));
const read = async (name) => (await readFile(join(secrets, name), 'utf8')).trim();

const token = await read('do-token');
async function api(method, path, body) {
  const res = await fetch(`https://api.digitalocean.com/v2/${path}`, {
    method,
    headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  if (!res.ok && res.status !== 404) throw new Error(`${method} ${path}: ${res.status} ${text.slice(0, 300)}`);
  return text ? JSON.parse(text) : {};
}
const mine = (name) => /^scarlet-(web|db|app-[a-z0-9-]+|load-.+)$/.test(name) || name === 'scarlet-web-db';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const loadNames = new Set([`${prefix}app`, `${prefix}db`, `${prefix}gen`]);
const ownsName = (name) => (set ? name.startsWith(prefix) : loadNames.has(name));
const ownsTag = (name) => (set ? name === base || name.startsWith(prefix) : name === base || loadNames.has(name));

async function destroy() {
  if (prod) throw new Error('destroy only runs with --env load');
  const droplets = (await api('GET', 'droplets?per_page=200')).droplets.filter((d) => ownsName(d.name));
  const volumes = (await api('GET', 'volumes?per_page=200')).volumes.filter((v) => (set ? ownsName(v.name) : v.name === `${prefix}db-data`));
  const firewalls = (await api('GET', 'firewalls?per_page=200')).firewalls.filter((f) => ownsName(f.name));
  const tags = (await api('GET', 'tags?per_page=200')).tags.filter((t) => ownsTag(t.name));
  console.log(`destroy: ${droplets.map((d) => d.name).join(', ') || 'no droplets'}; ${volumes.map((v) => v.name).join(', ') || 'no volumes'}; ${firewalls.map((f) => f.name).join(', ') || 'no firewalls'}; ${tags.map((t) => t.name).join(', ') || 'no tags'}`);
  if (plan) return;
  for (const d of droplets) {
    await run('ssh', ['-i', join(secrets, 'ops_ed25519'), '-o', 'IdentitiesOnly=yes', '-o', 'ConnectTimeout=8', `ops@${d.name}`, 'sudo tailscale logout'], { timeout: 20000 }).catch(() => {});
    await api('DELETE', `droplets/${d.id}`);
  }
  for (let i = 0; i < 40 && (await api('GET', 'droplets?per_page=200')).droplets.some((d) => ownsName(d.name)); i++) await sleep(3000);
  for (const v of volumes) {
    for (let i = 0; ; i++) {
      try {
        await api('DELETE', `volumes/${v.id}`);
        break;
      } catch (e) {
        if (i >= 20 || !String(e.message).includes('attached')) throw e;
        await sleep(3000);
      }
    }
  }
  for (const f of firewalls) await api('DELETE', `firewalls/${f.id}`);
  for (const t of tags) await api('DELETE', `tags/${t.name}`);
  console.log('destroy: done');
}

async function retag() {
  if (!prod) throw new Error('retag only runs with --env prod');
  const droplets = (await api('GET', 'droplets?per_page=200')).droplets;
  const app = droplets.find((d) => d.name === 'scarlet-web');
  const db = droplets.find((d) => d.name === 'scarlet-db');
  const fw = (await api('GET', 'firewalls?per_page=200')).firewalls.find((f) => f.name === 'scarlet-web-db');
  if (!app || !db || !fw) throw new Error('scarlet-web, scarlet-db and the firewall scarlet-web-db must all exist');
  console.log(`retag: ${app.name} gets ${APP_TAG}; ${fw.name} accepts 5432 from ${APP_TAG} only; ${db.name} loses ${TAG} (tags now: ${db.tags.join(' ')})`);
  if (plan) return;
  const droplet = (d) => ({ resources: [{ resource_id: String(d.id), resource_type: 'droplet' }] });
  await api('POST', 'tags', { name: APP_TAG });
  await api('POST', `tags/${APP_TAG}/resources`, droplet(app));
  const put = (sources) => api('PUT', `firewalls/${fw.id}`, {
    name: fw.name, droplet_ids: fw.droplet_ids, tags: fw.tags, outbound_rules: fw.outbound_rules,
    inbound_rules: fw.inbound_rules.map((r) => (r.ports === '5432' ? { ...r, sources: { tags: sources } } : r)),
  });
  await put([TAG, APP_TAG]);
  await put([APP_TAG]);
  if (db.tags.includes(TAG)) await api('DELETE', `tags/${TAG}/resources`, droplet(db));
  console.log('retag: done');
}

if (verb === 'destroy') {
  await destroy();
  process.exit(0);
}
if (verb === 'retag') {
  await retag();
  process.exit(0);
}

const role = roles[verb] ?? roles.web;
if (!mine(role.name)) throw new Error(`refusing to touch ${role.name}`);

const cloudflare = async (family) => (await (await fetch(`https://www.cloudflare.com/ips-${family}`)).text()).split('\n').map((s) => s.trim()).filter(Boolean);
const everywhere = { addresses: ['0.0.0.0/0', '::/0'] };
const outbound = [
  { protocol: 'tcp', ports: 'all', destinations: everywhere },
  { protocol: 'udp', ports: 'all', destinations: everywhere },
  { protocol: 'icmp', destinations: everywhere },
];
async function redisFirewall() {
  const name = option('--droplet', '');
  if (!mine(name)) throw new Error(`refusing to touch ${name}`);
  const droplet = (await api('GET', 'droplets?per_page=200')).droplets.find((d) => d.name === name);
  if (!droplet) throw new Error(`droplet ${name} does not exist`);
  const production = name === 'scarlet-web';
  const source = production ? APP_TAG : name;
  const tag = production ? 'scarlet-redis' : `${name}-redis`;
  const firewall = production ? 'scarlet-web-redis' : `${name}-redis`;
  if (!droplet.tags.includes(source)) throw new Error(`${name} does not carry the tag ${source}`);
  const exists = (await api('GET', 'firewalls?per_page=200')).firewalls.some((f) => f.name === firewall);
  console.log(`redis: ${firewall} accepts tcp 6379-6380 from tag ${source} on droplets tagged ${tag}${exists ? ' (exists)' : ''}; ${name} ${droplet.tags.includes(tag) ? 'has' : 'gets'} ${tag}`);
  if (plan) return;
  await api('POST', 'tags', { name: tag });
  if (!exists) {
    await api('POST', 'firewalls', { name: firewall, tags: [tag], inbound_rules: [{ protocol: 'tcp', ports: '6379-6380', sources: { tags: [source] } }], outbound_rules: outbound });
  }
  if (!droplet.tags.includes(tag)) await api('POST', `tags/${tag}/resources`, { resources: [{ resource_id: String(droplet.id), resource_type: 'droplet' }] });
}
if (verb === 'redis') {
  await redisFirewall();
  process.exit(0);
}

async function firewallRules() {
  if (verb === 'db') return { inbound_rules: [{ protocol: 'tcp', ports: '5432', sources: { tags: [role.appTag] } }], outbound_rules: outbound };
  if (verb === 'gen') return { inbound_rules: [], outbound_rules: outbound };
  if (!prod) {
    const gen = { tags: ['scarlet-load-gen'] };
    return { inbound_rules: [{ protocol: 'tcp', ports: '80', sources: gen }, { protocol: 'tcp', ports: '443', sources: gen }], outbound_rules: outbound };
  }
  const cfSources = { addresses: [...(await cloudflare('v4')), ...(await cloudflare('v6'))] };
  return { inbound_rules: [{ protocol: 'tcp', ports: '80', sources: cfSources }, { protocol: 'tcp', ports: '443', sources: cfSources }], outbound_rules: outbound };
}

const existing = (await api('GET', 'droplets?per_page=200')).droplets.find((d) => d.name === role.name);
if (existing) {
  console.log(`droplet ${role.name} already exists (id ${existing.id}); nothing to create`);
  process.exit(0);
}

if (plan) {
  const v = role.volume ? `, volume ${role.volume.name} ${role.volume.gigabytes} GB` : '';
  console.log(`would create: droplet ${role.name} ${role.size} ${REGION}${v}, tags ${role.tags.join(' ')}, firewall ${role.firewall}`);
  process.exit(0);
}

const publicKey = await read('ops_ed25519.pub');
const userData = (await readFile(join(dirname(fileURLToPath(import.meta.url)), role.template), 'utf8'))
  .replaceAll('__OPS_SSH_PUBKEY__', publicKey)
  .replaceAll('__TAILSCALE_AUTHKEY__', await read('tailscale-authkey'))
  .replaceAll('__HOSTNAME__', role.name)
  .replaceAll('__VOLUME__', role.volume?.name ?? '');
if (/__[A-Z_]+__/.test(userData)) throw new Error('the cloud-init file still has an unfilled placeholder');

const keys = (await api('GET', 'account/keys?per_page=200')).ssh_keys;
const keyBody = publicKey.split(' ').slice(0, 2).join(' ');
let key = keys.find((k) => k.public_key.split(' ').slice(0, 2).join(' ') === keyBody);
key ??= (await api('POST', 'account/keys', { name: 'scarlet-web-ops', public_key: publicKey })).ssh_key;

for (const tag of role.tags) await api('POST', 'tags', { name: tag });

if (role.firewall) {
  const firewalls = (await api('GET', 'firewalls?per_page=200')).firewalls;
  if (!firewalls.some((f) => f.name === role.firewall)) {
    await api('POST', 'firewalls', { name: role.firewall, tags: [role.firewallTag], ...(await firewallRules()) });
  }
}

let volume;
if (role.volume) {
  const volumes = (await api('GET', 'volumes?per_page=200')).volumes;
  volume = volumes.find((v) => v.name === role.volume.name)
    ?? (await api('POST', 'volumes', { name: role.volume.name, region: REGION, size_gigabytes: role.volume.gigabytes, filesystem_type: 'ext4', filesystem_label: 'scarlet-db-data', tags: role.volume.tags })).volume;
}

const { droplet } = await api('POST', 'droplets', {
  name: role.name, region: REGION, size: role.size, image: IMAGE,
  ssh_keys: [key.id], backups: role.backups, monitoring: true, ipv6: prod && verb === 'web', tags: role.tags, user_data: userData,
  ...(volume ? { volumes: [volume.id] } : {}),
});
console.log(`created droplet ${droplet.name} id ${droplet.id}; waiting for an address`);
let now;
for (let i = 0; i < 60; i++) {
  await sleep(5000);
  now = (await api('GET', `droplets/${droplet.id}`)).droplet;
  if (now.status === 'active' && now.networks.v4.some((n) => n.type === 'public')) break;
}
if (now?.status !== 'active') throw new Error('the droplet did not become active in 5 minutes');
const address = (type) => now.networks.v4.find((n) => n.type === type)?.ip_address;
console.log(JSON.stringify({ id: now.id, name: now.name, size: now.size_slug, status: now.status, ipv4: address('public'), private: address('private'), vpc: now.vpc_uuid, features: now.features, tags: now.tags, volumes: now.volume_ids }));

const tailscale = process.platform === 'win32' ? 'tailscale' : 'tailscale';
let signed = false;
for (let i = 0; i < 90 && !signed; i++) {
  await sleep(5000);
  let out = '';
  try { out = (await run(tailscale, ['lock', 'status'])).stdout; } catch (e) { out = `${e.stdout ?? ''}`; }
  const own = new RegExp(`^\\s*${role.name}(-\\d+)?\\.`);
  const line = out.split('\n').find((l) => own.test(l) && /nodekey:[0-9a-f]+/.test(l));
  const nodekey = line?.match(/nodekey:[0-9a-f]+/)?.[0];
  if (nodekey) {
    await run(tailscale, ['lock', 'sign', nodekey]);
    signed = true;
    console.log(`signed ${line.trim().split(/\s+/)[0]} (${nodekey.slice(0, 16)}...) in the tailnet`);
  }
}
if (!signed) throw new Error(`${role.name} did not show up as a locked node; check the Tailscale auth key (a used one-use key fails quietly) and tailscale lock status`);
