#!/usr/bin/env node
// Writes every spell's icon from an installed WoW Forever client: `spells.json` (spell id to icon file id) and one PNG per
// icon file. Reads the client through the local file server (README.md). The output is Blizzard's art: it goes on the
// maps disk, never into git.
// Usage: node tools/scene-art/spells.mjs <SpellMisc table as CSV> <out dir> [server]
import { readIcons, storeIcons } from './icon-store.mjs';

const [csvPath, out, server = 'http://127.0.0.1:5123'] = process.argv.slice(2);
if (!csvPath || !out) {
    console.error('usage: node tools/scene-art/spells.mjs <SpellMisc table as CSV> <out dir> [server]');
    process.exit(2);
}

await storeIcons(readIcons(csvPath, 'SpellID', 'SpellIconFileDataID'), out, 'spells.json', 'spells', server);
