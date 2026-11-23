export const STEP = 2;

export const ms = (seconds) => Math.round(seconds * 1000);

/** A cursor on a session's clock that writes `[milliseconds since the session began, code, ...fields]` lines as it moves. */
export class Track {
  lines = [];
  creature = 990200;

  constructor(start) {
    this.t = start;
  }

  at(seconds, ...line) {
    this.t += ms(seconds);
    this.lines.push([this.t, ...line]);
  }

  wait(seconds) {
    this.t += ms(seconds);
  }

  npc() {
    this.creature += 1;
    return this.creature;
  }
}

export const cast = (k, spell, seconds = "") => k.at(STEP, "C", spell, seconds);
