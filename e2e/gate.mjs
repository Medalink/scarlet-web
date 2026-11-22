/**
 * A local HTTP gate in front of the server under test. `php artisan serve` runs a few PHP workers (bin/dev sets 4),
 * each holding one connection until it is answered, and sometimes resets a connection it has already accepted. A
 * browser opens up to six connections to one host at once, so the lane sends everything, its own requests and the
 * browser's, through this gate. The gate passes at most `limit` requests to the server at a time, lets the rest wait,
 * and repeats a GET or HEAD that the server reset before it answered, at most twice. `retried` counts those repeats.
 */

import http from "node:http";

const RETRIES = 2;
const SAFE = new Set(["GET", "HEAD"]);

export class Gate {
  #server;
  origin;
  retried = 0;

  constructor(server, origin) {
    this.#server = server;
    this.origin = origin;
  }

  /** Listens on a free 127.0.0.1 port and forwards to `target` (a URL), at most `limit` requests at a time. */
  static async start(target, limit) {
    const upstream = new URL(target);
    const agent = new http.Agent({ keepAlive: false, maxSockets: limit });
    let gate;
    const server = http.createServer((request, response) => {
      let forwarded;
      let attempt = 0;
      const send = () => {
        forwarded = http.request({
          host: upstream.hostname,
          port: upstream.port,
          path: request.url,
          method: request.method,
          headers: request.headers,
          agent,
        }, (answer) => {
          response.writeHead(answer.statusCode, answer.headers);
          answer.pipe(response);
        });
        forwarded.on("error", () => {
          if (response.destroyed || request.destroyed) return;
          if (SAFE.has(request.method) && attempt < RETRIES) {
            attempt += 1;
            gate.retried += 1;
            send();
            return;
          }
          response.destroy();
        });
        if (SAFE.has(request.method)) forwarded.end();
      };
      send();
      if (!SAFE.has(request.method)) request.pipe(forwarded);
      response.on("close", () => forwarded.destroy());
    });
    await new Promise((resolve, reject) => {
      server.once("error", reject);
      server.listen(0, "127.0.0.1", resolve);
    });
    gate = new Gate(server, `http://127.0.0.1:${server.address().port}`);
    return gate;
  }

  close() {
    this.#server.closeAllConnections();
    this.#server.close();
  }
}
