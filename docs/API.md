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
