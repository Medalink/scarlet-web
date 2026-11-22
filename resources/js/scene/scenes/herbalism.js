export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.3, 0.7, 0.85] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.1, sway: 0.16, offset: { wide: 0.26, narrow: -0.1 } },
    warmup: 120,
    setting: {
        name: 'ground',
        ground: { tint: '#3c4a2a', radius: 60 },
        sun: { from: [-0.75, 0.4, 0.3], colour: 0xffcf9a, power: 7, sky: 0xa9bdf0, floor: 0x243020, fill: 3.4 },
    },
    props: [
        { key: 'peacebloom', model: 'peacebloom', at: [0.2, 0], lift: 0.25, scale: 0.75, turn: 0.4, anchor: [0, 1.9, 0] },
        { key: 'silverleaf', model: 'silverleaf', at: [-3.4, 2.4], lift: 0.3, scale: 0.5, turn: 1.1, anchor: [0, 2.2, 0] },
        { key: 'mageroyal', model: 'mageroyal', at: [4.4, 3.4], lift: 0.35, scale: 0.5, turn: 2.0, anchor: [0, 2.0, 0] },
        { key: 'kingsblood', model: 'kingsblood', at: [2.6, -1.6], lift: 0.2, scale: 0.42, turn: 0.2, emit: { all: { grow: 0.6 } } },
        { key: 'briarthorn', model: 'briarthorn', at: [-1.2, 5.4], lift: 0.5, scale: 0.5, turn: 2.6 },
        { key: 'bruiseweed', model: 'bruiseweed', at: [5.8, 6.2], lift: 0.2, scale: 0.75, emit: { all: {} } },
        { key: 'fadeleaf', model: 'fadeleaf', at: [7.0, 1.2], lift: 0.4, scale: 0.45, turn: 1.4 },
        { key: 'khadgar', model: 'khadgar', at: [-5.4, 6.8], lift: 0.3, scale: 0.5 },
        { key: 'goldthorn', model: 'goldthorn', at: [1.9, 7.4], lift: 0.2, scale: 1.1, turn: 0.9 },
        { key: 'lotus', model: 'lotus', at: [-1.9, -1.0], lift: 0.05, scale: 0.9, turn: 0.6 },
    ],
};
