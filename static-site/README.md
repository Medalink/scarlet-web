# Scarlet website

The landing page and a demo character overview for [Scarlet](https://github.com/Medalink/scarlet), the WoW Forever addon that records what you do in game.

Plain HTML, CSS and JavaScript with no build step. GitHub Pages serves this folder from `main`.

- `index.html`: what Scarlet records, the event codes, what it never records and the capture map.
- `character.html`: the character overview, drawn from invented demo data.
- `assets/format-data.js` is generated: `node tools/build-data.mjs <path to a scarlet checkout>` rebuilds it from `docs/FORMAT.md` and `docs/api-map.json`.

Every character, place, creature and number on the site is invented.

## License

GPL-3.0. See `LICENSE`.
