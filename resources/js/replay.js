// The hero replay of the character page: the cells the character walked draw in the order it first walked them, over a
// couple of seconds, a count beside the Level figure rises to it and each death mark appears when the walk reaches its day.
// The real figure stays in the page for readers and is only hidden while the count shows. The server's page and the map
// without a replay show the finished state, so a failure, a hidden tab or a frame that never comes ends in it.

const DURATION = 2500;
const TILE_WAIT = 700;
const FRAME_WAIT = 500;

const ease = (t) => (t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2);

export function replayPlan(config) {
    const days = config.replay.days;
    const ends = [];
    let total = 0;
    for (const count of days) {
        total += count;
        ends.push(total);
    }
    const marks = (config.layers.marks ?? []).map((mark) => ends[Math.min(mark.d ?? 0, ends.length - 1)]);
    return {
        at(progress) {
            const cells = Math.round(total * ease(progress));
            return { cells, marks: marks.filter((end) => end <= cells).length };
        },
    };
}

export function bindReplay(map, plan, figure, button) {
    const level = Number(figure?.dataset.count ?? 0);
    const counter = document.createElement('span');
    counter.setAttribute('aria-hidden', 'true');
    let real = null;
    if (figure && level > 1) {
        real = document.createElement('span');
        real.append(...figure.childNodes);
        figure.append(real);
    }
    let frame = 0;
    let watchdog = 0;
    let stepped = false;

    const count = (value) => {
        if (!real) return;
        counter.textContent = value;
        if (!counter.isConnected) {
            real.classList.add('ch-hide');
            real.after(counter);
        }
    };

    const finish = () => {
        cancelAnimationFrame(frame);
        clearTimeout(watchdog);
        map.setReveal(null);
        counter.remove();
        real?.classList.remove('ch-hide');
    };

    const guarded = (work) => (now) => {
        try {
            work(now);
        } catch (error) {
            finish();
            throw error;
        }
    };

    const play = guarded(() => {
        cancelAnimationFrame(frame);
        clearTimeout(watchdog);
        if (document.hidden) return finish();
        stepped = false;
        watchdog = setTimeout(() => { if (!stepped) finish(); }, TILE_WAIT + FRAME_WAIT);
        map.setReveal(plan.at(0));
        const asked = performance.now();
        const walk = guarded(() => {
            const began = performance.now();
            const step = guarded((now) => {
                stepped = true;
                const progress = Math.min(1, (now - began) / DURATION);
                if (progress >= 1) return finish();
                map.setReveal(plan.at(progress));
                count(String(1 + Math.round((level - 1) * ease(progress))));
                frame = requestAnimationFrame(step);
            });
            frame = requestAnimationFrame(step);
        });
        const ready = guarded(() => {
            if (map.pending() === 0 || performance.now() - asked > TILE_WAIT) return walk();
            frame = requestAnimationFrame(ready);
        });
        ready();
    });

    document.addEventListener('visibilitychange', () => { if (document.hidden) finish(); });
    if (button) {
        button.hidden = false;
        button.addEventListener('click', play);
    }
    play();
}
