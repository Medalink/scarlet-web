# Upload API, version 1

The server side of the upload protocol in [DESIGN.md](DESIGN.md), "Upload protocol" and "Security". This page is
exact enough to write the client from. Paths are under `https://<host>/api/v1`. Every answer is JSON.

A character name is `Name-Surname`: two parts joined by one `-`, each 1 to 40 characters with no whitespace, `-`, `/`
or `\`.

## Signing a request

Every request except `POST /installs` carries three headers:

| Header | Value |
| --- | --- |
| `Scarlet-Install` | The install id (a UUID) that `POST /installs` returned |
| `Scarlet-Time` | The client's clock, epoch seconds, as decimal digits |
| `Scarlet-Signature` | Base64 (standard alphabet, padded) of the 64-byte Ed25519 signature of the message below |

The message is four lines joined by `\n` (no trailing newline), as bytes:

```
<METHOD>
<path>
<Scarlet-Time>
<lower-case hex SHA-256 of the exact request body bytes>
```

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
message (text)    POST\n/api/v1/upload/sync\n1795000000\n4f8d14bff65a243f4d1f0e8895acd487258887b01e63e919f213df5308acc78a
message (hex)     504f53540a2f6170692f76312f75706c6f61642f73796e630a313739353030303030300a34663864313462666636356132343366346431663065383839356163643438373235383838376230316536336539313966323133646635333038616363373861
signature         QmH8q4EUZ2Wq6uLD2+HUPIxtoy9455BNMq5NeXuTuAAJB3yEcF8dDdwUkHdDZ/MNKb2Hg6dDTOxUM2JHEaofDw==
```

The request is

```
POST /api/v1/upload/sync HTTP/1.1
Content-Type: application/json
Scarlet-Install: <install id>
Scarlet-Time: 1795000000
Scarlet-Signature: QmH8q4EUZ2Wq6uLD2+HUPIxtoy9455BNMq5NeXuTuAAJB3yEcF8dDdwUkHdDZ/MNKb2Hg6dDTOxUM2JHEaofDw==
```

A request with no body, `GET /api/v1/upload/status` at the same time, signs
`GET\n/api/v1/upload/status\n1795000000\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` and gets

```
UZdN1au5Ip2O+n8j1IiJEUtREPSm9x7G8svEp12A/zG/OrRe6OacH4w8YxjMYJzjs2YHjWq31cFaOlkatH8XCQ==
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

### `POST /installs/link-code`

No body. `201` with `{"code": "XXXX-XXXX", "expires_at": "...", "expires_in": 600}`. The app shows the code and opens
`/link?code=XXXX-XXXX` in the browser. A code works once. An install that is already linked gets `409 already_linked`.

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
`removed` is how many parts it held in the groups that are on and the manifest no longer lists; they and their decoded
rows are gone. Parts of a group that is switched off in `groups` stay until the app removes the group (below).

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
  characters (leaderboards, camps, search and characters). Send the tag back as `If-None-Match`: an unchanged answer is `304`
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

The boards: fastest to level 20, 40 and 60, highest level without dying, most quests turned in, most quests completed, most kills, most dungeon runs, most distance and most time by the fire.

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

Characters (only those made public), creatures, items, zones and quests whose name holds every word of the text, and the site's own pages. Best match first: exact, prefix, word, contains, then a close spelling when nothing else matched.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `q` | query | string | The text to search for, 2 to 80 characters. |
| `kind` | query | string | `characters`, `creatures`, `items`, `zones` or `quests`. Leave out for the first hits of every kind and the pages. |
| `cursor` | query | string | The `next_cursor` of the previous page. |
| `limit` | query | integer | Rows per page, 1 to 100. Default 25. |

Without `kind`: `data` has one entry per kind and a sixth, `pages`, each with `count` and `hits`. With `kind`: `data` is the list of hits, paged with `meta.next_cursor`. A hit has `kind`, `id`, `name`, `path`, `match` (`exact`, `prefix`, `word`, `contains` or `fuzzy`) and the numbers of its kind; a text of digits also finds the creature, item, quest or zone with that id as an `exact` hit. A page hit has `title`, `path`, `meta` and `match`.

Example: `curl https://<host>/api/v1/public/search?q=ash&kind=zones`

### Characters

#### `GET /public/characters/{name}`

A public character: race, class, level, ruleset, time played, this week, distance walked, feats and standings. A character that is not public answers 404.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `name` | path | string | The character name, `Name-Surname`. |

`data`: `name`, `race`, `class`, `faction`, `level`, `played_seconds` (the total the game reports, `null` until Scarlet has read it), `recorded_seconds` (play Scarlet counted), `first_level`, `recorded_since`, `whole_life` (recorded from level 1), `ruleset`, `last_seen_at`, `week`, `distance`, `feats` (every feat that applies to the character: those earned first, rarest first, then those not yet earned, closest first; `pinned` is true for a feat the owner pinned, which keeps its place in the list. `key` is one of `summit`, `veteran`, `devoted`, `steadfast`, `seasoned`, `regular`, `long-haul`, `longstanding`, `unbroken`, `slayer`, `bestiary`, `finisher`, `gladiator`, `rampage`, `fallen`, `flattened`, `nemesis`, `territorial`, `roamer`, `questing`, `errand-runner`, `fickle`, `cartographer`, `local-hero`, `thorough`, `delver`, `vanquisher`, `spelunker`, `raider`, `conqueror`, `deep-dweller`, `flawless`, `familiar`, `soloist`, `full-house`, `explorer`, `globetrotter`, `local`, `wayfarer`, `jumper`, `swimmer`, `runner`, `rider`, `stroller`, `flyer`, `tracker`, `scout`, `witness`, `naturalist`, `skullseer`, `looter`, `scavenger`, `lucky`, `collector`, `jackpot`, `curator`, `unboxer`, `angler`, `fishmonger`, `wading`, `gatherer`, `forager`, `artisan`, `master`, `practised`, `crafter`, `repertoire`, `earner`, `spender`, `tycoon`, `windfall`, `expressive`, `mime`, `signature`, `indecisive`; the gold feats `earner`, `spender`, `tycoon` and `windfall` are listed only when `meta.economy` is true; `name`, `value`, `tier` from 0 for none to 5 for legend, with `tier_name` one of `Copper`, `Silver`, `Gold`, `Scarlet` or `Legend`, `threshold` of the tier held and `next`, the threshold of the tier above, `null` at the top; `artisan` counts the sum of the character's profession ranks), `standings` (a list, best place first, of the boards the character is on among public characters of every ruleset: `board`, `title`, `place`, `total` and `value`). `meta.economy` says whether the owner shows money.

Example: `curl https://<host>/api/v1/public/characters/Tolvir-Greane`

#### `GET /public/characters/{name}/series/{series}`

One series of a public character: `levelling` (played time before each level, with the ruleset median and middle half), `xp`, `deaths`, `dungeons`, `activity`, `sessions`, and `gold` when the owner shows money.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `name` | path | string | The character name. |
| `series` | path | string | The series name. |

`data`: the rows of the series.

Example: `curl https://<host>/api/v1/public/characters/Tolvir-Greane/series/levelling`

### Records

#### `GET /public/creatures/{id}`

One creature: its level range and kind, kills, deaths to it, drops under each loot rule and the world cells where it was met.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The creature id, as in the address of its page. |
| `ruleset` | query | string | A ruleset slug from `/rulesets`. Leave out for all rulesets. |
| `server` | query | string | `all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given. |

`data`: `facts`, `kills`, `deaths`, `deaths_by_level`, `drops` (item, loot rule, drops, opens, rate) and `cells`. Drop rates cover every ruleset.

Example: `curl https://<host>/api/v1/public/creatures/257926`

#### `GET /public/items/{id}`

One item: what it is, where it drops by source and loot rule, the boss encounters that dropped it, the recipes that make or use it, and its auction prices.

| Parameter | In | Type | Meaning |
| --- | --- | --- | --- |
| `id` | path | integer | The item id, as in the address of its page. |

`data`: `item`, `sources` (source, loot rule, drops, opens, rate), `prices` (one entry for each ruleset and realm: `ruleset`, `realm`, and `days` of `day`, `price` in copper and `browse`), `dropped`, `source_count`, `best_rate`, `first_at`, `encounters` (encounter, item, dropped, characters), `made_by` and `used_in` (recipes: spell, output, reagents). `item` holds the item's facts (class, equip slot, levels, sell price, bind type, stack, quality) and is `null` while no upload has carried them (an `II` line): an item known only by name and drops, such as one seen in a loot line, still answers with `name` and `sources`.

Example: `curl https://<host>/api/v1/public/items/1465`

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

