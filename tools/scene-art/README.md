# Scene art

The art of the profession pages' heads: each scene's own models from the game (geometry, textures, particle emitters)
and the icons its page shows. It is Blizzard's art, so it is never committed: it lives on the `maps` disk under the
scene's name and reaches the server with the map tiles. The camp page has its own tool, `tools/camp-art`; this one
reads models with the same `m2.mjs`.

## Needs

- Node 24. The tool has no dependencies.
- A local file server for an installed WoW Forever client (build 1.60.1) that answers two paths: `/casc/fdid?fileDataID=<id>&filename=<id>`
  (the raw file) and `/casc/blp2png?fileDataID=<id>` (a texture as PNG). The default address is `http://127.0.0.1:5123`.
- The ids are in the `SCENES` table of `build.mjs`: each scene lists its model file ids by key (the keys the scene file
  in `resources/js/scene/scenes` places) and its icon file ids by the id the page asks for. Fishing's icon ids are item
  ids. `../camp-art/m2.mjs` reads the M2 layout of client version 272.

## Command

```sh
node tools/scene-art/build.mjs fishing storage/app/maps/fishing [server address]
```

The scenes are `fishing`, `herbalism`, `mining` and `skinning`; each goes to a folder of its own name.

## Output

```
fishing/fishing.json    the five models: batches, bones, lights and particle emitters, with the texture file ids
fishing/tex/<file id>.png  one PNG per texture
fishing/icons/<id>.png     the nine catch icons, named by item id
```

`storage/app/maps` is ignored by git. The site serves the files at `/scene-art/fishing/models.json`,
`/scene-art/fishing/tex/<id>.png` and `/scene-art/fishing/icons/<id>.png` and shows a scene only while
`fishing/fishing.json` exists; without it the head is a plain page head.

## To the server

`bin/deploy maps storage/app/maps` copies the whole maps folder, every scene's folder included, to
`/srv/scarlet-web/maps` on the droplet, where the app reads it as its `maps` disk. A new build changes `<scene>.json`;
the page puts that file's modified time on every art address, so a changed build is live within five minutes and the
old files stay cached under their old addresses.
