// Starts the real app for the e2e lane on a Linux PC, runs a command against it and stops it again.
//
//   node e2e/boot.mjs [-- <command> [args...]]      default command: node e2e/run.mjs
//
// Each run gets a database of its own, its own Redis prefix and a free port, so runs in two checkouts never share
// state. Runs in one checkout take turns, because they share vendor/, node_modules/ and public/build. The command
// sees the app's own settings (its DB_, REDIS_ and APP_ values), its address in E2E_URL and E2E_STRICT=1, and this
// exits with the command's exit code. When the command
// fails, the tail of the app's own logs is printed.
//
// A run that is interrupted stops the app and drops its database before it exits. A run that was killed outright
// leaves both behind, and the next run removes them: it finds every earlier run whose boot process is gone and stops
// its PHP processes, drops its database, deletes its Redis keys and removes its temp folder.
//
// The app's settings come from this process alone: every DB_, REDIS_, CACHE_, SESSION_, QUEUE_ and HORIZON_ value of
// the caller is dropped, and the ones that name a server are set here, so a .env in the checkout cannot name another.
//
// PostgreSQL and Redis are two standing containers made from the images in docker/pins.tsv, scarlet-web-e2e-pg on
// 127.0.0.1:5448 and scarlet-web-e2e-redis on 127.0.0.1:6398 (E2E_PG and E2E_REDIS name other addresses, which must
// serve this PC alone). When one does not answer, this starts it with docker. It needs php, composer, pnpm, cargo,
// psql and flock on the PATH.
// On Windows the app runs under bin/dev instead and the lane takes --url (DEV.md, Checks before a ship).
import { spawn } from "node:child_process";
import crypto from "node:crypto";
import fs from "node:fs";
import net from "node:net";
import os from "node:os";
import path from "node:path";

const repo = path.resolve(import.meta.dirname, "..");
const address = (text) => ({ host: text.split(":")[0], port: Number(text.split(":")[1]) });
const postgres = address(process.env.E2E_PG ?? "127.0.0.1:5448");
const redis = address(process.env.E2E_REDIS ?? "127.0.0.1:6398");
const run = crypto.randomBytes(4).toString("hex");
const databaseOf = (id) => `scarlet_e2e_${id}`;
const scratchOf = (id) => path.join(os.tmpdir(), `scarlet-web-e2e-${id}`);
const database = databaseOf(run);
const prefix = `${database}_`;
const scratch = scratchOf(run);
const turnFile = path.join(os.tmpdir(), `scarlet-web-e2e-turn-${crypto.createHash("sha256").update(repo).digest("hex").slice(0, 12)}`);
const partDirectory = path.join(repo, "part");
const partBinary = path.join(process.env.CARGO_TARGET_DIR ? path.resolve(partDirectory, process.env.CARGO_TARGET_DIR) : path.join(partDirectory, "target"), "release", "scarlet-part");
const mapArt = path.join(repo, "storage", "app", "maps", "manifest.json");
const REDIS_DATABASES = 16;
const READY_MS = 30_000;
const OUTPUT_KEPT = 64 * 1024;
const EXIT_OF = { SIGINT: 130, SIGTERM: 143 };
const OWN_SETTINGS = /^(DB|REDIS|CACHE|SESSION|QUEUE|HORIZON)_/;
const LANE_MS = 600_000;
const EOL = "\n";

const SERVERS = [
  { name: "scarlet-web-e2e-pg", pin: "postgres", at: postgres, inside: 5432, options: ["--shm-size", "256M", "-e", "POSTGRES_HOST_AUTH_METHOD=trust", "-e", "TIMESCALEDB_TELEMETRY=off"], command: ["postgres", "-c", "fsync=off", "-c", "synchronous_commit=off", "-c", "full_page_writes=off"] },
  { name: "scarlet-web-e2e-redis", pin: "redis", at: redis, inside: 6379, options: [], command: ["redis-server", "--save", "", "--appendonly", "no"] },
];

class Failed extends Error {}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
let current = null;
let interrupted = null;
let cleaning = false;

function finished(file, args, { env = process.env, cwd = repo, shown = false } = {}) {
  return new Promise((resolve, reject) => {
    if (interrupted && !cleaning) return reject(new Failed(`stopped by ${interrupted}`));
    const child = spawn(file, args, { cwd, env, stdio: shown ? "inherit" : ["ignore", "pipe", "pipe"] });
    current = child;
    const kept = { stdout: "", stderr: "" };
    for (const stream of ["stdout", "stderr"]) child[stream]?.on("data", (chunk) => (kept[stream] = (kept[stream] + chunk).slice(-OUTPUT_KEPT)));
    child.once("error", (error) => {
      current = null;
      reject(new Failed(`${file} did not start: ${error.message}`));
    });
    child.once("close", (status, signal) => {
      current = null;
      resolve({ status, signal, ...kept });
    });
  });
}

async function command(file, args, options) {
  const done = await finished(file, args, options);
  if (interrupted && !cleaning) throw new Failed(`stopped by ${interrupted}`);
  if (done.status !== 0) throw new Failed(`${file} ${args.join(" ")} exited ${done.status ?? done.signal}\n${(done.stdout + done.stderr).trim().split("\n").slice(-15).join("\n")}`);
  return done.stdout;
}

const succeeds = (file, args) => finished(file, args).then((done) => done.status === 0, () => false);

const answers = ({ host, port }) => new Promise((resolve) => {
  const socket = net.connect(port, host);
  socket.once("connect", () => { socket.destroy(); resolve(true); });
  socket.once("error", () => resolve(false));
});

async function until(what, ready, ms = READY_MS) {
  const end = Date.now() + ms;
  while (!(await ready())) {
    if (interrupted) throw new Failed(`stopped by ${interrupted}`);
    if (Date.now() > end) throw new Failed(`${what} did not answer within ${ms / 1000} s`);
    await sleep(100);
  }
}

const psqlArguments = (sql, target = "postgres") => ["-h", postgres.host, "-p", String(postgres.port), "-U", "postgres", "-v", "ON_ERROR_STOP=1", "-Atqc", sql, target];
const psql = (sql, target) => command("psql", psqlArguments(sql, target));

function freePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once("error", reject);
    server.listen(0, "127.0.0.1", () => { const { port } = server.address(); server.close(() => resolve(port)); });
  });
}

function forgetRedisKeys(keys) {
  const script = "local n = 0 for _, k in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', k) n = n + 1 end return n";
  const bulk = (words) => `*${words.length}\r\n` + words.map((w) => `$${Buffer.byteLength(w)}\r\n${w}\r\n`).join("");
  return new Promise((resolve) => {
    const socket = net.connect(redis.port, redis.host);
    let replies = "";
    socket.on("data", (chunk) => { replies += chunk; if (replies.split("\r\n").length > REDIS_DATABASES * 2) socket.end(); });
    socket.once("error", () => resolve());
    socket.once("close", () => resolve());
    socket.once("connect", () => {
      for (let db = 0; db < REDIS_DATABASES; db++) socket.write(bulk(["SELECT", String(db)]) + bulk(["EVAL", script, "0", keys + "*"]));
    });
    setTimeout(() => socket.destroy(), 5000).unref();
  });
}

function takeTurn() {
  return new Promise((resolve, reject) => {
    const holder = spawn("flock", [turnFile, "sh", "-c", "echo held; cat >/dev/null"], { stdio: ["pipe", "pipe", "inherit"] });
    current = holder;
    const waiting = setTimeout(() => console.log("boot: waiting for another run in this checkout to finish"), 1000);
    let settled = false;
    const settle = (work) => {
      if (settled) return;
      settled = true;
      clearTimeout(waiting);
      current = null;
      work();
    };
    holder.once("error", (error) => settle(() => reject(new Failed(`flock did not start: ${error.message}`))));
    holder.once("close", () => settle(() => reject(new Failed(interrupted ? `stopped by ${interrupted}` : "the turn in this checkout was lost"))));
    holder.stdout.once("data", () => settle(() => resolve(holder)));
  });
}

function bootRuns(pid) {
  try {
    return fs.readFileSync(`/proc/${pid}/cmdline`, "utf8").includes("boot.mjs");
  } catch (error) {
    return error.code === "EACCES" || error.code === "EPERM";
  }
}

function owned(id) {
  try {
    return bootRuns(Number(fs.readFileSync(path.join(scratchOf(id), "owner"), "utf8")));
  } catch {
    return false;
  }
}

function makeScratch() {
  const making = `${scratch}.new`;
  fs.mkdirSync(path.join(making, "views"), { recursive: true });
  fs.writeFileSync(path.join(making, "owner"), String(process.pid));
  fs.renameSync(making, scratch);
}

function killRun(id) {
  const mark = `DB_DATABASE=${databaseOf(id)}`;
  for (const entry of fs.readdirSync("/proc")) {
    if (!/^\d+$/.test(entry)) continue;
    try {
      if (fs.readFileSync(`/proc/${entry}/environ`, "utf8").includes(mark)) process.kill(Number(entry), "SIGKILL");
    } catch {}
  }
}

async function sweep() {
  const left = new Set();
  for (const entry of fs.readdirSync(os.tmpdir())) {
    const id = /^scarlet-web-e2e-([0-9a-f]{8})$/.exec(entry)?.[1];
    if (id && !owned(id)) left.add(id);
  }
  const listed = await psql("select datname from pg_database where datname ~ '^scarlet_e2e_[0-9a-f]{8}$'");
  for (const name of listed.split("\n").filter(Boolean)) if (!owned(name.slice(-8))) left.add(name.slice(-8));
  for (const id of left) {
    killRun(id);
    await psql(`drop database if exists ${databaseOf(id)} with (force)`);
    await forgetRedisKeys(`${databaseOf(id)}_`);
    try {
      fs.rmSync(scratchOf(id), { recursive: true, force: true });
    } catch {}
  }
  if (left.size) console.log(`boot: removed what ${left.size} killed run(s) left: ${[...left].join(" ")}`);
}

async function servers() {
  const down = [];
  for (const server of SERVERS) if (!(await answers(server.at))) down.push(server);
  if (!down.length) return;
  const docker = (await succeeds("docker", ["info"])) ? ["docker"] : ["sudo", "-n", "docker"];
  const pins = Object.fromEntries(fs.readFileSync(path.join(repo, "docker", "pins.tsv"), "utf8").trim().split("\n").slice(1).map((line) => line.split("\t")));
  for (const server of down) {
    const exists = await succeeds(docker[0], [...docker.slice(1), "inspect", server.name]);
    if (exists) await command(docker[0], [...docker.slice(1), "start", server.name]);
    else await command(docker[0], [...docker.slice(1), "run", "--detach", "--name", server.name, "--restart", "unless-stopped", "--publish", `${server.at.host}:${server.at.port}:${server.inside}`, ...server.options, pins[server.pin], ...server.command]);
  }
  await until("PostgreSQL", () => succeeds("psql", psqlArguments("select 1")), 60_000);
  await until("Redis", () => answers(redis));
}

function newerThan(file, roots) {
  if (!fs.existsSync(file)) return true;
  const built = fs.statSync(file).mtimeMs;
  const stale = (entry) => (fs.statSync(entry).isDirectory() ? fs.readdirSync(entry).some((name) => name !== "target" && stale(path.join(entry, name))) : fs.statSync(entry).mtimeMs > built);
  return roots.some((root) => fs.existsSync(root) && stale(root));
}

async function dependencies() {
  await command("composer", ["install", "--no-interaction", "--no-progress", "--no-ansi"]);
  await command("pnpm", ["install", "--frozen-lockfile"]);
  await command("pnpm", ["run", "build"]);
  if (!newerThan(partBinary, [path.join(repo, "part"), path.join(repo, "docs")])) return;
  const slot = (await succeeds("sh", ["-c", "command -v fleet"])) ? ["fleet", "slot", "--"] : [];
  const cargo = [...slot, "cargo", "build", "--release"];
  await command(cargo[0], cargo.slice(1), { cwd: path.join(repo, "part") });
}

function appEnvironment(url) {
  const lifted = "100000000";
  const inherited = Object.fromEntries(Object.entries(process.env).filter(([name]) => !OWN_SETTINGS.test(name)));
  return {
    ...inherited,
    APP_ENV: "local", APP_DEBUG: "true", APP_URL: url, APP_KEY: "base64:" + crypto.randomBytes(32).toString("base64"),
    LOG_CHANNEL: "single", LOG_LEVEL: "debug",
    DB_URL: "", DB_SSLMODE: "prefer", REDIS_URL: "", REDIS_CACHE_URL: "", REDIS_USERNAME: "", REDIS_CLUSTER: "redis",
    REDIS_CACHE_HOST: redis.host, REDIS_CACHE_PORT: String(redis.port), REDIS_CACHE_PASSWORD: "null",
    REDIS_DB: "0", REDIS_CACHE_DB: "1", REDIS_SESSION_DB: "2",
    REDIS_CACHE_CONNECTION: "cache", REDIS_CACHE_LOCK_CONNECTION: "default", REDIS_QUEUE_CONNECTION: "default", SESSION_CONNECTION: "session",
    DB_CONNECTION: "pgsql", DB_HOST: postgres.host, DB_PORT: String(postgres.port), DB_DATABASE: database, DB_USERNAME: "postgres", DB_PASSWORD: "",
    REDIS_CLIENT: "phpredis", REDIS_HOST: redis.host, REDIS_PORT: String(redis.port), REDIS_PASSWORD: "null",
    REDIS_PREFIX: prefix, CACHE_PREFIX: prefix + "cache_", HORIZON_PREFIX: prefix + "horizon:", SESSION_COOKIE: prefix + "session",
    SESSION_DRIVER: "redis", CACHE_STORE: "redis", QUEUE_CONNECTION: "redis",
    SCARLET_DEV_LOGIN: "true", SCARLET_ADMINS: "development:development-1", SCARLET_MIN_CHARACTERS: process.env.E2E_MIN_CHARACTERS ?? "1", SCARLET_PART_BIN: partBinary, VIEW_COMPILED_PATH: path.join(scratch, "views"),
    SCARLET_RATE_REGISTRATION_PER_IP: lifted, SCARLET_RATE_UPLOAD_PER_INSTALL: lifted, SCARLET_RATE_UPLOAD_PER_IP: lifted,
    SCARLET_RATE_PUBLIC_ANONYMOUS: lifted, SCARLET_RATE_PUBLIC_TOKEN: lifted, SCARLET_RATE_PAGE_PER_ADDRESS: lifted,
    PHP_CLI_SERVER_WORKERS: "4",
  };
}

const started = [];

function start(name, args, env) {
  const log = path.join(scratch, name + ".log");
  const out = fs.openSync(log, "a");
  const child = spawn("php", args, { cwd: repo, env, detached: true, stdio: ["ignore", out, out] });
  child.once("error", () => {});
  started.push({ name, child, log });
  return child;
}

const ended = (child) => child.pid === undefined || child.exitCode !== null || child.signalCode !== null;

async function stopAll() {
  const signal = (name) => {
    for (const { child } of started) {
      try { process.kill(-child.pid, name); } catch {}
    }
  };
  signal("SIGTERM");
  const end = Date.now() + 5000;
  while (started.some(({ child }) => !ended(child)) && Date.now() < end) await sleep(50);
  signal("SIGKILL");
}

function tail(file, from = 0, lines = 40) {
  if (!fs.existsSync(file)) return "";
  return fs.readFileSync(file, "utf8").slice(from).trim().split("\n").slice(-lines).join("\n");
}

async function main() {
  if (process.platform !== "linux") throw new Failed("e2e/boot.mjs runs on Linux; on Windows start the app with bin/dev and run: node e2e/run.mjs --url http://127.0.0.1:<port>");
  const split = process.argv.indexOf("--");
  const lane = split > 0 ? process.argv.slice(split + 1) : ["node", path.join("e2e", "run.mjs")];
  if (!lane.length) throw new Failed("usage: node e2e/boot.mjs [-- <command> [args...]]");
  const laravelLog = path.join(repo, "storage", "logs", "laravel.log");
  const began = performance.now();
  const step = async (name, work) => {
    const from = performance.now();
    const value = await work();
    console.log(`boot ${String(Math.round(performance.now() - from)).padStart(6)}ms  ${name}`);
    return value;
  };
  let code = 1;
  let logStart = 0;
  let turn = null;
  try {
    turn = await takeTurn();
    makeScratch();
    logStart = fs.existsSync(laravelLog) ? fs.statSync(laravelLog).size : 0;
    await step("PostgreSQL and Redis answer", async () => {
      await servers();
      await sweep();
    });
    await step("dependencies, assets and the part tool are built", dependencies);
    const url = `http://127.0.0.1:${await freePort()}`;
    const env = appEnvironment(url);
    await step(`database ${database} is migrated`, async () => {
      await psql(`create database ${database}`);
      await command("php", ["artisan", "migrate", "--force", "--no-interaction"], { env });
    });
    await step(`the app answers at ${url}`, async () => {
      const serve = start("serve", ["artisan", "serve", "--host=127.0.0.1", `--port=${new URL(url).port}`, "--no-reload"], env);
      start("horizon", ["artisan", "horizon"], env);
      await until("the app", async () => {
        if (ended(serve)) throw new Failed(`php artisan serve ended before it answered at ${url}`);
        return (await fetch(url + "/up", { signal: AbortSignal.timeout(2000) }).then((r) => r.status, () => 0)) === 200;
      });
    });
    const from = performance.now();
    const late = setTimeout(() => {
      console.error(`boot: ${lane.join(" ")} was still running after ${LANE_MS / 1000} s and is stopped`);
      current?.kill("SIGKILL");
    }, LANE_MS);
    const done = await finished(lane[0], lane.slice(1), { env: { ...env, E2E_URL: url, E2E_STRICT: "1", E2E_MAP_ART: fs.existsSync(mapArt) ? "1" : "0" }, shown: true });
    clearTimeout(late);
    code = done.status ?? 1;
    console.log(`boot ${String(Math.round(performance.now() - from)).padStart(6)}ms  ${lane.join(" ")} exited ${done.status ?? done.signal}`);
  } catch (error) {
    if (!(error instanceof Failed)) throw error;
    console.error(`boot failed: ${error.message}`);
  } finally {
    cleaning = true;
    const tidy = async (what, work) => {
      try {
        await work();
      } catch (error) {
        console.error(`boot: ${what} failed: ${error.message}`);
      }
    };
    await tidy("stopping the app", stopAll);
    if (code !== 0 || interrupted) {
      await tidy("printing the app's logs", () => {
        for (const [name, text] of [["storage/logs/laravel.log", tail(laravelLog, logStart)], ...started.map(({ name, log }) => [name, tail(log)])]) {
          if (text) console.error(["--- " + name, text].join(EOL));
        }
      });
    }
    if (turn) {
      await tidy(`dropping ${database}`, () => psql(`drop database if exists ${database} with (force)`));
      await tidy("deleting the Redis keys", () => forgetRedisKeys(prefix));
      await tidy(`removing ${scratch}`, () => fs.rmSync(scratch, { recursive: true, force: true }));
      turn.stdin.end();
    }
    console.log(`boot: ${((performance.now() - began) / 1000).toFixed(1)}s in all`);
  }
  return interrupted ? EXIT_OF[interrupted] : code;
}

for (const signal of Object.keys(EXIT_OF)) {
  process.on(signal, () => {
    interrupted ??= signal;
    if (!cleaning) current?.kill(signal);
  });
}
process.exit(await main());
