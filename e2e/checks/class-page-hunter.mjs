import { HUNTER } from "../support.mjs";
import { AMMO_WORDS, ASPECT_WORDS, BAND, CALLOUTS, FEIGN, FEIGN_WORDS, HEAD, PARTY, PETS, PET_WORDS, QUIVER, SHOT_NOTE, SHOT_RANK, TALENTS, TRACKING, TRAP_RANK } from "../hunter-expected.mjs";
import { bandLinksItsClassPage, pageShowsItsFigures } from "./class-page-kit.mjs";

export const hunterPageShowsItsFigures = (ctx) => pageShowsItsFigures(ctx, {
  route: `/c/${HUNTER}/hunter`,
  name: "Hunter",
  groups: { head: HEAD, quiver: QUIVER, pets: PETS, shots: SHOT_RANK, traps: TRAP_RANK, "Feign Death": FEIGN, tracking: TRACKING, party: PARTY },
  sequences: { callouts: CALLOUTS, talents: TALENTS },
  words: [...AMMO_WORDS, ...PET_WORDS, ...ASPECT_WORDS, ...FEIGN_WORDS, SHOT_NOTE],
});

export const hunterBandLinksItsClassPage = (ctx) => bandLinksItsClassPage(ctx, { character: HUNTER, slug: "hunter", name: "Hunter", band: BAND });
