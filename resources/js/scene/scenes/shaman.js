export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 11.2, targetY: 1.25, sway: 0.16, offset: { wide: 0.3, narrow: 0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#2c323c', radius: 60 },
        sun: { from: [-0.5, 0.55, 0.6], colour: 0xa9c0ff, power: 3.0, sky: 0x55688f, floor: 0x12151b, fill: 2.0 },
        glows: [
            { at: [-4.7, 2.87], lift: 1.3, colour: 0xff7a2a, power: 12, reach: 8, flicker: 0.3 },
            { at: [-2.1, 1.32], lift: 1.3, colour: 0xc9a25a, power: 9, reach: 7, flicker: 0.1 },
            { at: [0.1, 0.02], lift: 1.3, colour: 0x3d9bff, power: 12, reach: 8, flicker: 0.15 },
            { at: [1.9, -1.08], lift: 1.3, colour: 0xcfe9ff, power: 12, reach: 8, flicker: 0.2 },
        ],
    },
    props: [
        { key: 'fire', model: 'fire', at: [-4.7, 2.87], lift: -1.3, scale: 0.9, turn: -1.5, emit: { all: {} }, anchor: [0, 3.25, 0] },
        { key: 'earth', model: 'earth', at: [-2.1, 1.32], lift: -1.3, scale: 0.9, turn: -1.5, emit: { all: {} }, anchor: [0, 3.25, 0] },
        { key: 'water', model: 'water', at: [0.1, 0.02], lift: -1.3, scale: 0.9, turn: -1.5, emit: { all: {} }, anchor: [0, 3.25, 0] },
        { key: 'air', model: 'air', at: [1.9, -1.08], lift: -1.3, scale: 0.9, turn: -1.5, emit: { all: {} }, anchor: [0, 3.25, 0] },
    ],
};
