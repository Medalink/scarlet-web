export function mulberry(seed) {
    let s = seed >>> 0;
    return () => ((s = (Math.imul(s ^ (s >>> 15), 2246822507) + 0x9e3779b9) >>> 0) / 4294967296);
}

/** A model's animation track at time `t`: the value at the nearest two keys, mixed. */
export function sampleTrack(times, values, t) {
    if (values.length === 1) return values[0];
    let i = 1;
    while (i < times.length - 1 && times[i] < t) i++;
    const a = times[i - 1], b = times[i], k = b > a ? Math.min(1, Math.max(0, (t - a) / (b - a))) : 0;
    const va = values[i - 1], vb = values[i];
    return typeof va === 'number' ? va + (vb - va) * k : va.map((v, n) => v + (vb[n] - v) * k);
}
