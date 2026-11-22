export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.3, 0.7, 0.85] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#55483a', radius: 60 },
        sun: { from: [0.6, 0.3, 0.55], colour: 0xff9d6a, power: 5, sky: 0x6c79a8, floor: 0x1c1613, fill: 1.9 },
        glows: [{ at: [-3.4, 2.4], lift: 0.9, colour: 0xffc46b, power: 16, reach: 10, flicker: 0.12 }],
    },
    props: [
        { key: 'rack', model: 'rack', at: [1.4, 1.6], scale: 0.7, turn: 2.3, anchor: [0, 3.3, 0] },
        { key: 'second', model: 'rack', at: [5.7, 6.6], scale: 0.7, turn: 1.5 },
        { key: 'hides', model: 'hides', at: [-0.2, -0.6], lift: 0.1, scale: 0.7, turn: 0.4, anchor: [0, 0.7, 0] },
        { key: 'pile', model: 'pile', at: [4.6, 0.6], lift: 0.1, scale: 0.7, turn: 2.0 },
        { key: 'bag', model: 'bag', at: [2.0, -1.9], scale: 1.8, turn: 0.7, anchor: [0, 1.1, 0] },
        { key: 'lantern', model: 'lantern', at: [-3.4, 2.4], lift: 0.08 },
    ],
};
