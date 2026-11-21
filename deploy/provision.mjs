#!/usr/bin/env node
// Creates the scarlet-web droplet on DigitalOcean: the ssh key, the tag, the firewall, then the droplet, in that order,
// so the firewall guards the droplet from its first boot. Run once from a machine that holds the secrets folder.
//
//   node deploy/provision.mjs            create what is missing
//   node deploy/provision.mjs --plan     print what it would create
//
// SCARLET_SECRETS is the folder with do-token, ops_ed25519.pub and tailscale-authkey (default: E:/Dev/secrets/scarlet-web
// on Windows, ~/.config/scarlet-web elsewhere). Nothing is printed from those files and nothing is written back.
// It touches only resources named scarlet-web and the tag scarlet-web. It never edits another droplet, firewall or key.
import { readFile } from 'node:fs/promises';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const NAME = 'scarlet-web';
const REGION = 'nyc3';
const SIZE = 's-2vcpu-4gb';
const IMAGE = 'ubuntu-24-04-x64';
const plan = process.argv.includes('--plan');
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

const cloudflare = async (family) => (await (await fetch(`https://www.cloudflare.com/ips-${family}`)).text()).split('\n').map((s) => s.trim()).filter(Boolean);
const v4 = await cloudflare('v4');
const v6 = await cloudflare('v6');
const cfSources = { addresses: [...v4, ...v6] };
const everywhere = { addresses: ['0.0.0.0/0', '::/0'] };

const existing = (await api('GET', 'droplets?per_page=200')).droplets.find((d) => d.name === NAME);
if (existing) {
  console.log(`droplet ${NAME} already exists (id ${existing.id}); nothing to create`);
  process.exit(0);
}

const publicKey = await read('ops_ed25519.pub');
const userData = (await readFile(join(dirname(fileURLToPath(import.meta.url)), 'cloud-init.yaml'), 'utf8'))
  .replaceAll('__OPS_SSH_PUBKEY__', publicKey)
  .replaceAll('__TAILSCALE_AUTHKEY__', await read('tailscale-authkey'));
if (/__[A-Z_]+__/.test(userData)) throw new Error('cloud-init.yaml still has an unfilled placeholder');

const rules = {
  inbound_rules: [
    { protocol: 'tcp', ports: '80', sources: cfSources },
    { protocol: 'tcp', ports: '443', sources: cfSources },
  ],
  outbound_rules: [
    { protocol: 'tcp', ports: 'all', destinations: everywhere },
    { protocol: 'udp', ports: 'all', destinations: everywhere },
    { protocol: 'icmp', destinations: everywhere },
  ],
};

if (plan) {
  console.log(`would create: ssh key ops_ed25519, tag ${NAME}, firewall ${NAME} (80 and 443 from ${cfSources.addresses.length} Cloudflare ranges), droplet ${NAME} ${SIZE} ${REGION}`);
  process.exit(0);
}

// 1. the one ssh key
const keys = (await api('GET', 'account/keys?per_page=200')).ssh_keys;
const keyBody = publicKey.split(' ').slice(0, 2).join(' ');
let key = keys.find((k) => k.public_key.split(' ').slice(0, 2).join(' ') === keyBody);
key ??= (await api('POST', 'account/keys', { name: `${NAME}-ops`, public_key: publicKey })).ssh_key;

// 2. the tag
await api('POST', 'tags', { name: NAME });

// 3. the firewall, aimed at the tag only
const firewalls = (await api('GET', 'firewalls?per_page=200')).firewalls;
if (!firewalls.some((f) => f.name === NAME)) await api('POST', 'firewalls', { name: NAME, tags: [NAME], ...rules });

// 4. the droplet
const { droplet } = await api('POST', 'droplets', {
  name: NAME, region: REGION, size: SIZE, image: IMAGE,
  ssh_keys: [key.id], backups: true, monitoring: true, ipv6: true, tags: [NAME], user_data: userData,
});
console.log(`created droplet ${droplet.name} id ${droplet.id}; waiting for an address`);
for (let i = 0; i < 60; i++) {
  await new Promise((r) => setTimeout(r, 5000));
  const now = (await api('GET', `droplets/${droplet.id}`)).droplet;
  const v4pub = now.networks.v4.find((n) => n.type === 'public')?.ip_address;
  const v6pub = now.networks.v6.find((n) => n.type === 'public')?.ip_address;
  if (now.status === 'active' && v4pub) {
    console.log(JSON.stringify({ id: now.id, name: now.name, region: now.region.slug, size: now.size_slug, status: now.status, ipv4: v4pub, ipv6: v6pub, features: now.features, tags: now.tags }));
    process.exit(0);
  }
}
throw new Error('the droplet did not become active in 5 minutes');
