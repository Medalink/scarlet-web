export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#3f3b44', radius: 60 },
        sun: { from: [-0.6, 0.6, 0.5], colour: 0xa9b8dc, power: 4.4, sky: 0x7888b4, floor: 0x1c1a1e, fill: 2.5 },
        glows: [
            { at: [3.6, 1.6], lift: 0.4, colour: 0xff8a3c, power: 12, reach: 9, flicker: 0.25 },
            { at: [0.2, -0.6], lift: 2.2, colour: 0x7dffb0, power: 8, reach: 9, flicker: 0.08 },
        ],
    },
    props: [
        { key: 'station', model: 'set3', at: [0.2, 0.2], turn: 0.9, emit: { all: {} }, anchor: [0, 3.1, 0] },
        { key: 'work', model: 'cauldron', at: [3.6, 1.6], scale: 1.3, emit: { all: {} }, anchor: [0, 2.3, 0] },
        { key: 'set', model: 'set', at: [-6, 4.6], turn: 2.2, emit: { all: {} } },
        { key: 'stock', model: 'table', at: [-2.6, -0.8], scale: 0.9, anchor: [0, 1.7, 0] },
        { key: 'bowl', model: 'bowl', at: [-2.6, -0.8], lift: 0.79, emit: { all: {} } },
        { key: 'bottle', model: 'bottle', at: [-2, -1.2], lift: 0.79 },
        { key: 'green', model: 'green', at: [-3.2, -0.3], lift: 0.79, turn: 1 },
        { key: 'books', model: 'books', at: [5.8, 4.6], scale: 1.4, turn: 0.5 },
    ],
};
