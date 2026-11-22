export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#4a4a40', radius: 60 },
        sun: { from: [-0.5, 0.5, 0.6], colour: 0xc9d4ee, power: 3.8, sky: 0x7b8cb8, floor: 0x1a1a1c, fill: 2.2 },
        glows: [
            { at: [1.9, -1.4], lift: 0.9, colour: 0xffc46b, power: 14, reach: 10, flicker: 0.12 },
        ],
    },
    props: [
        { key: 'station', model: 'tent', at: [3.2, 5.8], scale: 0.75, turn: 2.3, anchor: [0, 3.6, 0] },
        { key: 'work', model: 'bed', at: [0.4, 0.8], scale: 0.55, turn: 0.9, anchor: [0, 1.6, 0] },
        { key: 'stock', model: 'crate', at: [-2.8, -0.4], scale: 1.2, turn: 0.4, anchor: [0, 2.9, 0] },
        { key: 'top', model: 'crate', at: [-2.8, -0.4], lift: 1.49, scale: 0.9, turn: 1 },
        { key: 'horde', model: 'horde', at: [-4.2, 1.4], scale: 0.9, turn: 1.3 },
        { key: 'lantern', model: 'lantern', at: [1.9, -1.4], lift: 0.08 },
    ],
};
