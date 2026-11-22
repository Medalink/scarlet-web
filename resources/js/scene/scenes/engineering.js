export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#45464a', radius: 60 },
        sun: { from: [-0.6, 0.6, 0.5], colour: 0xa9b8dc, power: 4.8, sky: 0x7888b4, floor: 0x1c1a1e, fill: 2.5 },
        glows: [
            { at: [1, -1.4], lift: 2, colour: 0xffc46b, power: 14, reach: 10, flicker: 0.06 },
        ],
    },
    props: [
        { key: 'station', model: 'lathe', at: [0.2, 0.4], scale: 0.85, turn: 0.7, anchor: [0, 3.2, 0] },
        { key: 'machine', model: 'machine', at: [5.4, 5.6], scale: 0.6, turn: 2.2 },
        { key: 'crane', model: 'crane', at: [-6.4, 6], scale: 0.5, turn: 1 },
        { key: 'work', model: 'toolbox', at: [-2.4, -0.8], scale: 1.5, turn: 0.5, anchor: [0, 1.5, 0] },
        { key: 'wrench', model: 'wrench', at: [-1.2, -1.7], lift: 0.12, scale: 1.5, tilt: 1.57, turn: 0.4 },
        { key: 'stock', model: 'keg', at: [3, -0.9], scale: 1.4, anchor: [0, 1.9, 0] },
        { key: 'bundle', model: 'bundle', at: [3.9, -0.3], scale: 1.3 },
        { key: 'mortar', model: 'mortar', at: [5.1, 1], scale: 1.1, turn: 2 },
    ],
};
