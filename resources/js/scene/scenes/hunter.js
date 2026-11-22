export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 10.0, targetY: 1.25, sway: 0.16, offset: { wide: 0.18, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#454b38', radius: 60 },
        sun: { from: [-0.85, 0.35, 0.5], colour: 0xe8c87a, power: 5.0, sky: 0x9aa470, floor: 0x22251a, fill: 2.6 },
        glows: [
            { at: [-0.3, 2.4], lift: 0.5, colour: 0xff9a3d, power: 12, reach: 7, flicker: 0.35 },
            { at: [5, -4], lift: 0.2, colour: 0xffd98a, power: 26, reach: 24, flicker: 0.05 },
        ],
    },
    props: [
        { key: 'campfire', model: 'campfire', at: [-0.3, 2.4], lift: 0.8, scale: 1.15 },
        { key: 'cat', model: 'cat', at: [1.7, 1.5], scale: 0.75, turn: -1.3, anchor: [0, 1.35, 0] },
        { key: 'wolf', model: 'wolf', at: [-0.8, 0.6], scale: 0.68, turn: -1.7, anchor: [0, 1.35, 0] },
        { key: 'boar', model: 'boar', at: [3, -1.2], scale: 0.55, turn: -2.0, anchor: [0, 1.2, 0] },
        { key: 'owl', model: 'owl', at: [-3, 1.8], scale: 0.8, turn: -1.1, anchor: [0, 1.0, 0] },
    ],
};
