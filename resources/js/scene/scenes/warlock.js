export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 11.2, targetY: 1.25, sway: 0.16, offset: { wide: 0.3, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#35303f', radius: 60 },
        sun: { from: [-0.5, 0.55, 0.6], colour: 0xb9a8ff, power: 3.4, sky: 0x6a5c9c, floor: 0x15121a, fill: 2.2 },
        glows: [
            { at: [0, 0], lift: 1.4, colour: 0x9d6bff, power: 34, reach: 16, flicker: 0.1 },
            { at: [3.2, -1.2], lift: 0.8, colour: 0x7dff6b, power: 8, reach: 7, flicker: 0.2 },
        ],
    },
    props: [
        { key: 'circle', model: 'circle', at: [0, 0], lift: 0.04, scale: 0.6 },
        { key: 'voidwalker', model: 'voidwalker', at: [-2.0, 1.5], scale: 0.8, turn: -1.3, emit: { all: {} }, anchor: [0, 3.0, 0] },
        { key: 'succubus', model: 'succubus', at: [1.7, 1.9], scale: 1, turn: -1.8, anchor: [0, 2.25, 0] },
        { key: 'imp', model: 'imp', at: [0.15, -1.7], scale: 0.62, turn: -1.4, emit: { all: { gain: 0.3, grow: 0.8 } }, anchor: [0, 1.5, 0] },
        { key: 'felhunter', model: 'felhunter', at: [2.6, -0.7], scale: 0.7, turn: -2.2, anchor: [0.45, 2.1, -0.25] },
    ],
};
