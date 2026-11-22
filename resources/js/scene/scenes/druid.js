export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 11.2, targetY: 1.25, sway: 0.16, offset: { wide: 0.1, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#2c4230', radius: 60 },
        sun: { from: [-0.5, 0.55, 0.6], colour: 0x9db8e8, power: 4.6, sky: 0x4a6a9a, floor: 0x161d22, fill: 3.4 },
        glows: [
            { at: [0.4, 2.6], lift: 1.6, colour: 0x8fd6a0, power: 4, reach: 9, flicker: 0.1 },
        ],
    },
    props: [
        { key: 'bear', model: 'bear', at: [-3, 1.8], scale: 1.0, turn: -1.2, anchor: [0, 2.05, 0] },
        { key: 'cat', model: 'cat', at: [-0.8, 0.5], scale: 1.05, turn: -1.7, anchor: [0, 1.8, 0] },
        { key: 'travel', model: 'travel', at: [1.6, 1.4], scale: 1.1, turn: -1.3, anchor: [0, 1.6, 0] },
        { key: 'aquatic', model: 'aquatic', at: [3, -1.3], scale: 0.8, turn: -2.0, anchor: [0, 1.1, 0] },
    ],
};
