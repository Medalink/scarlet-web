# Scene art

The art of the camps page's, the profession pages' and the class pages' heads: each scene's own models from the game (geometry, textures,
particle emitters, lights) and the icons its page shows, and the icon of every item and every spell. It is Blizzard's art, so it is never committed: it lives on the
`maps` disk under the scene's name and reaches the server with the map tiles. The `camp` scene is the Basic Campfire,
Camp Tent and Camp Chair and the nine Camp Benefits icons, named by spell id (`SpellMisc.SpellIconFileDataID`).

## Needs

- Node 24. The tool has no dependencies.
- A local file server for an installed WoW Forever client (build 1.60.1) that answers `/casc/fdid?fileDataID=<id>&filename=<id>`
  (the raw file) and `/casc/blp2png?fileDataID=<id>` (a texture as PNG), and for `items.mjs` also
  `/dbc/export/csv?name=Item&build=1.60.1.71037` (the client's `Item` table), `spells.mjs` also
  `/dbc/export/csv?name=SpellMisc&build=1.60.1.71037` (the `SpellMisc` table), and a scene with a creature also
  `/dbc/peek/<table>?build=1.60.1.71037&col=<column>&val=<value>` (one row of a table as JSON). The default address is `http://127.0.0.1:5123`.
- The ids are in the `SCENES` table of `scenes.mjs`: each scene lists its models by key (the keys the scene file
  in `resources/js/scene/scenes` places), each as a file id or, for a creature, as `{ file, display }` and its icon file ids by the id the page asks for (only `camp` has icons, named by
  spell id). `m2.mjs` reads the M2 layout of client version 272.

## Command

```sh
node tools/scene-art/build.mjs fishing storage/app/maps/fishing [server address]
```

The scenes are `camp`, the twelve professions by slug (`fishing`, `herbalism`, `mining`, `skinning`, `alchemy`, `blacksmithing`,
`enchanting`, `engineering`, `leatherworking`, `tailoring`, `cooking`, `first-aid`) and the nine classes by slug (`warrior`, `paladin`,
`hunter`, `rogue`, `priest`, `shaman`, `mage`, `warlock`, `druid`); each goes to a folder of its own name.

### Creature skins

A creature's model file leaves its skin to the creature display: its textures of type 11, 12 and 13 are empty, and the
display's row in `CreatureDisplayInfo` lists the three files that fill them (`TextureVariationFileDataID[0]` to `[2]`). A
model entry of `{ file: 124622, display: 4449 }` makes `build.mjs` read that row, check through `CreatureModelData` that
the display belongs to the model, write the three files into the model's texture ids and then read the model, so its
batches name textures that exist. The built model carries `display`, which the art gate compares. The models stand in
their bind pose: the scene module draws no skeleton animation.

## Output

```
fishing/fishing.json    the five models: batches, bones, lights and particle emitters, with the texture file ids
fishing/tex/<file id>.png  one PNG per texture
```

`storage/app/maps` is ignored by git. The site serves the files at `/scene-art/fishing/models.json` and
`/scene-art/fishing/tex/<id>.png` (the camp's icons at `/scene-art/camp/icons/<spell id>.png`) and shows a scene only while
`fishing/fishing.json` exists; without it the head is a plain page head.

## Item icons

```sh
curl -o Item.csv "http://127.0.0.1:5123/dbc/export/csv?name=Item&build=1.60.1.71037"
node tools/scene-art/items.mjs Item.csv storage/app/maps/items [server address]
```

It reads the `ID` and `IconFileDataID` columns of the `Item` table and writes, once for every profession page:

```
items/items.json         item id to icon file id, ids only
items/icons/<file id>.png  one PNG per icon file, shared by every item that uses it
```

An icon file already in the folder is not fetched again, so a second run is cheap. A file the server cannot give is
skipped and counted, and the items that use it are left out of `items.json`; the next run tries it again. A run that
changes nothing leaves `items.json` alone, so the addresses the site hands out stay the same. For build 1.60.1.71037
30,347 items share 3,376 icon files. The site serves `/item-icons/<file id>.png` and looks an
item up in `items.json` through `App\Support\ItemIcons`; the item tables show an icon only while that file exists.

## Spell icons

```sh
curl -o SpellMisc.csv "http://127.0.0.1:5123/dbc/export/csv?name=SpellMisc&build=1.60.1.71037"
node tools/scene-art/spells.mjs SpellMisc.csv storage/app/maps/spells [server address]
```

It reads the `SpellID` and `SpellIconFileDataID` columns of `SpellMisc` and writes, as `items.mjs` does for items:

```
spells/spells.json         spell id to icon file id, ids only
spells/icons/<file id>.png   one PNG per icon file, shared by every spell that uses it
```

`SpellMisc` has more than one row for some spells; the first row with an icon wins. For build 1.60.1.71037 31,836 spells share
2,342 icon files (10 MiB), and 25 files the client cannot give are skipped, which leaves their spells out of `spells.json`.
The site serves `/spell-icons/<file id>.png` and looks a spell up in `spells.json` through `AppSupportSpellIcons`; a
spell row shows an icon only while that file exists, and an empty square otherwise. The two folders stay apart, since the
`items/` and `spells/` maps are each published whole; an icon file that is both an item's and a spell's is stored in
both. `items.mjs` and `spells.mjs` share `icon-store.mjs`.

## To the server

`bin/deploy maps storage/app/maps` adds the whole maps folder, every scene's folder, `items/` and `spells/` included, to the art
store on medaware and sends it on to `/srv/scarlet-web/maps` on the droplet, where the app reads it as its `maps` disk
(docs/HOSTING.md, Map art). Run it before you ship a change to `scenes.mjs`: `ship` refuses the commit while the store
lacks a model or an icon of the table. A new build changes `<scene>.json`;
the page puts that file's modified time on every art address, so a changed build is live within five minutes and the
old files stay cached under their old addresses.
