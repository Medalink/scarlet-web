export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 12.2, targetY: 1.25, sway: 0.16, offset: { wide: 0.2, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#2a2f40', radius: 60 },
        sun: { from: [-0.5, 0.55, 0.6], colour: 0x9cc0ff, power: 4.8, sky: 0x4a62a6, floor: 0x121830, fill: 3.0 },
        glows: [
            { at: [0.6, 0.6], lift: 1.8, colour: 0x9d6bff, power: 26, reach: 14, flicker: 0.1 },
        ],
    },
    props: [
        { key: 'portal', model: 'portal', at: [0.6, 1.4], scale: 1.05, turn: -1.5, anchor: [0, 3.2, 0] },
        { key: 'sheep', model: 'sheep', at: [-1.7, 0.2], scale: 1.5, turn: -1.4, anchor: [0, 1.5, 0] },
        { key: 'bottles', model: 'alchemy', at: [2.6, -0.5], scale: 0.8, turn: -2.0, anchor: [0, 2.0, 0] },
        { key: 'books', model: 'bookshelf', at: [-3.9, 3.4], scale: 0.6, turn: -1.2, anchor: [0, 2.6, 0] },
    ],
};
