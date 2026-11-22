export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#3a3848', radius: 60 },
        sun: { from: [-0.6, 0.6, 0.5], colour: 0xa9b8dc, power: 3.2, sky: 0x7888b4, floor: 0x1c1a1e, fill: 2.5 },
        glows: [
            { at: [3.8, 3.2], lift: 1.2, colour: 0x8f7dff, power: 26, reach: 14, flicker: 0.1 },
            { at: [0, -0.8], lift: 0.9, colour: 0xffc46b, power: 9, reach: 8, flicker: 0.15 },
        ],
    },
    props: [
        { key: 'station', model: 'lectern', at: [0.4, 0.2], turn: 0.5, anchor: [0, 3, 0] },
        { key: 'work', model: 'pool', at: [3.8, 3.2], scale: 0.55, emit: { all: {} }, anchor: [0, 3.2, 0] },
        { key: 'stock', model: 'books', at: [-2.4, -0.6], scale: 1.4, turn: 0.7, anchor: [0, 1.4, 0] },
        { key: 'more', model: 'low', at: [-3.2, 0.5], scale: 1.4, turn: 2 },
        { key: 'stand', model: 'candelabra', at: [-3.8, 3.6] },
        { key: 'far', model: 'candelabra', at: [6.4, 5.8], turn: 1 },
        { key: 'candle', model: 'candle', at: [-1.4, 1.5], scale: 1.2, emit: { all: {} } },
        { key: 'near', model: 'candle', at: [1.9, -1.2], scale: 1.2, emit: { all: {} } },
        { key: 'third', model: 'candle', at: [-0.4, -1.7], scale: 1.2, emit: { all: {} } },
    ],
};
