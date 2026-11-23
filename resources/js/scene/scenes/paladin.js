export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 11.2, targetY: 1.25, sway: 0.16, offset: { wide: 0.2, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#6a5a40', radius: 60 },
        sun: { from: [-0.7, 0.4, 0.55], colour: 0xf6c97a, power: 7.5, sky: 0xb08a5a, floor: 0x3a2e1e, fill: 3.6 },
        glows: [
            { at: [-1.2, 1.8], lift: 0.6, colour: 0xffd88a, power: 9, reach: 8, flicker: 0.12 },
        ],
    },
    props: [
        { key: 'banner', model: 'banner', at: [3.3, 2.8], scale: 0.42, turn: -1.4, anchor: [0, 2.71, 0] },
        { key: 'podium', model: 'podium', at: [-3.2, 3.0], scale: 1.2, turn: -1.2, anchor: [0, 1.77, 0] },
        { key: 'warhorse', model: 'warhorse', at: [-0.7, -0.5], scale: 0.95, turn: -1.05, anchor: [0, 2.37, 0] },
        { key: 'shieldrack', model: 'shieldrack', at: [1.8, -1.8], scale: 1.4, turn: -1.7, anchor: [-0.75, 1.4, 0.4] },
    ],
};
