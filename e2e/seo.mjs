#!/usr/bin/env node
// Asks a running site what a search engine and a link preview would ask: robots.txt, the sitemap files, the head of
// the pages they list, the pages that must stay out of search results, the share image and the JSON answers.
//
//   node e2e/seo.mjs [base URL, default http://127.0.0.1:8470] [pages read per sitemap file, default 15]
//
// It reads every page of /sitemaps/pages.xml and the first pages of each other file, so it stays under the page rate
// limit. It prints one line per failure and exits 1 when there is one.

const base = (process.argv[2] ?? 'http://127.0.0.1:8470').replace(/\/$/, '');
const perFile = Number(process.argv[3] ?? 15);
const failures = [];
let checks = 0;

function expect(ok, what) {
  checks += 1;
  if (!ok) failures.push(what);
}

async function get(path) {
  const response = await fetch(base + path, { redirect: 'manual' });
  return { status: response.status, headers: response.headers, body: Buffer.from(await response.arrayBuffer()) };
}

const pathOf = (url) => new URL(url).pathname;
const locs = (xml) => [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1].replaceAll('&amp;', '&'));
const tags = (html, pattern) => [...html.matchAll(pattern)].map((m) => m[1]);
const meta = (html, key) => tags(html, new RegExp(`<meta (?:name|property)="${key}" content="([^"]*)">`, 'g'));

async function head(path) {
  const page = await get(path);
  const html = page.body.toString('utf8');
  return { status: page.status, html, canonical: tags(html, /<link rel="canonical" href="([^"]*)">/g), robots: meta(html, 'robots') };
}

async function indexed(path, canonicalPath = path) {
  const page = await head(path);
  expect(page.status === 200, `${path}: status ${page.status}`);
  expect(page.canonical.length === 1 && pathOf(page.canonical[0]) === canonicalPath, `${path}: canonical ${page.canonical.join(' ') || 'missing'}, expected ${canonicalPath}`);
  expect(page.robots.length === 0, `${path}: robots ${page.robots.join(' ')}`);
  expect(/<title>[^<]{10,}<\/title>/.test(page.html), `${path}: title missing or short`);
  for (const key of ['description', 'og:title', 'og:description', 'og:url', 'og:image', 'og:site_name', 'twitter:card']) {
    const found = meta(page.html, key);
    expect(found.length === 1 && found[0] !== '' && !found[0].includes('&amp;#'), `${path}: ${key} ${found.length === 1 ? `is "${found[0]}"` : `appears ${found.length} times`}`);
  }
}

async function hidden(path) {
  const page = await head(path);
  expect(page.status === 200, `${path}: status ${page.status}`);
  expect(page.robots.length === 1 && page.robots[0].includes('noindex'), `${path}: no noindex`);
  expect(page.canonical.length === 0, `${path}: a noindex page names a canonical`);
}

async function image(path, width, height) {
  const file = await get(path);
  const png = file.status === 200 && file.body.subarray(1, 4).toString('latin1') === 'PNG';
  expect(png && file.body.readUInt32BE(16) === width && file.body.readUInt32BE(20) === height, `${path}: not a ${width} by ${height} PNG`);
}

const robots = await get('/robots.txt');
expect(robots.status === 200 && /^Sitemap: https?:\/\/\S+\/sitemap\.xml$/m.test(robots.body.toString('utf8')), '/robots.txt: no Sitemap line');

const index = await get('/sitemap.xml');
expect(index.status === 200 && (index.headers.get('content-type') ?? '').includes('xml'), `/sitemap.xml: status ${index.status}, ${index.headers.get('content-type')}`);
const files = locs(index.body.toString('utf8')).map(pathOf);
expect(files.includes('/sitemaps/pages.xml'), '/sitemap.xml: /sitemaps/pages.xml is not listed');

let pages = 0;
let firstRecord = null;
for (const file of files) {
  const answer = await get(file);
  expect(answer.status === 200 && (answer.headers.get('content-type') ?? '').includes('xml'), `${file}: status ${answer.status}, ${answer.headers.get('content-type')}`);
  const listed = locs(answer.body.toString('utf8')).map(pathOf);
  expect(listed.length > 0 && new Set(listed).size === listed.length, `${file}: ${listed.length} addresses, ${listed.length - new Set(listed).size} repeated`);
  const read = file === '/sitemaps/pages.xml' ? listed : listed.slice(0, perFile);
  for (const path of read) {
    await indexed(path);
    pages += 1;
  }
  if (file.startsWith('/sitemaps/creatures-')) firstRecord ??= listed[0];
}

expect((await get('/sitemaps/creatures-9999.xml')).status === 404, '/sitemaps/creatures-9999.xml: not 404');
if (firstRecord) await indexed(`${firstRecord}?server=pvp&x=1`, firstRecord);
await indexed('/stats/', '/stats');
await indexed('/camps/', '/camps');
await indexed('/camps?server=pvp&x=1', '/camps');
await indexed('/professions/fishing?server=pvp&x=1', '/professions/fishing');
await hidden('/search?q=wolf');
await hidden('/login');
await image('/share.png', 1200, 630);
await image('/apple-touch-icon.png', 180, 180);
const api = await get('/api/v1/public/stats');
expect(api.headers.get('x-robots-tag') === 'noindex', `/api/v1/public/stats: X-Robots-Tag ${api.headers.get('x-robots-tag')}`);

for (const failure of failures) console.log(`FAIL ${failure}`);
console.log(`${files.length} sitemap files, ${pages} listed pages read, ${checks} checks, ${failures.length} failed`);
process.exit(failures.length ? 1 : 0);
