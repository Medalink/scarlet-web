export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.4, 0.7, 0.8] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#4b463f', radius: 60 },
        sun: { from: [-0.6, 0.7, 0.5], colour: 0x9fb0d6, power: 3.5, sky: 0x6f80aa, floor: 0x1a181c, fill: 2.0 },
        glows: [
            { at: [5.6, 4.9], lift: 1.0, colour: 0xff8a3c, power: 22, reach: 16, flicker: 0.25 },
            { at: [-2.4, 1.0], lift: 0.9, colour: 0xffc46b, power: 14, reach: 9, flicker: 0.12 },
        ],
    },
    props: [
        { key: 'copper', model: 'copper', at: [0.2, 0], lift: 0.75, scale: 0.4, turn: 0.4, emit: { all: { grow: 0.45, gain: 0.5 } }, anchor: [0, 2.2, 0] },
        { key: 'tin', model: 'tin', at: [-3.8, 3.4], lift: 0.75, scale: 0.4, turn: 1.9, emit: { all: { grow: 0.45, gain: 0.5 } } },
        { key: 'mithril', model: 'mithril', at: [2.4, 0.5], lift: 0.75, scale: 0.42, turn: 2.6, emit: { all: { grow: 0.45, gain: 0.5 } }, anchor: [0, 2.3, 0] },
        { key: 'iron', model: 'iron', at: [-0.8, 6.0], lift: 0.7, scale: 0.45, turn: 3.4, emit: { all: { grow: 0.45, gain: 0.5 } } },
        { key: 'forge', model: 'forge', at: [6.6, 6.2], scale: 0.8, turn: 2.2, anchor: [0, 3.9, 0] },
        { key: 'cart', model: 'cart', at: [-5.6, 6.6], lift: 0.2, scale: 0.45, turn: 2.2 },
        { key: 'lantern', model: 'lantern', at: [-2.4, 1.0], lift: 0.08 },
        { key: 'pick', model: 'pick', at: [1.5, -1.0], lift: 0.1, scale: 1.6, turn: 0.8, tilt: 1.57 },
    ],
};
