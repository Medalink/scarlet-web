import { PALADIN } from "../support.mjs";
import { AURA_WORDS, BAND, BLESSING_NOTE, BLESSING_RANK, BUBBLE, BUBBLE_WORDS, CALLOUTS, CONTROL, HEAD, HEALS, HEALS_NOTE, JUDGEMENT, LAY_ON_HANDS, PARTY, SEAL_WORDS, TALENTS, UNDEAD } from "../paladin-expected.mjs";
import { bandLinksItsClassPage, pageShowsItsFigures } from "./class-page-kit.mjs";

export const paladinPageShowsItsFigures = (ctx) => pageShowsItsFigures(ctx, {
  route: `/c/${PALADIN}/paladin`,
  name: "Paladin",
  groups: { head: HEAD, Judgement: JUDGEMENT, blessings: BLESSING_RANK, undead: UNDEAD, bubble: BUBBLE, "Lay on Hands": LAY_ON_HANDS, heals: HEALS, control: CONTROL, party: PARTY },
  sequences: { callouts: CALLOUTS, talents: TALENTS },
  words: [...SEAL_WORDS, ...AURA_WORDS, ...BUBBLE_WORDS, BLESSING_NOTE, HEALS_NOTE],
});

export const paladinBandLinksItsClassPage = (ctx) => bandLinksItsClassPage(ctx, { character: PALADIN, slug: "paladin", name: "Paladin", band: BAND });
