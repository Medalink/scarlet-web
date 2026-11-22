#!/usr/bin/env node
// Writes every item's icon from an installed WoW Forever client: `items.json` (item id to icon file id) and one PNG per
// icon file. Reads the client through the local file server (README.md). The output is Blizzard's art: it goes on the
// maps disk, never into git.
// Usage: node tools/scene-art/items.mjs <Item table as CSV> <out dir> [server]
import { readIcons, storeIcons } from './icon-store.mjs';

const [csvPath, out, server = 'http://127.0.0.1:5123'] = process.argv.slice(2);
if (!csvPath || !out) {
    console.error('usage: node tools/scene-art/items.mjs <Item table as CSV> <out dir> [server]');
    process.exit(2);
}

await storeIcons(readIcons(csvPath, 'ID', 'IconFileDataID'), out, 'items.json', 'items', server);
