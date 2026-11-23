# Upload API, version 1

The server side of the upload protocol in [DESIGN.md](DESIGN.md), "Upload protocol" and "Security". This page is
exact enough to write the client from. Paths are under `https://<host>/api/v1`. Every answer is JSON.

A character name is `Name-Surname`: two parts joined by one `-`, each 1 to 40 characters with no whitespace, `-`, `/`
or `\`.

## Signing a request

Every request except `POST /installs` carries three headers, plus the optional `Scarlet-Site`:

| Header | Value |
| --- | --- |
| `Scarlet-Install` | The install id (a UUID) that `POST /installs` returned |
| `Scarlet-Time` | The client's clock, epoch seconds, as decimal digits |
| `Scarlet-Signature` | Base64 (standard alphabet, padded) of the 64-byte Ed25519 signature of the message below |
| `Scarlet-Site` | The host the request is sent to, the same string as the site line of the message below. Without the header the four-line message is signed and the host is not checked |

The message is four lines joined by `\n` (no trailing newline), as bytes:

```
<METHOD>
<path>
<Scarlet-Time>
<lower-case hex SHA-256 of the exact request body bytes>
```

A client binds the signature to the host with a fifth line, the site: the host it dials, lower case, with the port
when it is not the scheme's default (`127.0.0.1:8000`, `scarletweb.io`), sent as `Scarlet-Site` too. The server then
refuses a signature whose site differs from the host the request reached, or from the host the install registered on
(the host of its `POST /installs`, which registration refreshes; an install registered before the site line existed
takes its origin from the first signed request that names one). A signature without the site line keeps the four-line
form and no host check, so a client predating it keeps working.

- `METHOD` is upper case. `path` is the request path as sent, from the leading `/`, with no host and no query string
  (`/api/v1/upload/sync`). The routes take no query string.
- The body hash is over the bytes on the wire: the compressed frame for `/manifest` and `/parts`. A request without a
  body hashes the empty string (`e3b0c442…b855`).
- The server refuses a time more than 300 seconds from its own clock (`clock_skew`, which carries `server_time`) and
  refuses a signature it has seen in the last 600 seconds (`replayed_request`). Ed25519 is deterministic, so
  the same request signed in the same second has the same signature: wait for the next second before repeating one,
  or let a retry sign again with the new time.

### Worked example

This key pair is the published test vector 1 of RFC 8032 section 7.1. It is not a secret; never use it for a real install.

```
seed (hex)        9d61b19deffd5a60ba844af492ec2cc44449c5697b326919703bac031cae7f60
public key        11qYAYKxCrfVS/7TyWQHOg7hcvPapiMlrwIaaPcHURo=      (base64 of the 32 bytes)
```

`POST /api/v1/upload/sync` at time `1795000000` with this body, exactly these bytes:

```
{"character":"Name-Surname","groups":["core","xp"],"count":3,"digest":"0000000000000000000000000000000000000000000000000000000000000000"}
```

```
body SHA-256      4f8d14bff65a243f4d1f0e8895acd487258887b01e63e919f213df5308acc78a
message (text)    POST\n/api/v1/upload/sync\n1795000000\n4f8d14bff65a243f4d1f0e8895acd487258887b01e63e919f213df5308acc78a\nscarletweb.io
message (hex)     504f53540a2f6170692f76312f75706c6f61642f73796e630a313739353030303030300a346638643134626666363561323433663464316630653838393561636434383732353838383762303165363365393139663231336466353330386163633738610a736361726c65747765622e696f
signature         Ktl182KzB6oUL3E+kTlsm6+oNRbOABJQYOb4I++wb1dw1GbermoN0htXo28DTfds4Scl8gHoB7kYkO+b2owjBQ==
```

The request is

```
POST /api/v1/upload/sync HTTP/1.1
Content-Type: application/json
Scarlet-Install: <install id>
Scarlet-Time: 1795000000
Scarlet-Site: scarletweb.io
Scarlet-Signature: Ktl182KzB6oUL3E+kTlsm6+oNRbOABJQYOb4I++wb1dw1GbermoN0htXo28DTfds4Scl8gHoB7kYkO+b2owjBQ==
```

A request with no body, `GET /api/v1/upload/status` at the same time, signs
`GET\n/api/v1/upload/status\n1795000000\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\nscarletweb.io` and gets

```
kyGjrVxJVuHN38ECNIKbvlbGKHqE5/8YeGv+CrgV9hCYhhR33Xo/XyD529ZPFyWf+aO53j+huFmKDwIxTS0JCg==
```

Both signatures were checked with libsodium (`crypto_sign_verify_detached`) against the public key above.

## Errors

A refusal is `{"error": {"code": "...", "message": "...", ...}}` with the HTTP status below. Codes are stable;
messages are for people. Extra fields sit inside `error`.

| Status | Code | When | Extra fields |
| --- | --- | --- | --- |
| 401 | `missing_signature` | A signing header is missing or not well formed | |
| 401 | `unknown_install` | No install has this id | |
| 401 | `install_revoked` | The install was revoked on the account page | |
| 401 | `clock_skew` | `Scarlet-Time` is more than 300 s off | `server_time` |
| 401 | `bad_signature` | The signature does not match the request | |
| 401 | `replayed_request` | This exact signature was seen in the last 600 s | |
| 401 | `wrong_site` | The request reached another host than the site the signature names, or than the install registered on | |
| 409 | `name_held` | Another install holds this character name | `character` |
| 409 | `group_refused` | A group in the request was removed from the server (see "Groups") | `groups` |
| 409 | `already_linked` | The install already belongs to an account | |
| 413 | `body_too_large` | The body is over 4 MiB (`Content-Length` is checked before the body is read) | `limit` |
| 415 | `unsupported_media_type` | Wrong `Content-Type` for `/manifest` or `/parts` | `expected` |
| 422 | `invalid_request` | A field is missing or wrong | `fields` |
| 422 | `invalid_batch` | The body is not a zstd frame of length-prefixed parts, or breaks a size limit | |
| 422 | `unknown_group` | A group key is not in the sharing groups table | `group` |
| 422 | `group_not_switchable` | `core` and `names` cannot be switched off or removed | `group` |
| 422 | `too_many_characters` | The install already uploads 50 characters | `limit` |
| 422 | `part_not_requested` | A part in `/parts` was not asked for by the last `/manifest` and is not stored | `part` |
| 422 | `unknown_character` | `/parts` before a `/manifest` for that character | `character` |
| 429 | `rate_limited` | Over the request budget | `retry_after` (seconds) |
| 429 | `daily_limit` | The install reached 200 MiB of compressed parts for the UTC day | `limit_bytes`, `used_bytes` |

Limits: registration 5 per address per hour; signed requests 600 per address and 240 per install per minute.
A client that sees 429 waits `retry_after` seconds.

## Registration and install

### `POST /installs` (unsigned)

```json
{"public_key": "<base64 of the 32-byte Ed25519 public key>", "name": "optional label, up to 64 characters"}
```

`201` with `{"install": {"id": "<uuid>", "name": "...", "created_at": "2026-11-20T10:00:00Z"}}`. Registering a key
that already exists answers `200` with the same install, so a retry after a lost answer is safe. A revoked key answers
`401 install_revoked`.

### `GET /installs/me`

```json
{"install": {"id": "...", "name": "...", "created_at": "...", "last_seen_at": "..."},
 "linked": false, "link_code_expires_at": null, "characters": 3}
```

`linked` turns true once the player confirms a link code in the browser; the app polls this.
`link_code_expires_at` is set while an unused code is waiting.

### `GET /installs/me/feats`

The feats of every character this install owns (uploaded by it, or on the account it is linked to) that has any,
most recently seen first, with the economy shown. Each entry is what the character page's Feats section shows, plus
the feats not yet shown to the owner:

```json
{"characters": [{
  "name": "Name-Surname", "held": 31, "total": 75,
  "tiers": [{"count": 2, "name": "gold"}, {"count": 29, "name": "copper"}],
  "since": null, "limit": 3, "pinned": [],
  "top": [{"key": "slayer", "pinned": false, "tier": 2, "icon": "kills", "name": "Slayer", "tier_name": "Silver",
           "detail": "3,100 kills, 6,900 to gold at 10,000", "track": [100, 100, 3, 0, 0]}],
  "earned": ["... every card with tier 1 or more"], "unearned": ["... every card with tier 0"],
  "new": [{"key": "slayer", "name": "Slayer", "icon": "kills", "from": 1, "from_name": "Copper", "tier": 2, "tier_name": "Silver"}]
}]}
```

`new` lists the feats whose tier is above the tier last shown, highest tier first. `from` is 0 and `from_name` null for
a feat earned for the first time, and above 0 for an upgrade. `since` is the date an observed count started, or null.

### `POST /installs/me/feats/seen`

```json
{"character": "Name-Surname", "feats": {"slayer": 2}}
```

Records the tier shown for each feat, so `new` stops listing it. `204` with no body. A tier above the one the
character holds is lowered to it, a tier below the one recorded changes nothing, and sending it twice is safe. `404
not_found` when the character is not this install's. `422 invalid_request` for a feat that does not exist or a tier
outside 1 to 5.

### `GET /installs/me/class-page/{character?}`

The class page of every character this install owns (uploaded by it, or on the account it is linked to), public or
not, whose class has a page and whose sessions counted a cast, most recently seen first: the data the page at
`/c/{name}/{class}` draws. A character with no class page is not listed, so a character's absence is the answer. With
`{character}` (`Name-Surname`, percent-encoded in the path that is signed) the list holds only that character, and is
empty for a name the install does not own. A name that is not `Name-Surname` is `404`.

```json
{"characters": [{
  "name": "Name-Surname", "class": "warlock", "class_name": "Warlock", "scene": "warlock",
  "url": "https://scarletweb.io/c/Name-Surname/warlock",
  "links": {"character": "https://scarletweb.io/c/Name-Surname", "class_page": "https://scarletweb.io/c/Name-Surname/warlock", "build": null, "battlegrounds": null},
  "character": {"name": "Name Surname", "level": 52, "hours": 5.9, "hours_text": "5.9", "build": {"tree": "Affliction", "points": [30, 0, 12]}, "icon": "https://scarletweb.io/spell-icons/136.png?v=1"},
  "callouts": [{"key": "voidwalker", "title": "Zharmok", "text": "Voidwalker, out 2.7 hours", "note": "45% of demon time", "left": false}],
  "figures": [{"label": "Soul shards made", "value": 38, "unit": "count", "text": "38", "of": null, "icon": null}],
  "sheet": [{"modules": [{"heading": "Soul shards", "span": "W12", "note": null, "icon": null, "blocks": ["..."]}]}],
  "talents": [{"name": "Nightfall", "points": 2, "max": 2, "icon": null, "note": null, "blocks": ["..."]}],
  "party": ["... rows as in sheet"],
  "comparison": {"of": "warlocks", "from": 50, "to": 59, "characters": 31, "standings": [{"label": "Life Taps", "value": 18, "unit": "count", "text": "18", "percent": 72, "icon": null}]},
  "feats": [{"name": "Life-tapper", "tier": 2, "tier_name": "Silver", "detail": "18 Life Taps", "track": [100, 40, 0, 0, 0], "glyph": "spell", "icon": null}]
}]}
```

Numbers are numbers with `unit`, one of `count`, `short`, `decimal`, `percent`, `percent_tenth` or `hours`, and `text`,
the number as the page writes it (`254k`, `71%`). `comparison` is `null` under 20 characters of the class in the
bracket. `span` is a module's width on the sheet: `Third`, `W5`, `W7`, `W8` or `W12`. Every `icon` is an absolute
address on the site or `null`, and `scene` names the class's scene art. Hues and tones are by name: `tone` is `class`
(the class colour), `scarlet`, `lost` (grey) or `held` (white), and `hue` is a `#rrggbb` colour or `var(--name)` of
the site's theme (`var(--class)` is the class colour), at most one of the two set. A block has a `type`:

| `type` | Fields |
| --- | --- |
| `flow` | `unit`, `label`, `left` and `right` lists of `{label, count, icon, tone}`, at most 8 and 12 |
| `link` | `unit`, `label`, `left` and `right` lists of `{label, icon, tone}`, `pairs` of `{left, right, count}` with indexes into them |
| `mirror` | `label`, `unit`, `up` and `down` as `{label, hue}`, `points` of `{level, up, down}` |
| `stack` | `label`, `series` of `{label, hue}`, `columns` of `{label, shares}` with one share from 0 to 1 for each series |
| `bag` | `noun`, `slots`, `items` of `{icon, slots}` |
| `rank` | `unit`, `plain`, `of`, `rows` of `{label, aside, value, text, icon, tone, hue, share}` |
| `pair` | `rows` of `{label, small, value, unit, text}` |
| `split` | `parts` of `{label, value, tone, hue}` |
| `titled` | `title`, `icon`, `blocks` |
| `columns` | `columns`, two to four `titled` blocks |

### `POST /installs/link-code`

No body. `201` with `{"code": "XXXX-XXXX", "redeem": "scarlet-link-redeem\nXXXX-XXXX", "expires_at": "...", "expires_in": 600}`.
The app signs the `redeem` line with its own key (docs/API.md, "Signing") and opens
`/link?code=XXXX-XXXX&redeem=<base64 signature>` in the browser; the confirm there carries the signature back, so only
the holder of the install's key can complete the link — the code string alone binds nothing. A code works once. An
install that is already linked gets `409 already_linked`.

## Upload

The three steps run per character, in order: sync, manifest, parts. The order of characters does not matter.

`groups` everywhere is the list of group keys that are on in the app. The keys are those of `docs/groups.tsv`.

### `POST /upload/sync`

`Content-Type: application/json`

```json
{"character": "Name-Surname", "groups": ["core", "xp"], "count": 3, "digest": "<64 hex>"}
```

`count` is the number of parts the app holds for the character in those groups. `digest` is the XOR of their ids: the
32-byte ids XORed byte by byte, written as 64 lower-case hex characters (all zeros for no parts).

Answer: `{"match": true, "count": 3, "digest": "<64 hex>"}`. `count` and `digest` are the server's. When `match` is
true the character is done. A character the server has never seen answers `match: false, count: 0`.

### `POST /upload/manifest`

`Content-Type: application/vnd.scarlet.manifest+zstd`. The body is one zstd frame of this JSON, the
`manifest.json` that `scarlet-part pack` writes:

```json
{"character": "Name-Surname", "groups": ["core", "xp"],
 "parts": [{"id": "<64 hex>", "session": 1795000000, "group": "core", "lines": 120, "raw_bytes": 4096}]}
```

At most 200,000 parts, and every part's `group` must be in `groups`. The first manifest of a name claims it for the
install (`name_held` when another install, not of the same account, holds it).

Answer:

```json
{"need": ["<64 hex>", "..."], "present": 1710, "removed": 0}
```

`need` lists the ids the server does not hold, in manifest order. `present` is how many listed parts it already holds.
`removed` is how many parts it deleted: parts in the groups that are on that this install listed before, the manifest no
longer lists and no other install of the character lists; they and their decoded rows are gone. A part another install
of the character uploaded stays. Parts of a group that is switched off in `groups` stay until the app removes the group (below).

### `POST /upload/parts`

`Content-Type: application/vnd.scarlet.parts+zstd`. The body is one zstd frame (level 19, window at most 8 MiB) of
the concatenation, for each part, of

```
u32 little-endian byte length of the part text
the part text (UTF-8)
```

At most 4 MiB compressed, 64 MiB decompressed and 20,000 parts per request. Send the ids from `need` in as many
requests as the limits ask for. The server hashes each part text with SHA-256 and uses the result as the id; a part
whose id the last manifest did not ask for is refused with `part_not_requested` and nothing in the request is stored.

Answer: `{"stored": ["<64 hex>", "..."], "created": 12}`. `stored` lists every id of the request, `created`
counts those that were new. Sending the same request again stores nothing and answers the same (sign it again with a
new time). Decoding runs in the background; `sessions_waiting` in the status answer shows what is left.

Sizes measured on three real histories: a sync body is 270 to 280 bytes; a manifest of 1,500 parts is 61.7 KB; the
parts request for that whole history (148 sessions, 6.5 MB of text) is 1.2 MB, which is one request.

### `GET /upload/status`

```json
{"characters": [{"name": "Name-Surname", "parts": 569, "raw_bytes": 2412345, "sessions": 56,
                 "sessions_waiting": 0, "last_part_at": "2026-11-20T10:00:00Z"}],
 "usage": {"day": "2026-11-20", "bytes": 1234567, "limit_bytes": 209715200},
 "groups_refused": ["movement"]}
```

`characters` are those the install's owner holds (the account's, or the install's own when unlinked).
`sessions_waiting` counts sessions with parts not decoded yet.

## Groups and removal

`DELETE /upload/groups/{key}`, `PUT /upload/groups/{key}` and `DELETE /upload` take no body. `key` is 2 to 16
lower-case letters.

- `DELETE /upload/groups/{key}`: removes that group's parts and decoded rows for every character of the install's
  owner, refreshes the rollups over the time those characters played, and marks the group refused. Answer:
  `{"group": "movement", "refused": true, "parts_removed": 55, "rows_removed": 173412}`. After that, `/sync` and
  `/manifest` with the group still in `groups`, and `/parts` carrying it, answer `409 group_refused` with `groups`
  listing the refused keys. The app switches those groups off and goes on without them. `core` and `names` answer
  `422 group_not_switchable`.
- `PUT /upload/groups/{key}`: accepts the group again. Answer `{"group": "movement", "refused": false}`. The app
  then sends the parts from its local history through the normal three steps.
- `DELETE /upload`: removes every part, every decoded row and the characters of the owner, which frees the names.
  Installs and keys stay. Answer `{"characters_removed": 3, "parts_removed": 1708, "rows_removed": 400000}`.

## One upload, start to end

1. Register once: `POST /installs`, keep the install id and the private key.
2. For each character: `POST /upload/sync`. On `match: true`, stop.
3. `POST /upload/manifest` with every part. Read `need`.
4. `POST /upload/parts`, one request per batch of `need`, each within the size limits.
5. Next day, repeat from 2. A day with nothing new costs one request.

## API keys

A signed-in player makes API keys on the account page (`/account`, API keys). A key is a Sanctum personal access token
with one ability, `public:read`, for the public read API under `/api/v1/public`. Send it as `Authorization: Bearer <key>`
for the higher rate limit. The text of a key is shown once when it is made; the server stores only its hash. An account
holds at most 10 keys, lists them with the day each was made and last used, and deletes one at any time. Upload
requests do not use keys: they are signed by the install.

# Public API, version 1

Everything the public pages show, as read-only JSON. No sign-in. Paths are under `https://<host>/api/v1/public`. The
`/developers` page is written from the same list as the routes (`App\Support\PublicApiCatalog`), so the two agree.

## Answers

Every answer is UTF-8 JSON: `{"data": ..., "meta": {...}}`. Times are ISO 8601 in UTC, durations are seconds and money
is copper. A number that fewer than `meta.min_characters` characters contributed to is `null`, or its row is left out.
A character answers unless its owner made it private; any other character is `404 not_found`, the same as one that does
not exist. Money of a character is in the answer only when its owner shares the economy. The name of another player is
shown only when that player's own character is public.

A character made private or removed, or a sharing group its owner removed from the server, leaves every answer that
names characters within a minute (30 seconds at the origin, 30 at the edge). Statistics catch up after the rollup
refresh that the removal starts, within twelve minutes of it.

A ruleset is addressed by slug: `normal`, `pvp`, `roleplay`, `roleplay-pvp`, `hardcore`, or `unidentified` for characters
whose ruleset is not known yet. The name a page shows is `label`, and `kind` is the ruleset of WoW Forever. A slug from
before rulesets were kinds (`p1-std-pve-xp-g1`) or a ruleset's old numeric id answers with the ruleset that took it over.
Take the slugs from `GET /public/rulesets`.

## Limits, keys and caching

- Without a key: 60 requests a minute per address; an IPv6 address counts by its /64. With a key: 600 a minute for the
  account, shared by all its keys. A key is a personal access token with the `public:read` ability, made on the account
  page (see "API keys" above) and sent as `Authorization: Bearer <key>`.
- A key that is unknown, expired or lacks the ability is `401 invalid_token`; it is not treated as no key. After 10 of
  those from one address in a minute, the answer is `429 rate_limited` until the minute is over.
- Over the limit the answer is `429 rate_limited` with `Retry-After` in seconds and `retry_after` in the body.
  `X-RateLimit-Limit` and `X-RateLimit-Remaining` are on every answer.
- `Cache-Control: public, max-age=300` and an `ETag` on every successful answer; `max-age=30` on the answers that name
  characters (leaderboards, camps, professions, search and characters). Send the tag back as `If-None-Match`: an unchanged answer is `304`
  with no body. `Vary: Authorization`.
- CORS is open: `Access-Control-Allow-Origin: *` for `GET` and `OPTIONS`, with `ETag`, `Retry-After` and the rate-limit
  headers exposed to scripts.

## Pagination

A list answers its rows in `data` and `meta.next_cursor`. Pass the cursor back as `?cursor=` with the same other
parameters. A `null` cursor is the end. The cursor is opaque; do not build one. `limit` is 1 to 100 (25 by default).

```
curl 'https://<host>/api/v1/public/search?kind=quests&q=th&limit=40'
curl 'https://<host>/api/v1/public/search?kind=quests&q=th&limit=40&cursor=bzQw'
```

## Errors

`{"error": {"code": "...", "message": "..."}}` with the HTTP status; codes never change within a version, messages are
for people. `404 not_found` (also for an unknown path under `/api`), `422 invalid_request` (with `fields`),
`401 invalid_token`, `429 rate_limited`. Errors are `Cache-Control: no-store`.

## Endpoints

### Statistics

#### `GET /public/stats`

Headline numbers: characters, experience, time recorded, kills, deaths, quests and money, with when the data starts and was last updated.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |

`data.totals`, `data.coverage`, `data.rulesets`. A total is `null` while fewer than `meta.min_characters` characters contributed to it.

Example: `curl https://<host>/api/v1/public/stats`

#### `GET /public/camps`

How characters use campfires: visits, how long they last, what gets set up, the Camp Benefits players leave with, play while holding them, where and when characters camp, and the characters with the most time by the fire.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |

A visit is a stay by a campfire that began in the session; a stay carried over from the session before is not one. `data`:

- `totals`: `visits`, `characters`, `seconds` (every stay, a carried one included), `median_seconds` (of the visits),
  `played_seconds` (all play in the scope), and the visits that `earned` Camp Benefits, `sat`, used a tent
  (`tented`, and `tent_rests` for 30 seconds or more), `logged_out` by the fire or `died_after` within five minutes;
  `afk_seconds` and the seconds of the stays that know their AFK time (`afk_known_seconds`); `crafted` stays and the
  stays that know their crafts (`crafts_known`); `placed_tent` and `placed_chair`.
- `fires`: visits by the campfire the character placed (Basic, Journeyman, Expert) and a row with `spell_id` `null`
  for a fire that was already there. All four rows are always listed.
- `camp_benefits` and `benefits`: the Camp Benefits aura and the nine benefit buffs of the camp furniture, each with
  `gains`, `two_hour_gains` (the gains given for two hours instead of one), `seconds`, `lost_to_death` and
  `characters`; a buff also has `spell_id`, `label` and `effect`. All nine are
  listed, most gains first.
- `lengths`: visits by `step`, the length in 30 seconds, 40 meaning 20 minutes and over. `by_level`: `visits`,
  `tent_rests` and `played_seconds` at each level. `hours`: visits by UTC `weekday` (0 is Sunday) and `hour`.
- `buffed`: play while holding Camp Benefits: `seconds`, `xp_seconds` and `deaths_seconds` (the held time of the rows that
  know their experience and their deaths), `xp` and `deaths`, against `expected_xp` and `expected_deaths`, which are what the same seconds at the
  same levels give across all play of the scope.
- `zones`: the ten zones with the most visits. `heat`: the zone map with the most visits (`zone_id`, `map`, `name`)
  and its 100 by 100 `cells`.
- `board`: the first ten rows of `most-time-by-the-fire`, shaped like `/leaderboards/{board}`, and `rulesets`.

A figure is `null` or its row is left out while fewer than `meta.min_characters` characters contributed to it. Under
that limit for the whole scope, the totals are 0 and `characters` is `null`. This answer names characters, so it is
cached for 30 seconds.

Example: `curl https://<host>/api/v1/public/camps`

#### `GET /public/professions/{slug}`

How characters work with one profession. The four gatherings, `fishing`, `herbalism`, `mining` and `skinning`, answer with attempts and catches, the time they take, what they give, the rank characters reach, where and when they gather, and the characters with the most catches. The eight crafts, `alchemy`, `blacksmithing`, `enchanting`, `engineering`, `leatherworking`, `tailoring`, `cooking` and `first-aid`, answer with crafts, their sittings, recipes, reagents, the rank characters reach, where and when they craft, and the characters with the most crafts. Every other slug, and a slug that names none, answers `404 not_found`.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `slug` | path | string | A profession slug: `fishing`, `herbalism`, `mining`, `skinning`, `alchemy`, `blacksmithing`, `enchanting`, `engineering`, `leatherworking`, `tailoring`, `cooking` or `first-aid`. |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |

A crafting profession's `data`, counting the crafts whose `profession_id` is the trade's skill line:

- `profession`: the slug.
- `totals`: `crafts`, `made` (the sum of `quantity`), `characters`, `scope_characters` (characters with any play in the
  scope), `recipes` (different recipes that enough characters made), `sittings` (crafts with no gap of 120 seconds or less before them),
  `median_sitting_crafts` and `median_sitting_seconds` (a sitting runs from its first craft to its last; read from the rows
  of the last 90 days), `median_gap` (seconds between crafts of a sitting), `skill_ups`, `trivial` (crafts of a recipe
  with difficulty 3), `median_skill` (over characters, of the highest rank each had) and `at_cap` (characters at rank 300).
- `recipes`: the twelve recipes with the most crafts: `recipe_id`, `name`, `item_id` and `item` (what the crafts made
  most often), `crafts`, `made`, `skill_ups`, `characters`, `median_skill` (the rank before a craft) and `reagents`
  (`item_id`, `name`, `quantity`; empty until someone uploads the recipe).
- `difficulty`: `crafts` and `skill_ups` by the recipe's `difficulty`, 0 optimal to 3 trivial. `per_point`: `crafts`
  and `skill_ups` by `band` of 25 ranks. `by_level`: `median_skill` and `characters` at each character level.
  `hours`: crafts by UTC `weekday` (0 is Sunday) and `hour`.
- `reagents`: the ten items the crafts used most, each recipe's reagents times its crafts (`item_id`, `name`,
  `quantity`); empty while no recipe is uploaded. `zones`: the ten zones with the most crafts (`zone_id`, `name`,
  `crafts`, `characters`).
- `board`: the first ten rows of the profession's board of crafts (`most-alchemy-crafts`, `most-blacksmithing-crafts`,
  `most-enchanting-crafts`, `most-engineering-crafts`, `most-leatherworking-crafts`, `most-tailoring-crafts`,
  `most-cooking-crafts` or `most-first-aid-crafts`), shaped like `/leaderboards/{board}`, and `rulesets`.

A trade nobody has recorded answers `200` with zero totals, `characters` `null` and empty lists.

A gathering profession's attempt is one cast of the gathering spell; it is looted when a loot window followed it. Its `data`:

- `kind`: `fish`, `herb`, `ore` or `skin`.
- `totals`: `attempts`, `looted`, `characters`, `scope_characters` (characters with any play in the scope), `seconds`
  (the time cast; for fishing also the gaps between casts, which is the time at the water), `played_seconds` (all play in
  the scope), `median_seconds` (of the looted attempts: for fishing the wait for a bite), `trips` (attempts that start a
  run, one with no gap of 120 seconds or less before it) with `median_trip_seconds` and `median_trip_attempts`, `in_combat`,
  `skill_ups`, `median_skill` (over characters, of the highest rank each had), `at_cap` (characters at rank 300),
  `items` (different items given), `container_catches` (item rows that were opened later as a container) and, for
  fishing, `schools` (catches from a school, not the bobber's) or, for mining, `first_strikes` (`null` for the others).
- `items`: the twelve items attempts gave most, with `quantity`, `gathers` (item rows), `characters`, `median_skill` and
  the `zone` where it came up most. `contents`: the eight items most often taken out of caught containers
  (`quantity`, `opens`).
- `waits`: looted attempts by `step` of two seconds, 15 meaning 30 seconds and over. `per_point`: `looted` and
  `skill_ups` by `band` of 25 ranks. `by_level`: `median_skill` and `characters` at each character level.
  `hours`: attempt starts by UTC `weekday` (0 is Sunday) and `hour`.
- Fishing: `lures` (the six lures most casts were made under, `spell_id`, `name`, `casts`; a lure counts for 600 seconds,
  the classic length, which no recording has confirmed), `lured` (the casts counted in `lures`) and `poles` (the six
  poles most casts were made with, `item_id`, `name`, `casts`). Both lists are empty and `lured` is `null` for the others.
- Skinning: `creatures`, the ten creatures most often skinned (`npc_id`, `name`, `gathers`, `characters`); empty for the others.
- `zones`: the ten zones with the most looted attempts. `heat`: the zone map with the most attempts (`zone_id`, `map`,
  `name`) and its 100 by 100 `cells`, with the attempts as `visits`.
- `board`: the first ten rows of the board of looted attempts (`most-fish-caught`, `most-herbs-gathered`, `most-ore-mined`
  or `most-creatures-skinned`), shaped like `/leaderboards/{board}`, and `rulesets`.

A figure is `null` or its row is left out while fewer than `meta.min_characters` characters contributed to it. Under
that limit for the whole scope, the totals are 0 and `characters` is `null`. This answer names characters, so it is
cached for 30 seconds.

Example: `curl https://<host>/api/v1/public/professions/fishing`

#### `GET /public/rulesets`

Every ruleset with the numbers of the comparison table and its levelling curve.

`data`: a list of rulesets with `slug`, `label`, `hardcore`, `war_mode`, `kind` (`normal`, `pvp`, `roleplay`, `roleplay-pvp` or `hardcore`, `null` while Scarlet cannot tell which ruleset a character is on), `recorded` (false for the row of characters whose ruleset is not known yet), `characters`, `seconds`, `deaths`, `xp`, `quest_xp`, `reached`, `median_to_target` and `levelling` (level, median seconds, characters).

Example: `curl https://<host>/api/v1/public/rulesets`

#### `GET /public/rulesets/{slug}`

The headline numbers of one ruleset.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `slug` | path | string | A ruleset slug from `/rulesets`. |

The same as `/stats`, for this ruleset.

Example: `curl https://<host>/api/v1/public/rulesets/normal`

#### `GET /public/rulesets/{slug}/series/{series}`

One series of a ruleset: `levels`, `xp`, `deaths`, `dungeons`, `professions`, `gold` or `activity`. Use `all` as the slug for every ruleset.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `slug` | path | string | A ruleset slug, or `all`. |
| `series` | path | string | The series name. |

`data`: the rows of the series. Buckets with fewer than `meta.min_characters` characters are left out.

Example: `curl https://<host>/api/v1/public/rulesets/all/series/levels`

### Leaderboards

#### `GET /public/leaderboards`

The boards: fastest to level 20, 40 and 60, highest level without dying, most quests turned in, most quests completed, most kills, most dungeon runs, most distance, most time by the fire, most fish caught, most herbs gathered, most ore mined and most creatures skinned.

`data`: a list of boards with `board` (the name used in the path), `title`, `description` and `value` (the heading of the value column).

Example: `curl https://<host>/api/v1/public/leaderboards`

#### `GET /public/leaderboards/{board}`

One board, best first. Only public characters with counted sessions are listed.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `board` | path | string | A board name from `/leaderboards`. |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |
| `class` | query | string | A class file name such as `WARRIOR`. |
| `cursor` | query | string | The `next_cursor` of the previous page. |
| `limit` | query | integer | Rows per page, 1 to 100. Default 25. |

`data`: rows with `rank`, `name`, `race`, `class`, `value`, `ruleset`, `hardcore`, `deaths`, `since` (the date the character was first recorded, `null` when recorded from level 1) and `at`. `meta.next_cursor`, `meta.total`.

Example: `curl https://<host>/api/v1/public/leaderboards/most-quests?limit=2`

### Search

#### `GET /public/search`

Characters (only those made public), creatures, items, objects, zones and quests whose name holds every word of the text, and the site's own pages. Best match first: exact, prefix, word, contains, then a close spelling when nothing else matched.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `q` | query | string | The text to search for, 2 to 80 characters. |
| `kind` | query | string | `characters`, `creatures`, `items`, `objects`, `zones` or `quests`. Leave out for the first hits of every kind and the pages. |
| `cursor` | query | string | The `next_cursor` of the previous page. |
| `limit` | query | integer | Rows per page, 1 to 100. Default 25. |

Without `kind`: `data` has one entry per kind and a seventh, `pages`, each with `count` and `hits`. With `kind`: `data` is the list of hits, paged with `meta.next_cursor`. A hit has `kind`, `id`, `name`, `path`, `match` (`exact`, `prefix`, `word`, `contains` or `fuzzy`) and the numbers of its kind; a text of digits also finds the creature, item, object, quest or zone with that id as an `exact` hit. A page hit has `title`, `path`, `meta` and `match`.

Example: `curl https://<host>/api/v1/public/search?q=ash&kind=zones`

### Characters

#### `GET /public/characters/{name}`

A public character: race, class, level, ruleset, time played, this week, distance walked, feats and standings. A character that is not public answers 404.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `name` | path | string | The character name, `Name-Surname`. |

`data`: `name`, `race`, `class`, `faction`, `level`, `played_seconds` (the total the game reports, `null` until Scarlet has read it), `recorded_seconds` (play Scarlet counted), `first_level`, `recorded_since`, `whole_life` (recorded from level 1), `ruleset`, `last_seen_at`, `week`, `distance`, `feats` (every feat that applies to the character: those earned first, rarest first, then those not yet earned, closest first; `pinned` is true for a feat the owner pinned, which keeps its place in the list. `key` is one of `summit`, `veteran`, `devoted`, `steadfast`, `seasoned`, `regular`, `long-haul`, `longstanding`, `unbroken`, `slayer`, `bestiary`, `finisher`, `gladiator`, `rampage`, `fallen`, `flattened`, `nemesis`, `territorial`, `roamer`, `questing`, `errand-runner`, `fickle`, `cartographer`, `local-hero`, `thorough`, `delver`, `vanquisher`, `spelunker`, `raider`, `conqueror`, `deep-dweller`, `flawless`, `familiar`, `soloist`, `full-house`, `explorer`, `globetrotter`, `local`, `wayfarer`, `jumper`, `swimmer`, `runner`, `rider`, `stroller`, `tracker`, `scout`, `witness`, `naturalist`, `skullseer`, `looter`, `scavenger`, `lucky`, `collector`, `jackpot`, `curator`, `unboxer`, `angler`, `fishmonger`, `wading`, `gatherer`, `forager`, `artisan`, `master`, `practised`, `crafter`, `repertoire`, `earner`, `spender`, `tycoon`, `windfall`, `expressive`, `mime`, `signature`, `indecisive`, `battle-cry`, `charger`, `executioner`, `stance-dancer`, `mortal-striker`, `overpowering`, `bloodletter`, `weapon-master`, `bloodthirsty`, `whirling`, `seeing-red`, `berserker`, `sunderer`, `vengeful`, `blockade`, `shieldbearer`, `judicator`, `benediction`, `last-resort`, `untouchable`, `radiant`, `quickening`, `purifier`, `lightbringer`, `bastion`, `lawbringer`, `consecrator`, `templar`, `sealbearer`, `hammerfall`, `zealot`, `crusader`, `playing-dead`, `trapper`, `marked-prey`, `beast-tamer`, `unleashed`, `intimidating`, `pet-mender`, `pack-leader`, `deadeye`, `spreadshot`, `arcane-archer`, `sharpshooter`, `close-quarters`, `wing-clipper`, `escapist`, `survivalist`, `unseen`, `pickpocket`, `locksmith`, `vanishing-act`, `eviscerator`, `bleeder`, `cold-blooded`, `assassin`, `cutthroat`, `gouger`, `blade-dancer`, `swashbuckler`, `first-strike`, `sandman`, `bloodspiller`, `shadowstalker`, `fortifier`, `aegis`, `puppeteer`, `shrieker`, `inner-flame`, `dispeller`, `infuser`, `disciplinarian`, `mender`, `renewer`, `prayer-leader`, `high-priest`, `mind-flayer`, `pain-bringer`, `mind-blaster`, `voidspeaker`, `totemic`, `pack-runner`, `shock-therapy`, `twice-born`, `bolt-thrower`, `chain-reaction`, `purger`, `stormcaller`, `stormstriker`, `weapon-imbuer`, `charged`, `spiritwalker`, `wave-rider`, `chain-healer`, `tide-turner`, `tidecaller`, `sheep-herder`, `portal-master`, `quartermaster`, `blinker`, `missile-barrage`, `detonator`, `channeler`, `arcanist`, `firestarter`, `pyromaniac`, `scorcher`, `pyromancer`, `frostbinder`, `shatterer`, `iceborn`, `cryomancer`, `soul-harvester`, `life-tapper`, `fearmonger`, `summoner`, `corruptor`, `agonizer`, `leech`, `hexweaver`, `demon-caller`, `funneler`, `pactbinder`, `demonologist`, `bolt-slinger`, `immolator`, `searing`, `destroyer`, `shapeshifter`, `wild-gift`, `back-from-the-brink`, `entangler`, `wrathbringer`, `starcaller`, `moonlit`, `stargazer`, `mauler`, `shredder`, `savage`, `wildheart`, `greenthumb`, `rejuvenator`, `swiftmender`, `grovekeeper`, `caretaker`, `lifegiver`, `miracle-worker`, `field-medic`, `chaplain`, `raid-medic`, `guardian-angel`, `resurrector`, `cleanser`, `battle-medic`, `stalwart`, `damage-sponge`, `unbreakable`, `vanguard`, `bulwark`, `juggernaut`, `immovable`, `provoker`, `frontliner`, `first-to-fall`, `brawler`, `bruiser`, `onslaught`, `skirmisher`, `headhunter`, `warbringer`, `untouched`, `silencer`, `cleaver`, `duelist`, `longshot`, `artillery`, `barrage`, `backliner`, `spellslinger`, `siegebreaker`, `out-of-reach`, `warden`, `sniper`, `bombardier`; the gold feats `earner`, `spender`, `tycoon` and `windfall` are listed only when `meta.economy` is true; `name`, `value`, `tier` from 0 for none to 5 for legend, with `tier_name` one of `Copper`, `Silver`, `Gold`, `Scarlet` or `Legend`, `threshold` of the tier held and `next`, the threshold of the tier above, `null` at the top; `artisan` counts the ranks of Cooking, First Aid, Fishing and the character's two highest primary professions), `standings` (a list, best place first, of the boards the character is on among public characters of every ruleset: `board`, `title`, `place`, `total` and `value`). `meta.economy` says whether the owner shows money.

Example: `curl https://<host>/api/v1/public/characters/Tolvir-Greane`

#### `GET /public/characters/{name}/series/{series}`

One series of a public character: `levelling` (played time before each level, with the ruleset median and middle half), `xp`, `deaths`, `dungeons`, `activity`, `sessions`, and `gold` when the owner shows money.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `name` | path | string | The character name. |
| `series` | path | string | The series name. |

`data`: the rows of the series.

Example: `curl https://<host>/api/v1/public/characters/Tolvir-Greane/series/levelling`

#### `GET /public/characters/{name}/sessions`

The sessions of a public character, newest first, 20 a page, for a character whose owner keeps the session pages on. Any other character answers 404.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `name` | path | string | The character name. |
| `after` | query | integer | The `next` of the previous page: the start of the oldest session it listed. |

`data`: `sessions`, each with `start` (the id of the session in its address: epoch seconds), `end`, `played` (seconds), `level` (`[at the start, at the end]`), `title` (`rule`, one of `level`, `dungeon`, `quests`, `kills`, `gather`, `travel` or `time`, and the English `text`), and `kills`, `quests`, `deaths` and `xp`, each `null` when the player did not share that group; and `next`, the `after` of the next page, `null` on the last.

Example: `curl https://<host>/api/v1/public/characters/Tolvir-Greane/sessions`

#### `GET /public/characters/{name}/sessions/{start}`

One session of a public character as a recap: title, played time, levels, experience by source, counters, five-minute buckets, zones in order, levels, moments, route, the top lists, the best of the last 30 sessions, and how it compares with sessions at the same levels. The data of the page at /c/{name}/sessions/{start}.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `name` | path | string | The character name. |
| `start` | path | integer | The `start` of a session from the list. |

`data`: the same recap the Scarlet app and addon keep: `v`, `character`, `class`, `start`, `end`, `played`, `level`, `zone`, `title`, `xp` (`kills`, `dungeons`, `quests`, `explore`, `other`), `runs`, `counters` (a counter that is zero is left out), `buckets` (`secs`, `xp`, `combat`), `zones`, `levels`, `moments`, `route` (`legs`, and a `sheet` of points fitted to 0 to 1000 with `aspect`, `breaks` and `air`), `feats`, `bests`, `own` (the session against the 30 before it, `null` under 5), `top`, `compare` (against sessions at the same levels, `null` until 20 sessions are in the bracket) and `links`. Gold counters are in it only when `meta.economy` is true.

Example: `curl https://<host>/api/v1/public/characters/Tolvir-Greane/sessions/1763840520`

### Records

#### `GET /public/creatures/{id}`

One creature: its level range and kind, kills, deaths to it, drops under each loot rule and the world cells where it was met.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The creature id, as in the address of its page. |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |
| `rule` | query | string | The loot rule `by_size` is read under, such as `group`. Leave out for the rule with the most corpses. |

`data`: `facts`, `kills`, `deaths`, `deaths_by_level`, `drops` (item, loot rule, drops, opens, rate, `estimate` (`k` drops of `n` corpses looted for the first time, counting only windows read for loot, not for skinning; `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 corpses, `hollow` from 10 and `solid` from 30, and `flags` from `one_character`, `quest_item` and, for a free-for-all rule that counts one reader as the arm of `by_rule` does, `one_reader`; `rate`, `low` and `high` are `null` under 10 corpses)), `solo` (`kills`, the solo kills of the creature: those a character made itself (`credit` 1, its last hit, or 2, a pet or a damage-over-time spell from its threat list) with no corpse key, and every kill whose corpse has a key at a group size of 0 or 1, credit or not; `windows`, the solo windows read for loot; an `estimate` of the two with unit `kill`, and `rows` of item and an `estimate` of the windows that listed it over those kills; `estimate` is `null` while there are no solo kills), `looted` (the same shape over every kill whose corpse has a key, at any group size and however many members got credit, and the solo kills with no key; with the `rule` parameter only the kills with a key under that rule, and the solo kills only under `solo`; `estimate` is `null` while there are no such kills), `drop_value` (what the drop table is worth, under the `rule` asked for: `windows`, `per_window` (the items of an average window in copper, coin left out, `null` under 10 windows), `unpriced` (rows with no value) and `scanned_at` (the newest AHledger scan used), and `rows` of `item_id`, `quantity` and `value` (copper for one item and `from`, `market` or `vendor`, or `null` for a quest item or one with no price); an item is valued at the median of the AHledger US auction house prices or at the vendor price when that is higher, the item is poor or it binds on pickup; credit AHledger when you show them), `skinning` (`windows`, the skin windows of the creature read for the first time, `rows` of item, `quantity`, `per_drop`, an `estimate` as for `drops` and `value` (copper for one item and `from`, `market` or `vendor`, or `null` for a quest item or one with no price), over those windows and whatever the loot rule, and `value` as for `drop_value`), `by_rule` (the drop table split by loot rule: `split`, `rule` (`null`), `rules` and `sizes` (what was recorded, with `windows`), `arms` (one for each rule, with `key`, `label`, `windows`, `corpses`, `characters`, `flags` and `drawn`, true from 10 corpses; a free-for-all arm with windows with no corpse key that a second character contributed to counts one set (docs/LOOT.md, Units of counting, rule 6), and the arm carries `one_reader`), `compared` (the keys of the two drawn arms with the most corpses, never personal loot) and `rows`, one for each item with 10 drops or more, of `item_id`, `item`, `drops`, `arms` (`key` and `estimate` for each arm) and `difference` between the two compared (`difference`, `low` and `high` as shares, `marked` and `flags`; `null` while an arm is under 10 corpses, and `marked` only when both arms have 30 corpses and the range stays off zero after the number of items is taken into account)), `by_size` (the same by group size inside one loot rule: `rule` is the `rule` parameter, else the rule with the most corpses) and `cells`. Drop rates cover every ruleset.

Example: `curl https://<host>/api/v1/public/creatures/257926`

#### `GET /public/items/{id}`

One item: what it is, where it drops by source and loot rule, where it is gathered, the boss encounters that dropped it, the recipes that make or use it, and its auction prices.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The item id, as in the address of its page. |

`data`: `item`, `sources` (source, `read`, loot rule, drops, opens, rate, `estimate` as for the drops of a creature), `by_source` (one row for each source and `read` over every loot rule but personal loot: source, `read` (`loot`, `skin`, `herb`, `ore`, `fish`, `bag` or `other`), `quantity`, `per_drop` and `estimate`, ranked by the low end of the range with sources under 10 corpses last; `rule` and the `rules` the item dropped under), `gathered` (where the item is gathered, the 25 sources with the most gathers: `kind`, `source_kind` (`c` creature, `i` container item, null on a row for the whole profession, such as herbs or ore), `source_id`, `source`, `gathers`, `quantity`, `characters`, `median_skill`, and `rate`, for skins the share of that creature's skins that gave the item), `prices` (one entry for each ruleset and realm: `ruleset`, `realm`, and `days` of `day`, `price` in copper and `browse`), `market_prices` (AHledger's latest price on each auction house that priced it in the last 7 days, from the scans its addon's players upload: `market`, `label`, `scanned_at`, and in copper `median`, `min_buyout`, `low` (the lowest buyout that day), `median_7d` and `median_30d`, null for an item first listed in the latest scan, plus `quantity` and `sold_per_day`, null until AHledger has enough scans of that house; credit AHledger when you show them), `dropped` and `source_count` (drop sources with a known `read`, each counted once however it was read, plus gathering sources), `gathered_count` (gathers over every source), `best_rate`, `first_at`, `encounters` (encounter, item, dropped, characters), `made_by` and `used_in` (recipes: spell, output, reagents), `rolls` (the rolls of the characters that uploaded under the rules that roll, or `null` while too few of them rolled: `rolls`, `all_passed`, the rolls everyone passed, and `need`, `greed` and `pass` as an `estimate` with unit `roll`) and `receivers` (what groups received of it by class, never a name: `class`, `received`, the `share` of all receipts as an `estimate` with unit `receipt`, `rolled` and `won`, the rolls of need or greed the class made and won, and `won_by_roll` as an `estimate` with unit `roll`; each figure is one uploader's view of its group, so a group with several uploaders counts once for each). `item` holds the item's facts (class, equip slot, levels, sell price, bind type, stack, quality) and is `null` while no upload has carried them (an `II` line): an item known only by name and drops, such as one seen in a loot line, still answers with `name` and `sources`.

Example: `curl https://<host>/api/v1/public/items/1465`

#### `GET /public/objects/{id}`

One object, such as a chest, an ore vein or an herb: how often it was opened, by how many characters, where and at which levels, what it gives and the places it was opened.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The object id, as in the address of its page. |
| `rule` | query | string | A loot rule the drop table is read under, such as `group`. Leave out for every loot rule but personal loot. |

`data`: `id`, `name` (`null` until the addon has named it), `read` (`loot`, `herb` or `ore`: the read the drop table covers, the one the object has most windows of), `windows`, `characters`, `items`, `zones` (`id`, `name`, `windows`), `level_min` and `level_max` (the levels of the characters that opened it), `drop_table` (`rule`, `windows`, `characters`, `unread`, `rules` and `rows` of `item_id`, `item`, `quantity`, `per_drop`, `estimate` (`k` windows that held the item of `n` windows read for the first time, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 windows, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10 windows) and `value` (copper for one item and `from`, `market` or `vendor`, or `null` for a quest item or one with no price), and `value` as for the `drop_value` of a creature) and `points` (the `[u, v]` world positions the characters stood at for the newest 600 openings, or `null`). The fishing bobber answers 404: its catches are on `/professions/fishing`.

Example: `curl https://<host>/api/v1/public/objects/1731`

#### `GET /public/spells/{id}`

One spell: what the client says about it (icon, cast time, range, cost, description) and, for a recipe, what it makes and needs.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The spell id, as in the address of its page. |

`data`: `name`, `facts` (`icon_id`, `cast_ms`, `min_range`, `max_range`, `costs` as `powerType:cost` joined by commas, `passive`, `description`) and `recipe` (`output`, `optional_slots`, `reagents`).

Example: `curl https://<host>/api/v1/public/spells/133`

#### `GET /public/encounters/{id}`

One boss encounter: the items it dropped, each drop counted once however many players of the group uploaded it.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The encounter id, as in the address of its page. |

`data.drops`: `item_id`, `item`, `dropped` and `characters`, most dropped first.

Example: `curl https://<host>/api/v1/public/encounters/1065`

#### `GET /public/zones/{id}`

One zone: time played, deaths, what killed characters there and the creatures met. A dungeon has a negative id.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The zone id, as in the address of its page. |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |

`data`: `rect` (the zone on the world map), `played`, `deaths`, `killers`, `creatures`.

Example: `curl https://<host>/api/v1/public/zones/1446`

#### `GET /public/quests/{id}`

One quest: accepts, turn-ins, abandons, experience, and the levels and zones where it is turned in.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The quest id, as in the address of its page. |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |

`data`: `totals`, `levels` (level, turn-ins) and `zones`.

Example: `curl https://<host>/api/v1/public/quests/6521`

### Loot

#### `GET /public/loot/sources`

The creatures, objects or containers that were looted, one row for each: how many times, how many different items, the levels, the zone with most of them and the item that was in most of them. Every ruleset and every loot rule but personal loot. Fishing is not here.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `view` | query | string | `creatures`, `objects` or `containers`. Default `creatures`. |
| `zone` | query | integer | A zone id from `/zones/{id}`. Leave out for every zone. |
| `min` | query | integer | The lowest level: the creature level for `creatures`, the level of the looter for the others. |
| `max` | query | integer | The highest level, read the same way. |
| `quality` | query | integer | The lowest item quality the source gave, 0 poor to 5 legendary. |
| `q` | query | string | Text that is in the name of the source or of an item it gave, or the id of the source. 2 to 80 characters. |
| `sort` | query | string | `windows` (default), `items` or `level`. |
| `cursor` | query | string | The `next_cursor` of the previous page. |
| `limit` | query | integer | Rows per page, 1 to 100. Default 25. |

`data`: a list of `source_kind` (`c` creature, `g` object, `i` container item), `source_id`, `name`, `path`, `windows` (the corpses or openings read for the first time), `items`, `characters`, `level_min`, `level_max`, `zone` (`id`, `name`) and `top` (`item_id`, `item` and the `estimate` (`k` windows that held the item of `n` windows read for the first time, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 windows, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10 windows) of the item that was in most of them). `meta.view` and `meta.next_cursor`.

Example: `curl https://<host>/api/v1/public/loot/sources`

#### `GET /public/loot/items`

The items that dropped from creatures, objects and containers, one row for each: how many times, from how many sources, the source with the best rate and the zone the item dropped most in. Every ruleset and every loot rule but personal loot. Fishing is not here.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `zone` | query | integer | A zone id from `/zones/{id}`. Leave out for every zone. |
| `quality` | query | integer | The lowest item quality, 0 poor to 5 legendary. |
| `q` | query | string | Text that is in the name of the item, or its id. 2 to 80 characters. |
| `sort` | query | string | `windows` (default) or `items`: items by how often they dropped or from how many sources. |
| `cursor` | query | string | The `next_cursor` of the previous page. |
| `limit` | query | integer | Rows per page, 1 to 100. Default 25. |

`data`: a list of `item_id`, `name`, `quality`, `path`, `dropped` (the corpses and openings that held the item, once each), `sources`, `best` (`source_kind`, `source_id`, `source`, `path` and the `estimate` (`k` windows that held the item of `n` windows read for the first time, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 windows, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10 windows) at the source where the low end of the range is highest) and `zone`. `meta.next_cursor`.

Example: `curl https://<host>/api/v1/public/loot/items`

#### `GET /public/loot/groups`

What loot rules and group size change in loot: the items a corpse held under two loot rules, what the looter kept, the share of group loot each member received, rolls, kills an hour by group size and group composition. Every ruleset; personal loot is in no comparison.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `source` | query | string | A creature or object as its kind and id, `c255851` or `g649094`. Narrows what a corpse held and what the looter kept to it; the other parts cover every creature. |
| `a` | query | string | The first loot rule to compare: `group`, `freeforall`, `roundrobin`, `needbeforegreed`, `masterlooter` or `solo`. Leave out with `b` for the two with the most corpses on creatures seen under both. |
| `b` | query | string | The second loot rule to compare, as `a`. |
| `marks` | query | boolean | `true` adds `marks`. |

`data`: `source` (`null`, or `source_kind`, `source_id`, `name`, `path`), `figures` (`corpses`, `rules`, `largest`, `characters`, `first_at`, `last_at`, `several`, the creatures seen under two rules, `rolls` and `groups`), `effects` (`seen`, the rules with `windows` and `sources`, `pair`, the two compared, `rows` of `rule`, `items` per corpse and `corpses`, `sources` and `windows` the difference rests on, `difference` of `difference`, `low` and `high` in items per corpse or `null`, and `more`), `kept` (rows of `rule`, `size`, `corpses`, `items`, `received` and `other`, true for a size seen only on creatures no other size has), `share` (rows of `rule`, `size`, `yours`, `others`, `equal_share` and the `estimate` of yours over both, unit `receipt`), `rolls` (`choices` of `rule`, `quality`, `rolls` and `need`, `greed` and `pass` as an `estimate` with unit `roll`, and `thresholds` of `rule`, `threshold`, `corpses`, `items` and `per_100_corpses`), `kills_by_size` (`size`, `kills`, `hours` and `per_hour`), `composition` (`need` of `class`, `armor`, `rolls` and the need `estimate`, `skins` of `uploader_skins`, `looted`, `skinned` and `per_corpse`, and `roles` of `tank`, `healer`, `kills`, `hours` and `per_hour`) and `marks`. An `estimate` has `k`, `n`, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10. `per_hour`, `per_corpse` and `per_100_corpses` are `null` under 10 kills or corpses. `marks` is `null` unless asked for, else every marked difference between two loot rules or two group sizes of one rule: `source_kind`, `source_id`, `name`, `path`, `split` (`rule` or `size`), `rule` (the rule a size split reads), `item_id`, `item`, `arms` (the two compared, each with `key`, `label`, `windows`, `corpses`, `characters`, `flags`, `drawn` and the item's `estimate`) and `difference` (`difference`, `low` and `high` as shares of the arms, `marked` and `flags`); a mark is a counting fault until shown otherwise, since the game's drop table depends on neither.

Example: `curl https://<host>/api/v1/public/loot/groups`

### Map

#### `GET /public/map/heat`

Heat on the world grid: the cells where characters walked, died or met creatures, each with a level from 1 to 4.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `layer` | query | string | `paths`, `deaths` or `creatures`. Default `paths`. |
| `creature` | query | integer | A creature id: narrows `creatures` to one creature. |

`data.cells`: `i`, `j`, `level`, `value`, `characters`. `meta.grid` gives the grid size.

Example: `curl https://<host>/api/v1/public/map/heat?layer=deaths`

#### `GET /public/map/density`

One whole level of the heat grid, for drawing a density surface. Level 0 is 192 by 128 cells; each level doubles both.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `layer` | query | string | `paths`, `deaths` or `creatures`. Default `paths`. |
| `level` | query | integer | 0 to 3 for `paths`, 0 to 2 for the other layers. Default 0. |
| `creature` | query | integer | A creature id: narrows `creatures` to one creature. |

`data`: `level`, `columns`, `rows` and `cells`, a flat list of pairs: the cell index (`j * columns + i`) minus the previous
cell's, then the cell's counter. Cells are in index order; a cell that too few characters contributed to is left out.

Example: `curl https://<host>/api/v1/public/map/density?layer=paths&level=2`

