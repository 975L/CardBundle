// The stars and embers of a night back (see back.js), which a site may also draw on its cards' faces, on screen and frame by frame in an export: no import, so an export can lay this file into its page as it is, its "export" words dropped

// The sky's points, laid out once and the same on every card: spread by two primes rather than at random, so no card gets a clump
export const POINTS = Array.from({ length: 30 }, (_, i) => ({ x: ((i * 97) % 100) / 100, y: ((i * 61) % 100) / 100, phase: i * 0.7, size: 1 + (i % 3) }));

// A value brought back between 0 and 1, whichever way it went out
export const wrap = (value) => ((value % 1) + 1) % 1;

// One frame at the time in ms: the abyss sends its embers up, the sky drifts its stars slowly sideways and lets a star shoot every five seconds; a night back's stars hold still and its shooting star crosses its sky alone (inSky)
export function drawStars(context, width, height, time, { abyss = false, still = false, unit = 1, points = POINTS, night = false, inSky = () => true } = {}) {
    points.forEach((point) => {
        const glow = still ? 0.6 : (Math.sin(time / 600 + point.phase) + 1) / 2;
        const x = abyss || still || night ? point.x : wrap(point.x + time / (60000 + point.phase * 4000));
        const y = abyss && !still ? wrap(point.y - time / (9000 + point.phase * 900)) : point.y;
        context.fillStyle = abyss ? `rgba(255, ${(110 + glow * 60) | 0}, 40, ${glow * 0.95})` : `rgba(255, 246, 210, ${glow * 0.95})`;
        context.beginPath();
        context.arc(x * width, y * height, point.size * unit * (0.5 + glow * 0.6), 0, 2 * Math.PI);
        context.fill();
    });
    if (abyss || still) {
        return;
    }
    const run = (time % 5000) / 900;
    if (run >= 1) {
        return;
    }
    const tail = 60 * unit;
    const x0 = width * (0.15 + 0.7 * run);
    const y0 = height * (0.08 + 0.25 * run);
    if (!inSky(x0 / width, y0 / height)) {
        return;
    }
    const gradient = context.createLinearGradient(x0, y0, x0 - tail, y0 - tail * 0.37);
    gradient.addColorStop(0, `rgba(255, 255, 255, ${1 - run})`);
    gradient.addColorStop(1, "rgba(255, 255, 255, 0)");
    context.strokeStyle = gradient;
    context.lineWidth = 2 * unit;
    context.beginPath();
    context.moveTo(x0, y0);
    context.lineTo(x0 - tail, y0 - tail * 0.37);
    context.stroke();
}
