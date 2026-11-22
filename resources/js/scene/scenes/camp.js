const YAW = 0.62;

const faceFire = ([across, away]) => {
    const x = Math.cos(YAW) * across - Math.sin(YAW) * away, z = -Math.sin(YAW) * across - Math.cos(YAW) * away;
    return Math.atan2(z, -x) - YAW;
};

export default {
    look: { shadow: 'plain', fog: [9, 30], far: 120, bloom: [0.5, 0.75, 0.8] },
    view: { yaw: YAW, pitch: 0.15, dist: 11.8, targetY: 1.75, sway: 0.2, offset: { wide: 0.26, narrow: 0 } },
    warmup: 90,
    setting: {
        name: 'camp',
        ground: { tint: '#6b6258', radius: 60 },
        moon: { from: [-6, 9, -4], colour: 0x7f95c8, power: 0.55, sky: 0x6177ad, floor: 0x1c1714, fill: 0.8 },
        fire: {
            prop: 'fire',
            key: { light: 0, power: 30, base: 0.8, swing: 0.2, reach: 3.2, decay: 1.25, shake: 0.035 },
            ember: { light: 1, power: 22, reach: 2.6, decay: 1.3 },
            bloom: [0.42, 0.16],
            glow: [0.22, 0.14],
            core: [0.4, 0.25],
        },
    },
    props: [
        {
            key: 'fire', model: 'campfire', at: [0, 0], turn: -YAW, anchor: [0, 1.9, 0],
            emit: { 0: { boost: 2.4, grow: 2.3, lift: 0.18, order: 0 }, 1: { boost: 1.8, grow: 2.2, lift: 0.3, gain: 1.6, streak: true, cull: true, order: 0 } },
        },
        { key: 'tent', model: 'tent', at: [6.0, 6.8], turn: faceFire([6.0, 6.8]), anchor: [0, 4.45, 0] },
        { key: 'chair', model: 'chair', at: [-2.7, 0.9], turn: faceFire([-2.7, 0.9]), anchor: [0, 1.75, 0] },
    ],
};
