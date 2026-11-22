export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#4a433c', radius: 60 },
        sun: { from: [-0.6, 0.6, 0.5], colour: 0xa9b8dc, power: 4.4, sky: 0x7888b4, floor: 0x1c1a1e, fill: 2.5 },
        glows: [
            { at: [4.2, 4.4], lift: 1.2, colour: 0xff8a3c, power: 30, reach: 16, flicker: 0.25 },
            { at: [-6.6, 4.8], lift: 1.6, colour: 0xff6a2c, power: 14, reach: 10, flicker: 0.2 },
        ],
    },
    props: [
        { key: 'station', model: 'anvil', at: [0.4, 0], scale: 1.3, turn: 0.6, anchor: [0, 2.1, 0] },
        { key: 'hammer', model: 'hammer', at: [0.4, 0], lift: 1.78, scale: 1.3, turn: 1.2 },
        { key: 'work', model: 'forge', at: [5, 5.6], scale: 0.62, turn: 2.4, anchor: [0, 4.3, 0] },
        { key: 'furnace', model: 'bellows', at: [-7, 5.6], scale: 0.33, turn: 1 },
        { key: 'grind', model: 'grind', at: [3.3, -1.1], scale: 1, turn: 2 },
        { key: 'rack', model: 'rack', at: [2.9, 2.2], scale: 1.1, turn: 2.4 },
        { key: 'stock', model: 'workbench', at: [-2.2, -1], scale: 1, turn: 0.9, anchor: [0, 1.6, 0] },
        { key: 'iron', model: 'iron', at: [-2.2, -1], lift: 0.87, turn: 0.9 },
        { key: 'copper', model: 'copper', at: [-1.7, -1.7], lift: 0.87, turn: 1.3 },
        { key: 'mithril', model: 'mithril', at: [-2.7, -0.3], lift: 0.87, turn: 0.5 },
    ],
};
