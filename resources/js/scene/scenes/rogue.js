export default {
    look: { shadow: 'plain', fog: [16, 40], far: 160, bloom: [0.42, 0.7, 0.8] },
    view: { yaw: 0.5, pitch: 0.2, dist: 12.4, targetY: 1.4, sway: 0.16, offset: { wide: 0.22, narrow: 0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#2b2f3c', radius: 60 },
        sun: { from: [-0.5, 0.55, 0.6], colour: 0x9fb4e8, power: 3.0, sky: 0x4a5a8c, floor: 0x12141c, fill: 2.6 },
        glows: [
            { at: [-3.6, 2.2], lift: 1.9, colour: 0xffb347, power: 22, reach: 9, flicker: 0.25 },
            { at: [0.4, 0], lift: 0.9, colour: 0xffd98a, power: 6, reach: 5, flicker: 0.1 },
        ],
    },
    props: [
        { key: 'chest', model: 'chest', at: [0.4, 0], scale: 2.0, turn: -1.5, anchor: [0, 1.45, 0] },
        { key: 'crate', model: 'crate', at: [-2.2, 0.8], scale: 1.5, turn: -1.5 },
        { key: 'vials', model: 'vials', at: [-2.2, 0.8], lift: 0.75, scale: 1.7, turn: -1.5, anchor: [0, 1.8, 0] },
        { key: 'sack', model: 'sack', at: [2.2, -0.6], scale: 2.4, turn: -1.3, anchor: [0, 0.85, 0] },
        { key: 'gold', model: 'gold', at: [2.9, -1.7], scale: 0.5, turn: 0 },
        { key: 'barrel', model: 'barrel', at: [-3.9, 2.2], scale: 0.9, turn: 0 },
        { key: 'crate2', model: 'crate', at: [-0.9, 2.5], scale: 1.4, turn: -1.1 },
        { key: 'lantern', model: 'lantern', at: [-3.9, 2.2], lift: 2.45, scale: 2.2 },
    ],
};
