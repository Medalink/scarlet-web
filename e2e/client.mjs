/**
 * The upload client of the end-to-end lane, written from docs/API.md alone. The install's Ed25519 key is derived
 * from a label and never stored: the seed is the SHA-256 of the label, so one label is one install on every run and a
 * run that crashed is cleaned up by the next one. A signed request carries Scarlet-Install, Scarlet-Time and
 * Scarlet-Signature over `METHOD \n path \n time \n SHA-256(body)`. The server refuses a signature it saw in the last
 * 600 seconds and Ed25519 is deterministic, so the client never signs one request twice in the same second.
 */

import crypto from "node:crypto";
import zlib from "node:zlib";
import { parseJson } from "./support.mjs";

const PKCS8_SEED_PREFIX = Buffer.from("302e020100300506032b657004220420", "hex");
const MANIFEST_TYPE = "application/vnd.scarlet.manifest+zstd";
const PARTS_TYPE = "application/vnd.scarlet.parts+zstd";
const JSON_TYPE = "application/json";

export class InstallClient {
  #base;
  #label;
  #privateKey;
  #publicKey;
  #fetch;
  #installId = null;
  #lastSecond = new Map();

  constructor(base, label, fetchImpl = fetch) {
    this.#base = base.replace(/\/+$/, "");
    this.#label = label;
    this.#fetch = fetchImpl;
    const seed = crypto.createHash("sha256").update(label, "utf8").digest();
    this.#privateKey = crypto.createPrivateKey({ key: Buffer.concat([PKCS8_SEED_PREFIX, seed]), format: "der", type: "pkcs8" });
    this.#publicKey = crypto.createPublicKey(this.#privateKey).export({ format: "der", type: "spki" }).subarray(-32);
  }

  get installId() {
    return this.#installId;
  }

  /** POST /api/v1/installs: registers the derived key, or finds the install it already has. */
  async register() {
    const answer = await this.request("POST", "/api/v1/installs", {
      body: JSON.stringify({ public_key: this.#publicKey.toString("base64"), name: this.#label }),
      sign: false,
    });
    if (typeof answer.json?.install?.id === "string") this.#installId = answer.json.install.id;
    return answer;
  }

  me(options) {
    return this.request("GET", "/api/v1/installs/me", options);
  }

  linkCode(options) {
    return this.request("POST", "/api/v1/installs/link-code", options);
  }

  sync(character, groups, count, digest) {
    return this.request("POST", "/api/v1/upload/sync", { body: JSON.stringify({ character, groups, count, digest }) });
  }

  manifest(character, groups, parts) {
    const body = zlib.zstdCompressSync(Buffer.from(JSON.stringify({ character, groups, parts }), "utf8"));
    return this.request("POST", "/api/v1/upload/manifest", { type: MANIFEST_TYPE, body });
  }

  parts(texts) {
    return this.request("POST", "/api/v1/upload/parts", { type: PARTS_TYPE, body: zlib.zstdCompressSync(partsFrame(texts)) });
  }

  status() {
    return this.request("GET", "/api/v1/upload/status");
  }

  remove() {
    return this.request("DELETE", "/api/v1/upload");
  }

  /** One request: `sign: false` sends it unsigned, `corrupt: true` signs it wrong on purpose. */
  async request(method, path, { body = null, type = JSON_TYPE, sign = true, corrupt = false } = {}) {
    const headers = {};
    if (body !== null) headers["Content-Type"] = type;
    if (sign) {
      if (this.#installId === null) throw new Error(`register before signing ${method} ${path}`);
      const second = corrupt ? Math.floor(Date.now() / 1000) : await this.#secondFor(method, path, body ?? "");
      headers["Scarlet-Install"] = this.#installId;
      headers["Scarlet-Time"] = String(second);
      headers["Scarlet-Signature"] = this.#sign(method, path, second, body ?? "", corrupt).toString("base64");
    }
    const response = await this.#fetch(this.#base + path, { method, headers, body: body ?? undefined });
    const text = await response.text();
    return { status: response.status, headers: response.headers, text, json: parseJson(text) };
  }

  async #secondFor(method, path, body) {
    const key = `${method} ${path} ${hash(body)}`;
    const now = Math.floor(Date.now() / 1000);
    const second = Math.max(now, (this.#lastSecond.get(key) ?? -1) + 1);
    this.#lastSecond.set(key, second);
    if (second > now) await new Promise((resolve) => setTimeout(resolve, second * 1000 - Date.now() + 5));
    return second;
  }

  #sign(method, path, second, body, corrupt) {
    const message = Buffer.from(`${method}\n${path}\n${second}\n${hash(body)}`, "utf8");
    const signature = crypto.sign(null, message, this.#privateKey);
    if (corrupt) signature[0] ^= 0xff;
    return signature;
  }
}

function hash(body) {
  return crypto.createHash("sha256").update(typeof body === "string" ? Buffer.from(body, "utf8") : body).digest("hex");
}

/** The body of `/upload/parts` before compression: each part text prefixed with its u32 little-endian byte length. */
export function partsFrame(texts) {
  const chunks = [];
  for (const text of texts) {
    const bytes = Buffer.from(text, "utf8");
    const head = Buffer.alloc(4);
    head.writeUInt32LE(bytes.length, 0);
    chunks.push(head, bytes);
  }
  return Buffer.concat(chunks);
}

/** The XOR of 64-hex part ids as 64 lower-case hex characters. */
export function xorDigest(ids) {
  const digest = Buffer.alloc(32);
  for (const id of ids) {
    const bytes = Buffer.from(id, "hex");
    for (let i = 0; i < 32; i += 1) digest[i] ^= bytes[i];
  }
  return digest.toString("hex");
}
