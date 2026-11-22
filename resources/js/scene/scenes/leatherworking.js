export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#55483a', radius: 60 },
        sun: { from: [-0.5, 0.35, 0.6], colour: 0xffb07a, power: 5.6, sky: 0x7683b2, floor: 0x1e1815, fill: 2.5 },
        glows: [
            { at: [-3.4, 1.4], lift: 0.9, colour: 0xffc46b, power: 14, reach: 10, flicker: 0.12 },
        ],
    },
    props: [
        { key: 'station', model: 'rack', at: [1.4, 2.8], scale: 0.7, turn: 2.3, anchor: [0, 3.3, 0] },
        { key: 'work', model: 'workbench', at: [-1, -0.6], scale: 1.2, turn: 0.9, anchor: [0, 2, 0] },
        { key: 'boots', model: 'boots', at: [-0.7, -1.1], lift: 1.04, scale: 1.2, turn: 0.4 },
        { key: 'pair', model: 'pair', at: [-1.4, 0], lift: 1.04, scale: 1.2, turn: 2.2 },
        { key: 'stock', model: 'hides', at: [3.8, -0.4], lift: 0.1, scale: 0.7, turn: 0.4, anchor: [0, 0.7, 0] },
        { key: 'saddle', model: 'saddle', at: [5.4, 3.6], turn: 2 },
        { key: 'second', model: 'stand', at: [-4.2, 3.2], turn: 0.6 },
        { key: 'bag', model: 'bag', at: [1.7, -1.9], scale: 1.8, turn: 0.7 },
        { key: 'lantern', model: 'lantern', at: [-3.4, 1.4], lift: 0.08 },
    ],
};
