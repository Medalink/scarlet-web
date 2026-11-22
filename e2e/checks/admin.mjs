import { Jar, NAME, STRICT, Skip, expectStatus } from "../support.mjs";
import { signIn } from "./pages.mjs";

const PAGES = ["/admin", "/admin/api", "/admin/database", "/admin/ingest"];
const ADMIN = "development:development-1";

/** Every admin page answers 404 to a visitor, so nothing says it exists. */
export async function adminIsHiddenFromVisitors(ctx) {
  for (const route of PAGES) {
    const answer = await ctx.http.page(route, { redirect: "manual" });
    if (answer.status !== 404) throw new Error(`${route}: expected 404 for a visitor, saw ${answer.status}: ${answer.text.slice(0, 300)}`);
  }
}

/**
 * The development sign-in, listed in SCARLET_ADMINS by e2e/boot.mjs, opens the four pages privately, and /admin/api
 * counts the upload the lane made and a character it asked for by name.
 */
export async function adminSeesTheApiCalls(ctx) {
  const jar = new Jar();
  await signIn(ctx.http, jar);
  expectStatus(await ctx.http.api(`/api/v1/public/characters/${NAME}`), 200, `GET /api/v1/public/characters/${NAME}`);

  const first = await ctx.http.request(PAGES[0], { jar, headers: { Accept: "text/html" } });
  if (first.status === 404) {
    const off = `${ADMIN} is not in SCARLET_ADMINS on this server`;
    if (STRICT) throw new Error(`${off} (E2E_STRICT=1 means e2e/boot.mjs started it with that setting)`);
    throw new Skip(off);
  }
  for (const route of PAGES) {
    const answer = expectStatus(await ctx.http.request(route, { jar, headers: { Accept: "text/html" } }), 200, `GET ${route} as the admin`);
    const cache = answer.headers.get("cache-control") ?? "";
    if (!cache.includes("no-store") || !cache.includes("private")) throw new Error(`${route}: expected Cache-Control private, no-store, saw ${cache}`);
    if (!(answer.headers.get("x-robots-tag") ?? "").includes("noindex")) throw new Error(`${route}: expected X-Robots-Tag noindex`);
    if (route !== "/admin/api") continue;
    const uploads = Number((answer.text.match(/Upload requests<\/dt><dd>([\d,]+)/)?.[1] ?? "0").replaceAll(",", ""));
    if (uploads === 0) throw new Error("/admin/api: expected the upload requests of the lane to be counted, saw none");
    if (!answer.text.includes(`>${NAME}</a>`)) throw new Error(`/admin/api: expected it to list ${NAME} among the characters looked up`);
  }
}
