export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#4c4440', radius: 60 },
        sun: { from: [-0.5, 0.45, 0.6], colour: 0xffd9b0, power: 4.2, sky: 0x8090bb, floor: 0x1c1a1c, fill: 2.3 },
        glows: [
            { at: [-1, -1.6], lift: 2, colour: 0xffc46b, power: 10, reach: 9, flicker: 0.06 },
        ],
    },
    props: [
        { key: 'station', model: 'loom', at: [0.4, 0.8], scale: 1.5, turn: 2.2, anchor: [0, 3.6, 0] },
        { key: 'work', model: 'wheel', at: [3.3, 1.5], scale: 1.3, turn: 1.2, anchor: [0, 2.9, 0] },
        { key: 'stock', model: 'table', at: [-2.6, -0.6], anchor: [0, 1.7, 0] },
        { key: 'blue', model: 'blue', at: [-2.6, -0.6], lift: 0.9, scale: 1.2, turn: 0.5 },
        { key: 'spool', model: 'spool', at: [-2, -1.4], lift: 0.8, scale: 2.5 },
        { key: 'yellow', model: 'yellow', at: [-3.3, 0.1], lift: 0.8, scale: 2.5 },
        { key: 'green', model: 'green', at: [5.6, 5.4], lift: 0.98, scale: 1.5 },
        { key: 'red', model: 'red', at: [6.3, 4.9], lift: 0.98, scale: 1.5, turn: 0.8 },
        { key: 'white', model: 'white', at: [5, 5.9], lift: 0.98, scale: 1.5, turn: 2 },
        { key: 'orange', model: 'orange', at: [1.6, -1.6], scale: 1.4 },
    ],
};
