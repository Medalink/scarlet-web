/**
 * How a script gets an API key from a local site the way a person does: the development sign-in, its Battle.net
 * identity (`as=battlenet`, which exists only where the development sign-in does) and the API keys module of
 * /account/api-keys, called over Livewire's own HTTP endpoint. Every function takes `request(path, { method, headers,
 * body })`, which answers `{ status, headers, text }` and keeps the cookies of one visitor.
 */

const FORM = { "Content-Type": "application/x-www-form-urlencoded", Accept: "text/html" };
const SETTINGS = "/account/api-keys";
const COMPONENT = "account.api-keys";

const tokenIn = (html) => {
  const token = html.match(/name="_token" value="([^"]+)"/)?.[1];
  if (!token) throw new Error(`the page carries no CSRF token to send back: ${html.slice(0, 200)}`);
  return token;
};

const form = (fields) => ({ method: "POST", headers: FORM, body: new URLSearchParams(fields).toString() });

const decode = (text) => text.replaceAll("&quot;", '"').replaceAll("&#039;", "'").replaceAll("&amp;", "&");

/** A `request` function for `keys-client` over `fetch`, with the cookie jar of one visitor of the site at `base`. */
export function visitor(base) {
  const cookies = new Map();
  return async (path, { method = "GET", headers = {}, body } = {}) => {
    const sent = { ...headers };
    if (cookies.size > 0) sent.Cookie = [...cookies].map(([name, value]) => `${name}=${value}`).join("; ");
    const response = await fetch(base + path, { method, headers: sent, body, redirect: "manual" });
    for (const cookie of response.headers.getSetCookie()) {
      const [pair] = cookie.split(";");
      const at = pair.indexOf("=");
      if (at > 0) cookies.set(pair.slice(0, at).trim(), pair.slice(at + 1).trim());
    }
    return { status: response.status, headers: response.headers, text: await response.text() };
  };
}

/** Signs the visitor in with the development sign-in. */
export async function signInDevelopment(request) {
  const login = await request("/login", { headers: { Accept: "text/html" } });
  if (!login.text.includes('action="/auth/development"')) throw new Error("the development sign-in is off: set SCARLET_DEV_LOGIN=true in the server's environment");
  const answer = await request("/auth/development", form({ _token: tokenIn(login.text) }));
  if (answer.status !== 302 || !(answer.headers.get("location") ?? "").endsWith("/account")) {
    throw new Error(`POST /auth/development: expected a redirect to /account, saw ${answer.status} ${answer.headers.get("location")}`);
  }
}

/** Links the development Battle.net identity to the signed-in account, unless it is linked already. */
export async function linkBattlenet(request) {
  const page = await request(SETTINGS, { headers: { Accept: "text/html" } });
  if (!page.text.includes('id="key-battlenet"')) return;
  const answer = await request("/auth/development", form({ _token: tokenIn(page.text), as: "battlenet" }));
  if (answer.status !== 302 || !(answer.headers.get("location") ?? "").endsWith("/account")) {
    throw new Error(`POST /auth/development as=battlenet: expected a redirect to /account, saw ${answer.status} ${answer.headers.get("location")}`);
  }
}

/** Ages the signed-in account's keys named `name` past their expiry (development sign-in only). */
export async function expireKeys(request, name) {
  const page = await request(SETTINGS, { headers: { Accept: "text/html" } });
  const answer = await request("/auth/development/expire-keys", form({ _token: tokenIn(page.text), name }));
  if (answer.status !== 302) throw new Error(`POST /auth/development/expire-keys: expected a redirect, saw ${answer.status}: ${answer.text.slice(0, 200)}`);
}

/** Removes the signed-in account's Battle.net identity and leaves its keys (development sign-in only). */
export async function dropBattlenet(request) {
  const page = await request(SETTINGS, { headers: { Accept: "text/html" } });
  const answer = await request("/auth/development/drop-battlenet", form({ _token: tokenIn(page.text) }));
  if (answer.status !== 302) throw new Error(`POST /auth/development/drop-battlenet: expected a redirect, saw ${answer.status}: ${answer.text.slice(0, 200)}`);
}

/** The HTML the API keys module draws after `calls` ran with `updates` set on its properties. */
export async function callKeys(request, updates = {}, calls = []) {
  const page = await request(SETTINGS, { headers: { Accept: "text/html" } });
  const csrf = page.text.match(/data-csrf="([^"]+)"/)?.[1];
  const uri = page.text.match(/data-update-uri="([^"]+)"/)?.[1];
  const snapshot = [...page.text.matchAll(/wire:snapshot="([^"]+)"/g)].map(([, text]) => decode(text)).find((text) => text.includes(`"${COMPONENT}"`));
  if (!csrf || !uri || !snapshot) throw new Error(`${SETTINGS} does not carry the ${COMPONENT} component`);
  const answer = await request(new URL(uri, "http://local.invalid").pathname, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-Livewire": "1", Accept: "application/json" },
    body: JSON.stringify({ _token: csrf, components: [{ snapshot, updates, calls }] }),
  });
  const html = JSON.parse(answer.text)?.components?.[0]?.effects?.html;
  if (answer.status !== 200 || typeof html !== "string") throw new Error(`the Livewire update answered ${answer.status}: ${answer.text.slice(0, 300)}`);
  return html;
}

/** Makes a key named `name` that lasts `days`; `key` is its text, or null when the module refused, with the module's HTML either way. */
export async function makeKey(request, name, days) {
  const html = await callKeys(request, { name, days }, [{ path: "", method: "make", params: [] }]);
  const key = html.match(/class="sc-secret"[\s\S]*?<code>([^<]+)<\/code>/)?.[1] ?? null;
  return { key, html };
}

/** Deletes the keys named `name` that the module lists, one by one; how many it deleted. */
export async function deleteKeys(request, name) {
  let deleted = 0;
  const marker = `wire:confirm="Delete the key ${name}?`;
  for (;;) {
    const html = await callKeys(request);
    const at = html.indexOf(marker);
    const id = at < 0 ? undefined : html.slice(0, at).match(/delete\((\d+)\)"\s*$/)?.[1];
    if (id === undefined) return deleted;
    await callKeys(request, {}, [{ path: "", method: "delete", params: [Number(id)] }]);
    deleted += 1;
  }
}

/** A key for a local site: signs in, links Battle.net and makes a key of `days` days. */
export async function keyFor(request, name, days = 30) {
  await signInDevelopment(request);
  await linkBattlenet(request);
  const { key, html } = await makeKey(request, name, days);
  if (key === null) throw new Error(`the API keys module made no key: ${html.slice(0, 300)}`);
  return key;
}
