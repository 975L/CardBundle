// The cards' sounds, made up on the spot by the Web Audio API (nothing to ship, nothing to license): the weather of a back, the wind and the thunder (see back.js), and whatever a site plays with them - the notes, the noise and the creaks of a card turning over. Sound only ever starts once the visitor has tapped, a browser refusing it otherwise
let context = null;

export function sound() {
    if (!window.AudioContext) {
        return null;
    }
    context ??= new window.AudioContext();
    if ("suspended" === context.state) {
        context.resume();
    }

    return context;
}

// A note, gliding or not, fading out
export function tone(ctx, frequency, start, length, { to = frequency, type = "sine", volume = 0.12 } = {}) {
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    const at = ctx.currentTime + start;
    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, at);
    oscillator.frequency.exponentialRampToValueAtTime(to, at + length);
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(volume, at + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + length);
    oscillator.connect(gain).connect(ctx.destination);
    oscillator.start(at);
    oscillator.stop(at + length + 0.05);
}

// Noise through a band-pass: a breath of air when it glides, a crackle of embers when it is cut short
export function noise(ctx, start, length, { from = 2500, to = 900, q = 1.2, volume = 0.06 } = {}) {
    const buffer = ctx.createBuffer(1, Math.ceil(ctx.sampleRate * length), ctx.sampleRate);
    const samples = buffer.getChannelData(0);
    for (let i = 0; i < samples.length; i++) {
        samples[i] = Math.random() * 2 - 1;
    }
    const source = ctx.createBufferSource();
    const filter = ctx.createBiquadFilter();
    const gain = ctx.createGain();
    const at = ctx.currentTime + start;
    source.buffer = buffer;
    filter.type = "bandpass";
    filter.Q.value = q;
    filter.frequency.setValueAtTime(from, at);
    filter.frequency.exponentialRampToValueAtTime(to, at + length);
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.linearRampToValueAtTime(volume, at + length * 0.2);
    gain.gain.linearRampToValueAtTime(0.0001, at + length);
    source.connect(filter).connect(gain).connect(ctx.destination);
    source.start(at);
    source.stop(at + length + 0.05);
}

// A creak: a low sawtooth whose pitch stutters, as wood or iron does sticking and slipping, through a narrow band-pass that keeps only the grind
export function creak(ctx, start, length, { from = 70, to = 110, band = 1100, volume = 0.16 } = {}) {
    const oscillator = ctx.createOscillator();
    const filter = ctx.createBiquadFilter();
    const gain = ctx.createGain();
    const at = ctx.currentTime + start;
    const curve = new Float32Array(48);
    for (let i = 0; i < curve.length; i++) {
        curve[i] = from + (to - from) * (i / curve.length) + (Math.random() - 0.5) * 30;
    }
    oscillator.type = "sawtooth";
    oscillator.frequency.setValueCurveAtTime(curve, at, length);
    filter.type = "bandpass";
    filter.Q.value = 7;
    filter.frequency.value = band;
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(volume, at + 0.04);
    gain.gain.setValueAtTime(volume, at + length * 0.7);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + length);
    oscillator.connect(filter).connect(gain).connect(ctx.destination);
    oscillator.start(at);
    oscillator.stop(at + length + 0.05);
}

// A gust of wind: a breath of air rising and falling, its pitch sweeping up then down
export function whoosh() {
    if (null === context || "running" !== context.state) {
        return;
    }
    noise(context, 0, 1.6, { from: 350, to: 900, q: 0.9, volume: 0.07 });
    noise(context, 0.9, 1.8, { from: 900, to: 300, q: 0.9, volume: 0.05 });
}

// The thunder: a crack overhead, then a long rumble rolling away. Silent until a tap has woken the sound, the storm never starting it on its own
export function rumble() {
    if (null === context || "running" !== context.state) {
        return;
    }
    noise(context, 0, 0.25, { from: 3000, to: 600, q: 0.7, volume: 0.12 });
    noise(context, 0.12, 2.6, { from: 260, to: 60, q: 0.6, volume: 0.22 });
    tone(context, 48, 0.15, 2.2, { to: 34, volume: 0.14 });
}
