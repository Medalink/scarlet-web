export default {
    look: { shadow: 'plain', fog: [14, 34], far: 160, bloom: [0.34, 0.7, 0.82] },
    view: { yaw: 0.62, pitch: 0.2, dist: 12.4, targetY: 1.3, sway: 0.16, offset: { wide: 0.26, narrow: -0.2 } },
    warmup: 120,
    setting: {
        name: 'water',
        moon: { from: [-0.5, 0.62, -0.6], power: 9, fill: 2.6 },
        bank: { at: [9.5, 10.5], height: 1.5, radius: 44 },
    },
    props: [
        {
            key: 'bobber', model: 'bobber', at: [0, 0], lift: -0.08, scale: 1.7, turn: 2.2, bob: true, anchor: [0, 1.35, 0],
            emit: { 0: { grow: 2.6, gain: 1.7 }, 1: { grow: 2.6, gain: 1.7 }, 2: { grow: 1.3, gain: 1.4 }, 3: { grow: 2.6, gain: 1.7 } },
        },
        {
            key: 'school', model: 'school', at: [-4.2, 2.6], lift: -0.02, scale: 1.15,
            emit: { 0: { boost: 0.5, grow: 2 }, 5: { boost: 0.3, grow: 2 }, 6: { grow: 0.75, gain: 1.5 }, 7: { grow: 0.75, gain: 1.5 }, 1: { grow: 1.6 } },
        },
        { key: 'box', model: 'box', at: [3.7, 2.3], on: 'bank', turn: 0.5, scale: 1.25, anchor: [0, 0.95, 0] },
        { key: 'pole', model: 'pole', at: [2.8, 1.95], on: 'bank', lift: 0.86, turn: 3.5, tilt: 0.62, scale: 1.5 },
        { key: 'rack', model: 'rack', at: [5.3, 7.8], on: 'bank', turn: 0.75, scale: 1.15, anchor: [0, 3.3, 0] },
    ],
};
