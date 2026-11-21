<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Map viewer</title>
    <style>
        :root { color-scheme: dark; --bg: #141416; --fg: #f0f1f3; --line: rgba(255, 255, 255, 0.12); --accent: #ff2d55; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; background: var(--bg); color: var(--fg); font: 14px/1.4 "Sofia Sans", system-ui, sans-serif; }
        #map-viewer { position: fixed; inset: 0; }
        #map { display: block; width: 100%; height: 100%; }
        #map:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
        .bar { position: absolute; top: 12px; left: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .seg { display: inline-flex; border: 1px solid var(--line); border-radius: 6px; overflow: hidden; background: rgba(20, 20, 22, 0.85); }
        button { font: inherit; color: inherit; background: none; border: 0; padding: 6px 12px; cursor: pointer; }
        .seg button[aria-pressed="true"] { background: var(--accent); color: #fff; }
        .bar > button { border: 1px solid var(--line); border-radius: 6px; background: rgba(20, 20, 22, 0.85); }
        .world-zoom { position: absolute; right: 12px; top: 12px; display: grid; gap: 6px; }
        .iconbtn { width: 36px; height: 36px; padding: 0; font-size: 20px; border: 1px solid var(--line); border-radius: 6px; background: rgba(20, 20, 22, 0.85); }
        .world-credit { position: absolute; left: 12px; bottom: 10px; margin: 0; font-size: 12px; color: rgba(240, 241, 243, 0.7); text-shadow: 0 0 4px #141416; }
        .world-credit a { color: inherit; }
        #readout { position: absolute; right: 12px; bottom: 10px; font: 12px "Martian Mono", ui-monospace, monospace; white-space: pre; text-shadow: 0 0 4px #141416; }
    </style>
</head>
<body>
    <div id="map-viewer" data-zones="{{ json_encode($zones) }}">
        <canvas id="map" aria-label="The world map with invented heat, marks and a path. Drag to move, scroll to zoom."></canvas>
        <div class="bar">
            <div id="looks" class="seg"></div>
            <button type="button" data-fit="world">World</button>
            <button type="button" data-fit="1411">Durotar</button>
            <button type="button" data-fit="1426">Dun Morogh</button>
        </div>
        <div id="readout"></div>
    </div>
    @vite(['resources/js/map/design.js'])
</body>
</html>
