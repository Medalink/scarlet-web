import { WARRIOR } from "../support.mjs";
import { ATTACKS_USED, BAND, CALLOUTS, DAMAGE_BY_SPELL, EXECUTE, HEAD, OPENS, PARTY, SHOUT_RANK, SHOUT_UP, STANCE, STANCE_SHARES, TALENTS } from "../warrior-expected.mjs";
import { bandLinksItsClassPage, pageShowsItsFigures } from "./class-page-kit.mjs";

export const warriorPageShowsItsFigures = (ctx) => pageShowsItsFigures(ctx, {
  route: `/c/${WARRIOR}/warrior`,
  name: "Warrior",
  groups: { head: HEAD, Execute: EXECUTE, attacks: ATTACKS_USED, stance: STANCE, opens: OPENS, shouts: SHOUT_RANK, "damage by spell": DAMAGE_BY_SPELL, party: PARTY },
  sequences: { callouts: CALLOUTS, talents: TALENTS },
  words: [...STANCE_SHARES, SHOUT_UP],
});

export const warriorBandLinksItsClassPage = (ctx) => bandLinksItsClassPage(ctx, { character: WARRIOR, slug: "warrior", name: "Warrior", band: BAND });
