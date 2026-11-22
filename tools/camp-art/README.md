# Camp art

The art of the `/camps` page: the game's own Basic Campfire, Camp Tent and Camp Chair (geometry, textures, particle
emitters, lights) and the nine Camp Benefits icons. It is Blizzard's art, so it is never committed: it lives on the
`maps` disk under `camp/` and reaches the server with the map tiles.

## Needs

- Node 24. The tool has no dependencies.
- A local file server for an installed WoW Forever client (build 1.60.1) that answers two paths: `/casc/fdid?fileDataID=<id>&filename=<id>`
  (the raw file) and `/casc/blp2png?fileDataID=<id>` (a texture as PNG). The default address is `http://127.0.0.1:5123`.
- The ids are in `build.mjs`: the three model file ids come from the game objects the camp spells place, and each
  icon file id is the benefit spell's `SpellMisc.SpellIconFileDataID`. `m2.mjs` reads the M2 layout of client
  version 272.

## Command

```sh
node tools/camp-art/build.mjs storage/app/maps/camp [server address]
```

## Output

```
camp/camp.json            the three models: batches, bones, lights and particle emitters, with the texture file ids
camp/tex/<file id>.png    one PNG per texture (about 1 MB in all)
camp/icons/<spell id>.png the nine benefit icons, named by spell id
```

About 1.2 MB. `storage/app/maps` is ignored by git. The site serves the files at `/camp-art/camp.json`,
`/camp-art/tex/<id>.png` and `/camp-art/icons/<spell>.png` and shows the page's art only while `camp/camp.json`
exists; without it the head is a plain page head and the benefit icons are empty squares.

## To the server

`bin/deploy maps storage/app/maps` copies the whole maps folder, `camp/` included, to `/srv/scarlet-web/maps` on
the droplet, where the app reads it as its `maps` disk. A new build changes `camp.json`; the page puts that file's
modified time on every art address, so a changed build is live within five minutes and the old files stay cached
under their old addresses.
