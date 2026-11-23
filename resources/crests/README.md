# Feat crests

`kit.json` is the one source of the crest art for the website, the app and the addon: the shape of every piece,
the colour of every tier and material, and which tier attaches which piece. Each surface reads it; none keeps its
own copy of these numbers.

## What the kit holds

| Key | What it is |
| --- | --- |
| `box`, `center`, `viewBox` | The 96 by 96 drawing box, its centre, and the view boxes of a full and a compact crest |
| `tierNames` | The six tier names, 0 (not earned) to 5 |
| `attaches` | The piece each tier adds: `shield`, `crown`, `banner`, `side`, `rays` |
| `scopes` | For `character`, `class` and `account`: its shield, its side piece, its ring (account) and where the glyph sits |
| `quality` | Per tier: the accent `color`, the field's foot `deep`, the material of `frame`, `crown`, `banner`, `side` and `rays`, field opacities, glyph and gem colours, ghost opacities |
| `material` | `steel`, `pewter`, `parchment`, `gold`: a vertical gradient (`top`, `bottom`) and a `flat` colour where a gradient cannot be drawn |
| `band`, `gem` | The coloured band across the banner, and where the gem sits and how far its setting grows |
| `ink`, `light`, `nearAt`, `unearned`, `glyphSize` | The gap colour, the light glyph colour, where a ghost turns bright, the not-earned opacities, the glyph size |
| `pieces` | Each piece: its SVG path `d` in the 96 box, its bounding `box`, the way its progress ghost fills (`up`, `right`, `out`) and `back` when it sits on an ink backing |

## Swap the crests

New shapes, colours or materials, with the same pieces:

1. Edit `kit.json`. Keep every key; a path is any SVG path in the 96 by 96 box.
2. Website: `bin/artisan crests:kit` writes `app/View/Sc/CrestKit.php`.
3. App (scarlet-app): `node scripts/crests-gen.mjs` writes `app/ui/crest-kit.js` from this file.
4. Addon (scarlet): `node tools/media/build.mjs` writes `Media/Crests.tga` and `Tiers.lua`'s generated tables from this file.
5. Look at the website's `/design/system`, the app's component sheet and the addon render, then ship the three repos.

A different kind of art, such as painted images or a new set of pieces, replaces the drawer behind one interface in
each repo and leaves every page that shows a crest alone:

| Repo | Drawer | Interface |
| --- | --- | --- |
| scarlet-web | `app/View/Sc/Crest.php`, `<x-crest>` | `Crest::svg($glyph, $scope, $tier, $progress, $size, $label, $state, $icon)` |
| scarlet-app | `app/ui/crests.js` | `drawCrest(el)` on an element with `data-crest`, `data-scope`, `data-tier`, `data-progress`, `data-size`, `data-state`, `data-label`, `data-icon` |
| scarlet | `addon/Scarlet/Crest.lua` | `ns.UI.Crest(parent, size)`, then `:Set(scope, glyph, tier, progress, spell, state)`, `:Attach()`, `:Reveal()` |
