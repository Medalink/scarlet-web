# Page comps

The design of every page of the web app, as static HTML with invented names and numbers. Open a file in a browser.
A page is built from its comp and reviewed against it: same markup classes, same order of modules, same copy style.

- `assets/site.css` is the Scarlet website's stylesheet, unchanged. `assets/app.css` is what the app pages add.
  Both ship in the app as they are; page work adds rules to `app.css` and never restyles a class `site.css` owns.
- `assets/comp.js` draws the charts with invented numbers. It is the spec for the chart module: the same marks
  (hairline grid, mono axis labels, a scarlet median line with a band for the middle half, white for a second
  series, the heat palette) drawn from API data. `assets/map.js` is the site's map drawing.
- `build.mjs` writes the HTML files: `node docs/comps/build.mjs docs/comps`.

| Comp | Page |
| --- | --- |
| `stats.html` | `/stats` and, with a ruleset chosen in the filters, `/rulesets/{slug}` |
| `camps.html` | `/camps`. The head is a still: the page draws the game's campfire, tent and chair there, and the comp holds no game art |
| `fishing.html` | `/professions/{slug}` for the four gathering professions. The comp is Fishing; Herbalism, Mining and Skinning use the same modules with their own words (a pick, a strike, a skin) and leave out the modules that are fishing only. The head is a still: the page draws the profession's scene there |
| `cooking.html` | `/professions/{slug}` for the eight crafting professions. The comp is Cooking; the others use the same modules and leave out the ones they have no rows for. The head is a still, and there is no map module |
| `rulesets.html` | `/rulesets` |
| `leaderboards.html` | `/leaderboards` |
| `search.html` | `/search` |
| `character.html` | `/c/{name}`: the head, the owner's bar and the two modules the web app adds, and, above "This week", the class band that links to `/c/{name}/{class}`. The rest is the site's `character.html` as it is |
| `class.html` | `/c/{name}/{class}`. The comp is the Warlock; every class uses the same modules with its own figures. The head is a still and the comp holds no game art. This comp links the shipped sheets (`resources/css/site.css`, `app.css`, `game.css`) and `assets/class.css`, which is what the page adds to them |
| `creature.html`, `item.html`, `zone.html`, `quest.html` | the result pages |
| `loot.html`, `object.html`, `container.html` | `/loot`, the loot index, `/objects/{id}`, and the What it holds module of a container item |
| `world.html` | `/map`, the world map. The comp draws the invented sheet; the built page draws the tile pyramid of DESIGN.md "Maps" |
| `account.html` | `/account` |
| `login.html`, `link.html` | `/login`, `/link` |
| `developers.html` | `/developers` |

Rules that hold on every page: one `h1` naming the thing; a line of facts under it; the figure strip; then modules on
the 12-column sheet divided by hairlines. Headings say what the chart shows. No helper text that restates a heading.
A bucket with too few characters says "Too few characters" in place of a number. Empty modules say what is missing.
The landing page says uploading is optional and off until the player turns it on.
