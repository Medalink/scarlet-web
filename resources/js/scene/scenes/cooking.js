export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#4d4338', radius: 60 },
        sun: { from: [-0.6, 0.6, 0.5], colour: 0xa9b8dc, power: 4.4, sky: 0x7888b4, floor: 0x1c1a1e, fill: 2.5 },
        glows: [
            { at: [0.3, 0.2], lift: 0.5, colour: 0xff8a3c, power: 22, reach: 12, flicker: 0.25 },
            { at: [3.9, 2.6], lift: 0.4, colour: 0xff7a30, power: 10, reach: 8, flicker: 0.25 },
        ],
    },
    props: [
        { key: 'station', model: 'pot', at: [0.3, 0.2], scale: 1.5, emit: { all: {} }, anchor: [0, 3.7, 0] },
        { key: 'work', model: 'boar', at: [3.9, 2.6], lift: 0.3, scale: 0.85, turn: 2.2, anchor: [0, 2.4, 0] },
        { key: 'rack', model: 'rack', at: [6.4, 6], scale: 0.8, emit: { all: {} } },
        { key: 'stock', model: 'table', at: [-2.4, -1], scale: 0.9, turn: 0.9, anchor: [0, 1.7, 0] },
        { key: 'platter', model: 'platter', at: [-2.1, -1.6], lift: 0.81, scale: 0.45, turn: 0.9 },
        { key: 'bread', model: 'bread', at: [-2.8, -0.2], lift: 0.84, scale: 0.6, turn: 0.3 },
        { key: 'sack', model: 'sack', at: [3, -2.1], scale: 1.3, turn: 0.6 },
    ],
};
