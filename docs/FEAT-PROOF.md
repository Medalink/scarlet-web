# Feat proof map

Every class, talent-tree and role feat, with the evidence that a player can earn it on client build 1.60.1.71037.
The tables below are written by `bin/artisan feats:proof <tables> --write`; the text above them is written by hand.
The 74 general feats were audited the same day against the client's tables and are described in
[DESIGN.md](DESIGN.md) ("Feats").

## What "can be earned" means

A feat is in reach when three things hold.

1. **The count reaches the site.** The addon writes a line for it, the decoder stores it and the counter reads it.
2. **The class has the spell.** For a cast feat, every class that can hold it can learn, by level 60, at least one
   spell the rule names, and no name in the rule is missing from the client.
3. **The legend fits the game.** A player who cast the rule's spells every time their cooldowns allowed would reach
   the legend in at most 300 hours. When every spell of the rule has a cooldown of ten minutes or more, the limit is
   100 hours, since nobody uses such a spell each time it is ready. A spell with no cooldown counts as one cast every
   1.5 seconds. This is a floor, not a forecast: real play is several times slower.

`feats:proof` checks 2 and 3 for all 184 feats and exits with an error when one fails.

## How each count reaches the site

| Count | The addon writes | Sharing group | The decoder stores | Evidence |
| --- | --- | --- | --- | --- |
| Casts | `C` for every spell the player casts, instant or not, named by `SN` | combat, names | `spell_casts` | The end-to-end run uploads two Sinister Strikes and one Eviscerate and reads cutthroat 2 and eviscerator 1 back from the public API. A real log holds 7,041 `C` lines, among them Bane of Agony, Life Tap and Summon Succubus |
| Damage, healing and damage taken, in total and in one fight | `DM` after a fight, from the client's damage meter | combat | `meter_totals` | Real logs: 32 of the 33 sessions with a fight that the addon recorded (versions 0.3.0 and 0.4.0) carry meter lines for damage, healing and damage taken |
| The spec a session played | `TG` at login with the points in each talent tab, `TY` for a tab's name | build, names | `talent_snapshots` | Real logs: 54 of 56 login reads carry the points per tab. Tab names seen: Affliction, Demonology, Destruction, Beast Mastery, Marksmanship, Survival, Elemental Combat, Enhancement, Restoration |
| Hours, kills, deaths, player kills, dungeon runs and bosses, raid bosses | The lines the general feats already count | core, combat | `sessions`, `kills`, `dungeon_runs` | The general feats count them today |

## Limits a player should know

- A player who does not share the combat group gets no cast or meter feat, and one who does not share the build
  group has no spec, so no tree hours and no role feat.
- A session's spec is the talent tab with the most points at login. A character with no talent point, or with two
  tabs tied, has none.
- Only the player's own casts count. A pet's casts do not.
- Spells are matched by their English name.
- Sessions imported from an older archive carry no casts and no meter lines.
- The single-fight legends (Onslaught, Barrage, Unbreakable at 250,000 and Miracle Worker at 150,000) are estimates
  of a long raid boss fight at level 60. No level 60 fight was measured for them.
- Twice Born is the one cast feat no evidence covers end to end. A Shaman learns Reincarnation at level 30, but no
  log read so far holds a Shaman who took one, so it is not shown that the client reports it as a cast.
- "Fastest possible" ignores what a spell needs besides its cooldown: a target under 20% health, a dodge, a dead
  player, a demon to sacrifice. Those feats take longer than the column says. An independent check put the slowest
  realistic case at about 420 hours of play.
- The tab name "Shadow Magic" for a Shadow priest is inferred from the client's skill line, as the Shaman tab arrives
  as "Elemental Combat". No priest log was read.

## What the audit of 2026-11-22 changed

- Zealot counted Crusader Strike, which the client has no player spell for. It counts Repentance (changed the same
  day in its own ship).
- Provoker had no spell for a Paladin, who has no taunt on this client. It counts Seal of Fury for them.
- Warden had no spell for a Shaman. It counts Frost Shock and Earthbind Totem, and more spells for the other classes.
- Silencer gained the Paladin's Rebuke and Repentance.
- Legends that sat behind a cooldown came down: Charger, Lawbringer, Cold Blooded, Tide Turner, Twice Born, Back
  from the Brink and Last Resort.
- Eleven spell names the client does not have left the rules.

## Running it again

Export the client's tables `SpellName`, `SpellMisc`, `SpellLevels`, `SpellCooldowns`, `SkillLine`,
`SkillLineAbility`, `SkillRaceClassInfo`, `Talent` and `TalentTab` as CSV from the local client file server
(`tools/scene-art/README.md`) into a folder under `storage/app/`, which git ignores, then run
`bin/artisan feats:proof storage/app/client-tables --write`. The tables are the game's and are never committed.

<!-- feats:proof start -->
184 feats, 122 cast rules, 139 class checks, 0 failed.

## Warrior

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Battle Cry | Warrior | casts of Battle Shout | Battle Shout (level 1) | 25,000 | 10.4 | in reach |
| Charger | Warrior | casts of Charge, Intercept | Charge (level 4, 15s cooldown), Intercept (level 30, 30s cooldown) | 25,000 | 69.4 | in reach |
| Executioner | Warrior | casts of Execute | Execute (level 1) | 25,000 | 10.4 | in reach |
| Stance Dancer | Warrior | casts of Battle Stance, Defensive Stance, Berserker Stance | Battle Stance (level 1, 1s cooldown), Berserker Stance (level 30, 1s cooldown), Defensive Stance (level 10, 1s cooldown) | 25,000 | 3.5 | in reach |
| Mortal Striker | Warrior, Arms | casts of Mortal Strike | Mortal Strike (level 40, 6s cooldown) | 100,000 | 166.7 | in reach |
| Overpowering | Warrior, Arms | casts of Overpower | Overpower (level 12, 5s cooldown) | 25,000 | 34.7 | in reach |
| Bloodletter | Warrior, Arms | casts of Rend | Rend (level 4) | 100,000 | 41.7 | in reach |
| Weapon Master | Warrior, Arms | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Bloodthirsty | Warrior, Fury | casts of Bloodthirst | Bloodthirst (level 40, 6s cooldown) | 100,000 | 166.7 | in reach |
| Whirling | Warrior, Fury | casts of Whirlwind | Whirlwind (level 36, 10s cooldown) | 100,000 | 277.8 | in reach |
| Seeing Red | Warrior, Fury | casts of Death Wish, Recklessness, Berserker Rage | Berserker Rage (level 30, 30s cooldown), Death Wish (level 1, 180s cooldown), Recklessness (level 50, 1800s cooldown) | 6,000 | 42.3 | in reach |
| Berserker | Warrior, Fury | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Sunderer | Warrior, Protection | casts of Sunder Armor, Devastate | Devastate, Sunder Armor (level 10) | 200,000 | 41.7 | in reach |
| Vengeful | Warrior, Protection | casts of Revenge | Revenge (level 14, 5s cooldown) | 100,000 | 138.9 | in reach |
| Blockade | Warrior, Protection | casts of Shield Block, Shield Wall, Shield Slam | Shield Block (level 16, 5s cooldown), Shield Slam (level 40, 6s cooldown), Shield Wall (level 28, 900s cooldown) | 100,000 | 75.5 | in reach |
| Shieldbearer | Warrior, Protection | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Paladin

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Judicator | Paladin | casts of Judgement* | Judgement (level 4, 10s cooldown), Judgement of Command (level 20), Judgement of Fury (level 25), Judgement of Justice (level 22), Judgement of Light (level 30), Judgement of Martyrdom, Judgement of Righteousness (level 1), Judgement of Wisdom (level 38), Judgement of the Crusader (level 12) | 100,000 | 5.1 | in reach |
| Benediction | Paladin | casts of Blessing of *, Greater Blessing of * | Blessing of Freedom (level 18, 20s cooldown), Blessing of Kings (level 20), Blessing of Might (level 4), Blessing of Protection (level 10, 300s cooldown), Blessing of Sacrifice (level 46), Blessing of Wisdom (level 14), Greater Blessing of Kings (level 60), Greater Blessing of Might (level 52), Greater Blessing of Wisdom (level 54) | 25,000 | 1.5 | in reach |
| Last Resort | Paladin | casts of Lay on Hands | Lay on Hands (level 10, 1200s cooldown) | 200 | 66.7 | in reach |
| Untouchable | Paladin | casts of Divine Shield, Divine Protection | Divine Protection (level 6, 300s cooldown), Divine Shield (level 34, 300s cooldown) | 1,500 | 62.5 | in reach |
| Radiant | Paladin, Holy | casts of Holy Light | Holy Light (level 1) | 100,000 | 41.7 | in reach |
| Quickening | Paladin, Holy | casts of Flash of Light | Flash of Light | 100,000 | 41.7 | in reach |
| Purifier | Paladin, Holy | casts of Cleanse, Purify | Cleanse (level 42), Purify (level 8) | 25,000 | 5.2 | in reach |
| Lightbringer | Paladin, Holy | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Bastion | Paladin, Protection | casts of Holy Shield | Holy Shield (level 30, 10s cooldown) | 25,000 | 69.4 | in reach |
| Lawbringer | Paladin, Protection | casts of Hammer of Justice | Hammer of Justice (level 8, 60s cooldown) | 6,000 | 100.0 | in reach |
| Consecrator | Paladin, Protection | casts of Consecration | Consecration (level 20, 8s cooldown) | 100,000 | 222.2 | in reach |
| Templar | Paladin, Protection | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Sealbearer | Paladin, Retribution | casts of Seal of * | Seal of Command (level 20), Seal of Fury (level 10), Seal of Justice (level 22), Seal of Light (level 30), Seal of Martyrdom, Seal of Righteousness (level 1), Seal of Wisdom (level 38), Seal of the Crusader (level 6) | 100,000 | 5.2 | in reach |
| Hammerfall | Paladin, Retribution | casts of Hammer of Wrath | Hammer of Wrath (level 44, 6s cooldown) | 25,000 | 41.7 | in reach |
| Zealot | Paladin, Retribution | casts of Repentance | Repentance (level 20, 60s cooldown) | 6,000 | 100.0 | in reach |
| Crusader | Paladin, Retribution | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Hunter

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Playing Dead | Hunter | casts of Feign Death | Feign Death (level 30, 30s cooldown) | 6,000 | 50.0 | in reach |
| Trapper | Hunter | casts of * Trap | Explosive Trap (level 34), Freezing Trap (level 20, 30s cooldown), Frost Trap (level 28, 30s cooldown), Immolation Trap (level 16) | 25,000 | 5.0 | in reach |
| Marked Prey | Hunter | casts of Hunter's Mark | Hunter's Mark (level 6) | 100,000 | 41.7 | in reach |
| Beast Tamer | Hunter | casts of Tame Beast | Tame Beast | 400 | 0.2 | in reach |
| Unleashed | Hunter, Beast Mastery | casts of Bestial Wrath | Bestial Wrath (level 40, 120s cooldown) | 1,500 | 50.0 | in reach |
| Intimidating | Hunter, Beast Mastery | casts of Intimidation | Intimidation (level 30, 60s cooldown) | 6,000 | 100.0 | in reach |
| Pet Mender | Hunter, Beast Mastery | casts of Mend Pet, Revive Pet, Feed Pet | Feed Pet, Mend Pet (level 12), Revive Pet (level 10) | 25,000 | 3.5 | in reach |
| Pack Leader | Hunter, Beast Mastery | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Deadeye | Hunter, Marksmanship | casts of Aimed Shot | Aimed Shot (level 20, 6s cooldown) | 100,000 | 166.7 | in reach |
| Spreadshot | Hunter, Marksmanship | casts of Multi-Shot | Multi-Shot (level 18, 6s cooldown) | 100,000 | 166.7 | in reach |
| Arcane Archer | Hunter, Marksmanship | casts of Arcane Shot | Arcane Shot (level 6, 6s cooldown) | 100,000 | 166.7 | in reach |
| Sharpshooter | Hunter, Marksmanship | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Close Quarters | Hunter, Survival | casts of Raptor Strike, Mongoose Bite | Mongoose Bite (level 16, 5s cooldown), Raptor Strike | 100,000 | 32.1 | in reach |
| Wing Clipper | Hunter, Survival | casts of Wing Clip | Wing Clip (level 12) | 25,000 | 10.4 | in reach |
| Escapist | Hunter, Survival | casts of Disengage, Deterrence, Counterattack | Counterattack (level 30, 5s cooldown), Deterrence (level 20, 300s cooldown), Disengage (level 20, 5s cooldown) | 6,000 | 4.1 | in reach |
| Survivalist | Hunter, Survival | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Rogue

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Unseen | Rogue | casts of Stealth | Stealth (level 1, 10s cooldown) | 25,000 | 69.4 | in reach |
| Pickpocket | Rogue | casts of Pick Pocket | Pick Pocket (level 4) | 25,000 | 10.4 | in reach |
| Locksmith | Rogue | casts of Pick Lock | Pick Lock | 6,000 | 2.5 | in reach |
| Vanishing Act | Rogue | casts of Vanish | Vanish (level 22, 300s cooldown) | 1,500 | 125.0 | in reach |
| Eviscerator | Rogue, Assassination | casts of Eviscerate | Eviscerate (level 1) | 100,000 | 41.7 | in reach |
| Bleeder | Rogue, Assassination | casts of Rupture, Garrote | Garrote (level 14), Rupture (level 20) | 25,000 | 5.2 | in reach |
| Cold Blooded | Rogue, Assassination | casts of Cold Blood | Cold Blood (180s cooldown) | 1,500 | 75.0 | in reach |
| Assassin | Rogue, Assassination | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Cutthroat | Rogue, Combat | casts of Sinister Strike | Sinister Strike (level 1) | 200,000 | 83.3 | in reach |
| Gouger | Rogue, Combat | casts of Gouge | Gouge (level 6, 10s cooldown) | 25,000 | 69.4 | in reach |
| Blade Dancer | Rogue, Combat | casts of Blade Flurry, Adrenaline Rush, Evasion, Sprint | Adrenaline Rush (300s cooldown), Blade Flurry (level 1, 120s cooldown), Evasion (level 8, 300s cooldown), Sprint (level 10, 300s cooldown) | 6,000 | 90.9 | in reach |
| Swashbuckler | Rogue, Combat | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| First Strike | Rogue, Subtlety | casts of Ambush, Cheap Shot | Ambush (level 18), Cheap Shot (level 26) | 100,000 | 20.8 | in reach |
| Sandman | Rogue, Subtlety | casts of Sap | Sap (level 10) | 25,000 | 10.4 | in reach |
| Bloodspiller | Rogue, Subtlety | casts of Backstab, Hemorrhage, Ghostly Strike | Backstab (level 4), Ghostly Strike (20s cooldown), Hemorrhage (level 30) | 100,000 | 20.1 | in reach |
| Shadowstalker | Rogue, Subtlety | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Priest

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Fortifier | Priest | casts of Power Word: Fortitude, Prayer of Fortitude | Power Word: Fortitude (level 1), Prayer of Fortitude (level 48) | 25,000 | 5.2 | in reach |
| Aegis | Priest | casts of Power Word: Shield | Power Word: Shield (level 6, 4s cooldown) | 100,000 | 111.1 | in reach |
| Puppeteer | Priest | casts of Mind Control | Mind Control (level 30) | 1,500 | 0.6 | in reach |
| Shrieker | Priest | casts of Psychic Scream | Psychic Scream (level 14, 30s cooldown) | 6,000 | 50.0 | in reach |
| Inner Flame | Priest, Discipline | casts of Inner Fire | Inner Fire (level 12) | 25,000 | 10.4 | in reach |
| Dispeller | Priest, Discipline | casts of Dispel Magic | Dispel Magic (level 18) | 25,000 | 10.4 | in reach |
| Infuser | Priest, Discipline | casts of Power Infusion, Inner Focus | Inner Focus (180s cooldown), Power Infusion (level 40, 180s cooldown) | 6,000 | 150.0 | in reach |
| Disciplinarian | Priest, Discipline | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Mender | Priest, Holy | casts of Lesser Heal, Heal, Greater Heal, Flash Heal | Flash Heal (level 20), Greater Heal (level 40), Heal (level 16), Lesser Heal (level 1) | 100,000 | 10.4 | in reach |
| Renewer | Priest, Holy | casts of Renew | Renew | 100,000 | 41.7 | in reach |
| Prayer Leader | Priest, Holy | casts of Prayer of Healing, Holy Nova | Holy Nova (level 20), Prayer of Healing (level 30) | 25,000 | 5.2 | in reach |
| High Priest | Priest, Holy | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Mind Flayer | Priest, Shadow | casts of Mind Flay | Mind Flay (level 20) | 100,000 | 41.7 | in reach |
| Pain Bringer | Priest, Shadow | casts of Shadow Word: Pain | Shadow Word: Pain (level 4) | 100,000 | 41.7 | in reach |
| Mind Blaster | Priest, Shadow | casts of Mind Blast | Mind Blast (level 10, 8s cooldown) | 100,000 | 222.2 | in reach |
| Voidspeaker | Priest, Shadow | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Shaman

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Totemic | Shaman | casts of * Totem | Decoy Totem (20s cooldown), Disease Cleansing Totem (level 38), Earthbind Totem (level 6, 15s cooldown), Fire Resistance Totem (level 28), Flametongue Totem (level 28), Frost Resistance Totem (level 24), Grace of Air Totem (level 42), Grounding Totem (level 30, 15s cooldown), Healing Stream Totem (level 20), Magma Totem (level 26), Mana Spring Totem (level 26), Mana Tide Totem (level 1, 300s cooldown), Nature Resistance Totem (level 30), Poison Cleansing Totem (level 22), Searing Totem (level 10), Sentry Totem (level 34), Stoneclaw Totem (level 8, 30s cooldown), Stoneskin Totem (level 4), Strength of Earth Totem (level 10), Tremor Totem (level 18), Windfury Totem (level 32), Windwall Totem (level 36) | 100,000 | 2.4 | in reach |
| Pack Runner | Shaman | casts of Ghost Wolf | Ghost Wolf (level 20) | 25,000 | 10.4 | in reach |
| Shock Therapy | Shaman | casts of Earth Shock, Flame Shock, Frost Shock | Earth Shock (level 4, 6s cooldown), Flame Shock (level 10, 6s cooldown), Frost Shock (level 20, 6s cooldown) | 100,000 | 55.6 | in reach |
| Twice Born | Shaman | casts of Reincarnation | Reincarnation (level 30, 3600s cooldown) | 100 | 100.0 | in reach |
| Bolt Thrower | Shaman, Elemental | casts of Lightning Bolt | Lightning Bolt (level 1) | 200,000 | 83.3 | in reach |
| Chain Reaction | Shaman, Elemental | casts of Chain Lightning | Chain Lightning (level 32) | 25,000 | 10.4 | in reach |
| Purger | Shaman, Elemental | casts of Purge | Purge (level 12) | 25,000 | 10.4 | in reach |
| Stormcaller | Shaman, Elemental | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Stormstriker | Shaman, Enhancement | casts of Stormstrike | Stormstrike (level 40, 8s cooldown) | 100,000 | 222.2 | in reach |
| Weapon Imbuer | Shaman, Enhancement | casts of * Weapon | Flametongue Weapon, Frostbrand Weapon, Rockbiter Weapon, Windfury Weapon (level 30) | 25,000 | 2.6 | in reach |
| Charged | Shaman, Enhancement | casts of Lightning Shield | Lightning Shield (level 8) | 25,000 | 10.4 | in reach |
| Spiritwalker | Shaman, Enhancement | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Wave Rider | Shaman, Restoration | casts of Healing Wave, Lesser Healing Wave | Healing Wave (level 1), Lesser Healing Wave (level 20) | 100,000 | 20.8 | in reach |
| Chain Healer | Shaman, Restoration | casts of Chain Heal | Chain Heal (level 40) | 100,000 | 41.7 | in reach |
| Tide Turner | Shaman, Restoration | casts of Mana Tide Totem, Nature's Swiftness | Mana Tide Totem (level 1, 300s cooldown), Nature's Swiftness (level 1, 180s cooldown) | 1,500 | 46.9 | in reach |
| Tidecaller | Shaman, Restoration | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Mage

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Sheep Herder | Mage | casts of Polymorph* | Polymorph (level 8), Polymorph: Cow (level 60) | 25,000 | 5.2 | in reach |
| Portal Master | Mage | casts of Portal: * | Portal: Darnassus (level 50, 60s cooldown), Portal: Ironforge (level 40, 60s cooldown), Portal: Orgrimmar (level 40, 60s cooldown), Portal: Stormwind (level 40, 60s cooldown), Portal: Thunder Bluff (level 50, 60s cooldown), Portal: Undercity (level 40, 60s cooldown) | 1,500 | 4.2 | in reach |
| Quartermaster | Mage | casts of Conjure * | Conjure Food (level 6), Conjure Mana Agate (level 28), Conjure Mana Citrine (level 48), Conjure Mana Jade (level 38), Conjure Mana Ruby (level 58), Conjure Water | 25,000 | 1.7 | in reach |
| Blinker | Mage | casts of Blink | Blink (level 20, 15s cooldown) | 25,000 | 104.2 | in reach |
| Missile Barrage | Mage, Arcane | casts of Arcane Missiles | Arcane Missiles (level 8) | 100,000 | 41.7 | in reach |
| Detonator | Mage, Arcane | casts of Arcane Explosion | Arcane Explosion (level 14) | 100,000 | 41.7 | in reach |
| Channeler | Mage, Arcane | casts of Evocation, Arcane Power, Presence of Mind | Arcane Power (level 1, 180s cooldown), Evocation (level 20, 480s cooldown), Presence of Mind (level 1, 180s cooldown) | 6,000 | 126.3 | in reach |
| Arcanist | Mage, Arcane | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Firestarter | Mage, Fire | casts of Fireball | Fireball (level 1) | 200,000 | 83.3 | in reach |
| Pyromaniac | Mage, Fire | casts of Pyroblast | Pyroblast (level 20) | 100,000 | 41.7 | in reach |
| Scorcher | Mage, Fire | casts of Scorch, Fire Blast | Fire Blast (8s cooldown), Scorch (level 22) | 100,000 | 35.1 | in reach |
| Pyromancer | Mage, Fire | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Frostbinder | Mage, Frost | casts of Frostbolt | Frostbolt (level 4) | 200,000 | 83.3 | in reach |
| Shatterer | Mage, Frost | casts of Frost Nova, Cone of Cold | Cone of Cold (level 26, 10s cooldown), Frost Nova (level 10, 25s cooldown) | 100,000 | 198.4 | in reach |
| Iceborn | Mage, Frost | casts of Ice Block, Ice Barrier | Ice Barrier (level 40, 30s cooldown), Ice Block (level 4, 300s cooldown) | 6,000 | 45.5 | in reach |
| Cryomancer | Mage, Frost | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Warlock

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Soul Harvester | Warlock | casts of Drain Soul | Drain Soul (level 10) | 25,000 | 10.4 | in reach |
| Life Tapper | Warlock | casts of Life Tap | Life Tap (level 6) | 100,000 | 41.7 | in reach |
| Fearmonger | Warlock | casts of Fear, Howl of Terror, Death Coil | Death Coil (level 42, 120s cooldown), Fear (level 8), Howl of Terror (level 40, 40s cooldown) | 25,000 | 9.9 | in reach |
| Summoner | Warlock | casts of Ritual of Summoning | Ritual of Summoning (level 20) | 1,500 | 0.6 | in reach |
| Corruptor | Warlock, Affliction | casts of Corruption | Corruption (level 4) | 100,000 | 41.7 | in reach |
| Agonizer | Warlock, Affliction | casts of Bane of Agony | Bane of Agony (level 8) | 100,000 | 41.7 | in reach |
| Leech | Warlock, Affliction | casts of Drain Life, Siphon Life | Drain Life, Siphon Life (level 30) | 25,000 | 5.2 | in reach |
| Hexweaver | Warlock, Affliction | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Demon Caller | Warlock, Demonology | casts of Summon Imp, Summon Voidwalker, Summon Succubus, Summon Felhunter, Inferno, Ritual of Doom | Inferno (level 50, 3600s cooldown), Ritual of Doom (level 60, 3600s cooldown), Summon Felhunter (level 30), Summon Imp (level 1), Summon Succubus (level 20), Summon Voidwalker (level 10) | 6,000 | 0.6 | in reach |
| Funneler | Warlock, Demonology | casts of Health Funnel | Health Funnel (level 12) | 25,000 | 10.4 | in reach |
| Pactbinder | Warlock, Demonology | casts of Demonic Sacrifice, Fel Domination, Subjugate Demon, Soul Link | Demonic Sacrifice, Fel Domination (300s cooldown), Soul Link, Subjugate Demon (level 30) | 6,000 | 0.8 | in reach |
| Demonologist | Warlock, Demonology | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Bolt Slinger | Warlock, Destruction | casts of Shadow Bolt | Shadow Bolt (level 1) | 200,000 | 83.3 | in reach |
| Immolator | Warlock, Destruction | casts of Immolate | Immolate (level 1) | 100,000 | 41.7 | in reach |
| Searing | Warlock, Destruction | casts of Searing Pain | Searing Pain (level 18) | 100,000 | 41.7 | in reach |
| Destroyer | Warlock, Destruction | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Druid

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Shapeshifter | Druid | casts of Bear Form, Dire Bear Form, Cat Form, Travel Form, Aquatic Form, Moonkin Form | Aquatic Form (level 16), Bear Form (level 10), Cat Form (level 20), Dire Bear Form (level 40), Moonkin Form (level 40), Travel Form (level 30) | 100,000 | 6.9 | in reach |
| Wild Gift | Druid | casts of Mark of the Wild, Gift of the Wild | Gift of the Wild (level 50), Mark of the Wild (level 1) | 25,000 | 5.2 | in reach |
| Back From The Brink | Druid | casts of Rebirth | Rebirth (level 20, 1800s cooldown) | 200 | 100.0 | in reach |
| Entangler | Druid | casts of Entangling Roots, Hibernate | Entangling Roots (level 8), Hibernate (level 18) | 25,000 | 5.2 | in reach |
| Wrathbringer | Druid, Balance | casts of Wrath | Wrath (level 1) | 200,000 | 83.3 | in reach |
| Starcaller | Druid, Balance | casts of Starfire | Starfire (level 20) | 100,000 | 41.7 | in reach |
| Moonlit | Druid, Balance | casts of Moonfire | Moonfire (level 4) | 100,000 | 41.7 | in reach |
| Stargazer | Druid, Balance | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Mauler | Druid, Feral | casts of Maul, Swipe | Maul (level 10), Swipe | 100,000 | 20.8 | in reach |
| Shredder | Druid, Feral | casts of Claw, Shred, Rake | Claw (level 20), Rake (level 24), Shred (level 20) | 200,000 | 27.8 | in reach |
| Savage | Druid, Feral | casts of Ferocious Bite, Rip | Ferocious Bite (level 24), Rip (level 20) | 100,000 | 20.8 | in reach |
| Wildheart | Druid, Feral | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Greenthumb | Druid, Restoration | casts of Healing Touch, Regrowth | Healing Touch (level 1), Regrowth (level 12) | 100,000 | 20.8 | in reach |
| Rejuvenator | Druid, Restoration | casts of Rejuvenation | Rejuvenation (level 4) | 100,000 | 41.7 | in reach |
| Swiftmender | Druid, Restoration | casts of Swiftmend, Nature's Swiftness, Tranquility, Innervate | Innervate (level 40, 360s cooldown), Nature's Swiftness (level 1, 180s cooldown), Swiftmend (level 1, 15s cooldown), Tranquility (level 30, 300s cooldown) | 6,000 | 21.3 | in reach |
| Grovekeeper | Druid, Restoration | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |

## Healer

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Caretaker | Paladin | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Caretaker | Priest | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Caretaker | Shaman | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Caretaker | Druid | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Lifegiver | Paladin | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 150,000,000 | - | in reach |
| Lifegiver | Priest | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 150,000,000 | - | in reach |
| Lifegiver | Shaman | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 150,000,000 | - | in reach |
| Lifegiver | Druid | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 150,000,000 | - | in reach |
| Miracle Worker | Paladin | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 150,000 | - | in reach |
| Miracle Worker | Priest | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 150,000 | - | in reach |
| Miracle Worker | Shaman | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 150,000 | - | in reach |
| Miracle Worker | Druid | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 150,000 | - | in reach |
| Field Medic | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Field Medic | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Field Medic | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Field Medic | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Chaplain | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Chaplain | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Chaplain | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Chaplain | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Raid Medic | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Raid Medic | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Raid Medic | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Raid Medic | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Guardian Angel | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Guardian Angel | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Guardian Angel | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Guardian Angel | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Resurrector | Paladin | casts of Resurrection, Redemption, Ancestral Spirit, Rebirth, Revive | Redemption | 1,500 | 0.6 | in reach |
| Resurrector | Priest | casts of Resurrection, Redemption, Ancestral Spirit, Rebirth, Revive | Resurrection (level 10) | 1,500 | 0.6 | in reach |
| Resurrector | Shaman | casts of Resurrection, Redemption, Ancestral Spirit, Rebirth, Revive | Ancestral Spirit (level 12) | 1,500 | 0.6 | in reach |
| Resurrector | Druid | casts of Resurrection, Redemption, Ancestral Spirit, Rebirth, Revive | Rebirth (level 20, 1800s cooldown), Revive (level 12) | 1,500 | 0.6 | in reach |
| Cleanser | Paladin | casts of Cleanse, Purify, Dispel Magic, Cure Disease, Abolish Disease, Cure Poison, Abolish Poison, Remove Curse | Cleanse (level 42), Purify (level 8) | 25,000 | 5.2 | in reach |
| Cleanser | Priest | casts of Cleanse, Purify, Dispel Magic, Cure Disease, Abolish Disease, Cure Poison, Abolish Poison, Remove Curse | Abolish Disease (level 32), Cure Disease (level 14), Dispel Magic (level 18) | 25,000 | 3.5 | in reach |
| Cleanser | Shaman | casts of Cleanse, Purify, Dispel Magic, Cure Disease, Abolish Disease, Cure Poison, Abolish Poison, Remove Curse | Cure Disease (level 22), Cure Poison (level 16) | 25,000 | 5.2 | in reach |
| Cleanser | Druid | casts of Cleanse, Purify, Dispel Magic, Cure Disease, Abolish Disease, Cure Poison, Abolish Poison, Remove Curse | Abolish Poison (level 26), Cure Poison (level 14), Remove Curse (level 24) | 25,000 | 3.5 | in reach |
| Battle Medic | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Battle Medic | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Battle Medic | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Battle Medic | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |

## Tank

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Stalwart | Warrior | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Stalwart | Paladin | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Stalwart | Druid | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Damage Sponge | Warrior | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 200,000,000 | - | in reach |
| Damage Sponge | Paladin | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 200,000,000 | - | in reach |
| Damage Sponge | Druid | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 200,000,000 | - | in reach |
| Unbreakable | Warrior | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Unbreakable | Paladin | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Unbreakable | Druid | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Vanguard | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Vanguard | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Vanguard | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Bulwark | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Bulwark | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Bulwark | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Juggernaut | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Juggernaut | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Juggernaut | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Immovable | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Immovable | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Immovable | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Provoker | Warrior | casts of Taunt, Mocking Blow, Challenging Shout, Growl, Challenging Roar, Seal of Fury | Challenging Shout (level 26, 600s cooldown), Mocking Blow (level 16, 120s cooldown), Taunt (level 10, 8s cooldown) | 25,000 | 51.4 | in reach |
| Provoker | Paladin | casts of Taunt, Mocking Blow, Challenging Shout, Growl, Challenging Roar, Seal of Fury | Seal of Fury (level 10) | 25,000 | 10.4 | in reach |
| Provoker | Druid | casts of Taunt, Mocking Blow, Challenging Shout, Growl, Challenging Roar, Seal of Fury | Challenging Roar (level 28, 600s cooldown), Growl (level 10, 8s cooldown) | 25,000 | 54.8 | in reach |
| Frontliner | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Frontliner | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Frontliner | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| First To Fall | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 300 | - | in reach |
| First To Fall | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 300 | - | in reach |
| First To Fall | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 300 | - | in reach |

## Melee

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Brawler | Warrior | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Brawler | Paladin | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Brawler | Rogue | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Brawler | Shaman | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Brawler | Druid | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Bruiser | Warrior | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Bruiser | Paladin | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Bruiser | Rogue | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Bruiser | Shaman | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Bruiser | Druid | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Onslaught | Warrior | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Onslaught | Paladin | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Onslaught | Rogue | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Onslaught | Shaman | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Onslaught | Druid | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Skirmisher | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Skirmisher | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Skirmisher | Rogue | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Skirmisher | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Skirmisher | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Headhunter | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Headhunter | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Headhunter | Rogue | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Headhunter | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Headhunter | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Warbringer | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Warbringer | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Warbringer | Rogue | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Warbringer | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Warbringer | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Untouched | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Untouched | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Untouched | Rogue | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Untouched | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Untouched | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Silencer | Warrior | casts of Kick, Pummel, Shield Bash, Earth Shock, Hammer of Justice, Rebuke, Repentance, Bash, Feral Charge | Pummel (level 38, 10s cooldown), Shield Bash (level 12, 12s cooldown) | 25,000 | 37.9 | in reach |
| Silencer | Paladin | casts of Kick, Pummel, Shield Bash, Earth Shock, Hammer of Justice, Rebuke, Repentance, Bash, Feral Charge | Hammer of Justice (level 8, 60s cooldown), Rebuke (15s cooldown), Repentance (level 20, 60s cooldown) | 25,000 | 69.4 | in reach |
| Silencer | Rogue | casts of Kick, Pummel, Shield Bash, Earth Shock, Hammer of Justice, Rebuke, Repentance, Bash, Feral Charge | Kick (level 12, 10s cooldown) | 25,000 | 69.4 | in reach |
| Silencer | Shaman | casts of Kick, Pummel, Shield Bash, Earth Shock, Hammer of Justice, Rebuke, Repentance, Bash, Feral Charge | Earth Shock (level 4, 6s cooldown) | 25,000 | 41.7 | in reach |
| Silencer | Druid | casts of Kick, Pummel, Shield Bash, Earth Shock, Hammer of Justice, Rebuke, Repentance, Bash, Feral Charge | Bash (level 14, 60s cooldown), Feral Charge (level 20, 15s cooldown) | 25,000 | 83.3 | in reach |
| Cleaver | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Cleaver | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Cleaver | Rogue | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Cleaver | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Cleaver | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Duelist | Warrior | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Duelist | Paladin | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Duelist | Rogue | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Duelist | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Duelist | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |

## Ranged

| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |
| --- | --- | --- | --- | --- | --- | --- |
| Longshot | Hunter | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Longshot | Priest | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Longshot | Shaman | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Longshot | Mage | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Longshot | Warlock | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Longshot | Druid | played time of the sessions that logged in with this spec; no ceiling | - | 1,500 | - | in reach |
| Artillery | Hunter | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Artillery | Priest | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Artillery | Shaman | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Artillery | Mage | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Artillery | Warlock | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Artillery | Druid | the client's damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling | - | 300,000,000 | - | in reach |
| Barrage | Hunter | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Barrage | Priest | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Barrage | Shaman | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Barrage | Mage | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Barrage | Warlock | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Barrage | Druid | the largest single-fight total of the client's damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server | - | 250,000 | - | in reach |
| Backliner | Hunter | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Backliner | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Backliner | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Backliner | Mage | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Backliner | Warlock | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Backliner | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 400 | - | in reach |
| Spellslinger | Hunter | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Spellslinger | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Spellslinger | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Spellslinger | Mage | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Spellslinger | Warlock | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Spellslinger | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Siegebreaker | Hunter | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Siegebreaker | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Siegebreaker | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Siegebreaker | Mage | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Siegebreaker | Warlock | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Siegebreaker | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 650 | - | in reach |
| Out Of Reach | Hunter | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Out Of Reach | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Out Of Reach | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Out Of Reach | Mage | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Out Of Reach | Warlock | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Out Of Reach | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 250 | - | in reach |
| Warden | Hunter | casts of Polymorph*, Frost Nova, Fear, Howl of Terror, Banish, Freezing Trap, Scare Beast, Scatter Shot, Hibernate, Entangling Roots, Shackle Undead, Mind Control, Psychic Scream, Frost Shock, Earthbind Totem | Freezing Trap (level 20, 30s cooldown), Scare Beast (30s cooldown), Scatter Shot (level 30, 30s cooldown) | 25,000 | 69.4 | in reach |
| Warden | Priest | casts of Polymorph*, Frost Nova, Fear, Howl of Terror, Banish, Freezing Trap, Scare Beast, Scatter Shot, Hibernate, Entangling Roots, Shackle Undead, Mind Control, Psychic Scream, Frost Shock, Earthbind Totem | Mind Control (level 30), Psychic Scream (level 14, 30s cooldown), Shackle Undead (level 20) | 25,000 | 5.1 | in reach |
| Warden | Shaman | casts of Polymorph*, Frost Nova, Fear, Howl of Terror, Banish, Freezing Trap, Scare Beast, Scatter Shot, Hibernate, Entangling Roots, Shackle Undead, Mind Control, Psychic Scream, Frost Shock, Earthbind Totem | Earthbind Totem (level 6, 15s cooldown), Frost Shock (level 20, 6s cooldown) | 25,000 | 29.8 | in reach |
| Warden | Mage | casts of Polymorph*, Frost Nova, Fear, Howl of Terror, Banish, Freezing Trap, Scare Beast, Scatter Shot, Hibernate, Entangling Roots, Shackle Undead, Mind Control, Psychic Scream, Frost Shock, Earthbind Totem | Frost Nova (level 10, 25s cooldown), Polymorph (level 8), Polymorph: Cow (level 60) | 25,000 | 5.1 | in reach |
| Warden | Warlock | casts of Polymorph*, Frost Nova, Fear, Howl of Terror, Banish, Freezing Trap, Scare Beast, Scatter Shot, Hibernate, Entangling Roots, Shackle Undead, Mind Control, Psychic Scream, Frost Shock, Earthbind Totem | Banish (level 28), Fear (level 8), Howl of Terror (level 40, 40s cooldown) | 25,000 | 5.1 | in reach |
| Warden | Druid | casts of Polymorph*, Frost Nova, Fear, Howl of Terror, Banish, Freezing Trap, Scare Beast, Scatter Shot, Hibernate, Entangling Roots, Shackle Undead, Mind Control, Psychic Scream, Frost Shock, Earthbind Totem | Entangling Roots (level 8), Hibernate (level 18) | 25,000 | 5.2 | in reach |
| Sniper | Hunter | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Sniper | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Sniper | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Sniper | Mage | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Sniper | Warlock | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Sniper | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 100,000 | - | in reach |
| Bombardier | Hunter | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Bombardier | Priest | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Bombardier | Shaman | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Bombardier | Mage | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Bombardier | Warlock | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
| Bombardier | Druid | the same count as the general feat, over the sessions played in this role; repeatable without limit | - | 2,000 | - | in reach |
<!-- feats:proof end -->
