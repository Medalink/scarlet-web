/**
 * A small Chrome DevTools Protocol client over Node's global WebSocket, for the browser lane. The browser always
 * starts headless: `--headless=new` is a fixed element of ARGUMENTS, with no flag, mode or environment variable that
 * leaves it out, so no window can open on the desktop. A page is looked at through Page.captureScreenshot only.
 * Like e2e/shots.mjs it ignores the fonts.g console noise.
 */

import { spawn, spawnSync } from "node:child_process";
import fs from "node:fs";
import net from "node:net";
import path from "node:path";
import { ORIGIN_COOKIE, sleep } from "./support.mjs";

const NOISE = /fonts\.g/;
const BOOT_MS = 15_000;
const LOAD_MS = 10_000;
const POLL_MS = 25;
const CALL_MS = 15_000;
const ARGUMENTS = ["--headless=new", "--no-first-run", "--no-default-browser-check", "--disable-extensions", "--disable-background-networking", "--disable-features=NetworkPrediction,Preconnect"];

/** The browser the lane starts: `--browser <path>` wins, then Edge on Windows and chromium on Linux. */
export function browserCommand(explicit) {
  if (explicit) return explicit;
  if (process.platform === "win32") return "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";
  if (process.platform === "linux") return "chromium";
  throw new Error(`no browser is known for ${process.platform}: pass --browser <path>`);
}

function killTree(pid) {
  if (process.platform === "win32") {
    spawnSync("taskkill", ["/PID", String(pid), "/T", "/F"], { stdio: "ignore", windowsHide: true });
    return;
  }
  try {
    process.kill(-pid, "SIGKILL");
  } catch {
    try {
      process.kill(pid, "SIGKILL");
    } catch {
      return;
    }
  }
}

export class Browser {
  #child;
  #port;
  #scratch;
  #stopped = false;
  #onRequest;
  #onExit = () => this.stopNow();

  constructor(child, port, scratch, onRequest) {
    this.#child = child;
    this.#port = port;
    this.#scratch = scratch;
    this.#onRequest = onRequest;
    process.on("exit", this.#onExit);
  }

  get pid() {
    return this.#child.pid;
  }

  /** Starts a headless browser with its profile in `scratch`, a folder the caller made for it alone and stopNow() removes, and waits until the debug port answers. */
  static async start(scratch, command, onRequest = () => {}) {
    const port = await freePort();
    const profile = path.join(scratch, "profile");
    fs.mkdirSync(profile, { recursive: true });
    const child = spawn(command, [...ARGUMENTS, `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, "about:blank"], {
      stdio: "ignore",
      detached: process.platform !== "win32",
      windowsHide: true,
    });
    const browser = new Browser(child, port, scratch, onRequest);
    let refused = null;
    child.on("error", (error) => (refused = error));
    const deadline = Date.now() + BOOT_MS;
    while (Date.now() < deadline && child.exitCode === null && !refused) {
      try {
        const list = await fetch(`http://127.0.0.1:${port}/json/list`, { signal: AbortSignal.timeout(CALL_MS) }).then((response) => response.json());
        if (Array.isArray(list)) return browser;
      } catch {
        await sleep(POLL_MS);
      }
    }
    browser.stopNow();
    if (refused) throw new Error(`the browser ${command} did not start: ${refused.message}`);
    throw new Error(`the browser ${command} did not answer on debug port ${port} within ${BOOT_MS / 1000}s`);
  }

  /** The window handle Windows reports for the browser process; 0 means it has no window. */
  windowHandle() {
    const asked = spawnSync("pwsh", ["-NoProfile", "-Command", `(Get-Process -Id ${this.pid}).MainWindowHandle`], { encoding: "utf8", windowsHide: true });
    if (asked.status !== 0) throw new Error(`the window check failed: ${asked.stderr || asked.error}`);
    return asked.stdout.trim();
  }

  /** Opens a tab at `width` pixels, loads `url` with `cookies` (a name and a value each) and waits for the load event and the fonts. */
  async open(url, { width, height, cookies = [] }) {
    const target = await fetch(`http://127.0.0.1:${this.#port}/json/new?url=about:blank`, { method: "PUT", signal: AbortSignal.timeout(CALL_MS) }).then((response) => response.json());
    const tab = new Tab(target, this.#onRequest);
    await tab.connect();
    await Promise.all(["Runtime", "Page", "Log", "Network"].map((domain) => tab.send(`${domain}.enable`)));
    await tab.send("Emulation.setDeviceMetricsOverride", { width, height, deviceScaleFactor: 1, mobile: false });
    for (const cookie of [ORIGIN_COOKIE, ...cookies]) await tab.send("Network.setCookie", { ...cookie, url: new URL(url).origin });
    await tab.navigate(url);
    return tab;
  }

  /** Kills the browser's whole process tree by pid and removes its profile. */
  stopNow() {
    if (this.#stopped) return;
    this.#stopped = true;
    process.off("exit", this.#onExit);
    if (this.pid !== undefined) killTree(this.pid);
    try {
      fs.rmSync(this.#scratch, { recursive: true, force: true, maxRetries: 5, retryDelay: 100 });
    } catch (error) {
      console.error(`the browser profile ${this.#scratch} could not be removed: ${error.message}`);
    }
  }

  async stop() {
    this.stopNow();
  }
}

class Tab {
  #target;
  #socket;
  #lastId = 0;
  #waiting = new Map();
  #loaded;
  #requests = new Map();
  #onRequest;
  problems = [];

  constructor(target, onRequest) {
    this.#target = target;
    this.#onRequest = onRequest;
  }

  async connect() {
    this.#socket = new WebSocket(this.#target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
      this.#socket.addEventListener("open", resolve);
      this.#socket.addEventListener("error", () => reject(new Error("the debug socket refused the tab")));
    });
    this.#socket.addEventListener("message", (event) => this.#receive(JSON.parse(event.data)));
  }

  #receive(message) {
    if (message.id !== undefined) {
      this.#waiting.get(message.id)?.(message);
      this.#waiting.delete(message.id);
      return;
    }
    const { method, params } = message;
    if (method === "Runtime.exceptionThrown") {
      this.problems.push("page error: " + (params.exceptionDetails.exception?.description ?? params.exceptionDetails.text));
    } else if (method === "Runtime.consoleAPICalled" && params.type === "error") {
      const text = params.args.map((arg) => arg.value ?? arg.description ?? "").join(" ");
      if (!NOISE.test(text)) this.problems.push("console error: " + text);
    } else if (method === "Log.entryAdded" && params.entry.level === "error") {
      if (!NOISE.test(params.entry.text + (params.entry.url ?? ""))) this.problems.push("log error: " + params.entry.text + " " + (params.entry.url ?? ""));
    } else if (method === "Network.requestWillBeSent") {
      this.#requests.set(params.requestId, params.request.url);
      this.#onRequest(params.request.url);
    } else if (method === "Network.responseReceived" && params.response.status >= 400) {
      if (!NOISE.test(params.response.url)) this.problems.push(`request answered ${params.response.status}: ${params.response.url}`);
    } else if (method === "Network.loadingFailed" && !params.canceled) {
      const url = this.#requests.get(params.requestId) ?? "an unknown address";
      if (!NOISE.test(url)) this.problems.push(`request failed: ${params.errorText} ${url}`);
    } else if (method === "Page.loadEventFired") {
      this.#loaded?.();
    }
  }

  send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = ++this.#lastId;
      const timer = setTimeout(() => {
        this.#waiting.delete(id);
        reject(new Error(`${method}: the browser did not answer within ${CALL_MS / 1000}s`));
      }, CALL_MS);
      this.#waiting.set(id, (message) => {
        clearTimeout(timer);
        if (message.error) reject(new Error(`${method}: ${message.error.message}`));
        else resolve(message.result);
      });
      this.#socket.send(JSON.stringify({ id, method, params }));
    });
  }

  async navigate(url) {
    const loaded = new Promise((resolve) => {
      this.#loaded = resolve;
    });
    const started = await this.send("Page.navigate", { url });
    if (started.errorText) throw new Error(`${url} did not load: ${started.errorText}`);
    let timer;
    const late = new Promise((_, reject) => {
      timer = setTimeout(() => reject(new Error(`${url} did not fire the load event within ${LOAD_MS / 1000}s`)), LOAD_MS);
    });
    try {
      await Promise.race([loaded, late]);
    } finally {
      clearTimeout(timer);
    }
    await this.evaluate("document.fonts.ready.then(() => true)");
  }

  async evaluate(expression) {
    const result = await this.send("Runtime.evaluate", { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
    return result.result.value;
  }

  async until(expression, ms, what) {
    const deadline = Date.now() + ms;
    while (!(await this.evaluate(expression))) {
      if (Date.now() > deadline) throw new Error(`${what} within ${ms / 1000}s`);
      await sleep(POLL_MS);
    }
  }

  overflow(width) {
    return this.evaluate(`Math.max(0, document.documentElement.scrollWidth - ${width})`);
  }

  async screenshot() {
    const captured = await this.send("Page.captureScreenshot", { format: "png" });
    return Buffer.from(captured.data, "base64");
  }

  async close() {
    try {
      await this.send("Target.closeTarget", { targetId: this.#target.id });
    } catch {
      this.#socket.close();
    }
  }
}

function freePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.on("error", reject);
    server.listen(0, "127.0.0.1", () => {
      const { port } = server.address();
      server.close(() => resolve(port));
    });
  });
}
