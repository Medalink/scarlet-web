export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 10.6, targetY: 1.25, sway: 0.16, offset: { wide: 0.24, narrow: 0 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#3a3a44', radius: 60 },
        sun: { from: [-0.5, 0.55, 0.6], colour: 0xc6d4ff, power: 3.6, sky: 0x5a6a9c, floor: 0x14151c, fill: 2.4 },
        glows: [
            { at: [-2.4, 0.4], lift: 1.6, colour: 0xffb050, power: 22, reach: 11, flicker: 0.25 },
            { at: [1.2, 1.2], lift: 1.2, colour: 0xffc46b, power: 16, reach: 9, flicker: 0.25 },
            { at: [2.6, -1.4], lift: 1.6, colour: 0xfff0b0, power: 6, reach: 8, flicker: 0.06 },
        ],
    },
    props: [
        { key: 'pew', model: 'pew', at: [-0.2, 3.5], scale: 0.95, turn: -1.5 },
        { key: 'podium', model: 'podium', at: [-0.6, 0.3], scale: 1.3, turn: -1.5, anchor: [0, 2.0, 0] },
        { key: 'altar', model: 'altar', at: [2.3, -0.3], scale: 0.68, turn: -1.5, anchor: [0, 1.0, 0] },
        { key: 'candelabra', model: 'candelabra', at: [-2.7, 0.4], scale: 0.75, turn: -1.5, anchor: [0, 2.2, 0] },
        { key: 'candles', model: 'candles', at: [1.3, 1.3], scale: 1.1, turn: -1.5, emit: { all: {} } },
    ],
};
