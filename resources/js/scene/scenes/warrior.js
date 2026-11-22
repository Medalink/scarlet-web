export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 11.2, targetY: 1.25, sway: 0.16, offset: { wide: 0.3, narrow: 0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#4a4038', radius: 60 },
        sun: { from: [-0.6, 0.42, 0.7], colour: 0xffb066, power: 5.2, sky: 0x6a4a3a, floor: 0x1a1310, fill: 2.6 },
        glows: [
            { at: [-2.7, -1.9], lift: 0.7, colour: 0xff8a3d, power: 26, reach: 11, flicker: 0.15 },
            { at: [3.4, 2.6], lift: 0.2, colour: 0xffb066, power: 14, reach: 18, flicker: 0.04 },
        ],
    },
    props: [
        { key: 'dummy', model: 'dummy', at: [-0.5, 0.3], scale: 0.78, turn: -1.4, anchor: [0, 1.95, 0] },
        { key: 'weaponrack', model: 'weaponrack', at: [-2.8, 1.4], scale: 0.72, turn: -2.6, anchor: [0, 2.0, 0] },
        { key: 'armorstand', model: 'armorstand', at: [2.8, 1.2], scale: 1.05, turn: -1.6, anchor: [0, 1.9, 0] },
        { key: 'brazier', model: 'brazier', at: [-2.7, -1.9], scale: 0.32, turn: -1.5, anchor: [0, 0.9, 0] },
    ],
};
