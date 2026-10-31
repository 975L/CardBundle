// A card back drawn and moving (see templates/components/Back.html.twig): leant by the pointer or the phone, its effect drawn on a canvas laid over it - stars or embers, snow, rain, the low sun, mist, wind - and the storm's thunder and the wind's gusts heard and felt
import { Controller } from "@hotwired/stimulus";
import { rumble, whoosh } from "./audio.js";
import { drawStars, POINTS, wrap } from "./sky.js";

// What a finish does that CSS cannot, on every card (see templates/components/Sheet/Card.html.twig): the card tilts under the pointer, its light staying put, and on the app's bare card page after the phone itself when nothing touches it, as Papa Câlin's cards do; a tap presses it in, and the two highest finishes get a sky of their own drawn on a canvas - stars drifting with a shooting star now and then for Astrale, embers rising for Abyssale, and a back's snow, rain, wind, mist, thunder, and the sun's rays and birds of its dawn and sunset. The canvas only runs while the card is on screen, and a visitor asking for less motion gets the sky still and the card flat
const reduce = window.matchMedia("(prefers-reduced-motion: reduce)");

// A night back's stars, denser, as only those falling in its picture's sky are kept (see skyOf()), or half of them pressed above its castle's sky line
const NIGHT = Array.from({ length: 70 }, (_, i) => ({ x: ((i * 97) % 101) / 101, y: ((i * 61) % 103) / 103, phase: i * 0.7, size: 1 + (i % 4) * 0.6 }));

// The grid a back's picture is read on to find its sky, how far a cell's colour may stray from the sky above it, and how far down the sky may reach
const SKY = { width: 60, height: 84, edge: 0.06, depth: 0.7 };

// The snow's flakes, laid out once as the sky's points: each its own size, fall and sway, so none falls in step with another
const FLAKES = Array.from({ length: 18 }, (_, i) => ({ x: ((i * 89) % 100) / 100, y: ((i * 53) % 100) / 100, size: 0.8 + ((i * 7) % 5) * 0.45, fall: 7000 + ((i * 37) % 11) * 900, sway: 0.015 + ((i * 13) % 7) * 0.006, pace: 1300 + ((i * 29) % 9) * 260, phase: i * 1.9 }));

// The storm's drops, laid out the same way: fewer than a curtain, each its own speed, slant and length
const DROPS = Array.from({ length: 28 }, (_, i) => ({ x: ((i * 83) % 100) / 100, y: ((i * 47) % 100) / 100, fall: 650 + ((i * 31) % 9) * 90, slant: 0.26 + ((i * 17) % 7) * 0.012, length: 10 + ((i * 11) % 6) * 2.5, width: 0.8 + (i % 3) * 0.35, alpha: 0.18 + ((i * 7) % 5) * 0.05 }));

// The wind's wisps, curling across on a wave of their own, and the leaves it carries, fluttering
const WISPS = Array.from({ length: 6 }, (_, i) => ({ x: ((i * 37) % 100) / 100, y: 0.14 + ((i * 41) % 70) / 100, pace: 1700 + ((i * 23) % 5) * 300, length: 0.35 + ((i * 13) % 4) * 0.08, wave: 0.012 + (i % 3) * 0.008, phase: i * 2.3 }));
const LEAVES = Array.from({ length: 9 }, (_, i) => ({ x: ((i * 71) % 100) / 100, y: ((i * 29) % 100) / 100, pace: 1500 + ((i * 19) % 6) * 250, size: 1.6 + (i % 3) * 0.7, phase: i * 1.3 }));

// The mist's banks, each a soft puff drifting across at its own pace, low ones slower and thicker
const BANKS = Array.from({ length: 9 }, (_, i) => ({ x: ((i * 43) % 100) / 100, y: 0.3 + ((i * 29) % 60) / 100, pace: 9000 + ((i * 17) % 7) * 1800, size: 0.35 + ((i * 11) % 5) * 0.06, alpha: 0.3 + ((i * 7) % 4) * 0.07, phase: i * 1.7, way: 0 === i % 3 ? -1 : 1 }));

// The birds crossing a dawn or a sunset, each at its own height, pace and wingbeat, and the sun's rays fanning from where it stands
const BIRDS = Array.from({ length: 5 }, (_, i) => ({ x: ((i * 41) % 100) / 100, y: 0.08 + ((i * 23) % 28) / 100, pace: 11000 + ((i * 13) % 5) * 1800, size: 4 + (i % 3) * 1.4, beat: 110 + ((i * 7) % 4) * 25, phase: i * 1.1 }));
const RAYS = Array.from({ length: 6 }, (_, i) => ({ angle: (i - 2.5) * 0.17, width: 0.035 + (i % 3) * 0.015, phase: i * 1.9 }));

// A sunset's sky, from the top of the picture down to its horizon, a little above its middle: indigo, rose, orange, then the low sun's gold
const SUNSET = [[0, [45, 40, 95]], [0.35, [150, 70, 110]], [0.65, [245, 135, 60]], [0.88, [255, 195, 105]], [1, [255, 220, 150]]];

// Each castle's picture repainted at sunset, once for every back of the page showing it
const sunsets = new Map();

// The wind blows in gusts of this length, the sway of the picture swinging once each (see card-back-sway in sass/_back.scss)
const GUST = 5000;

// When each sound last played, one for every back on the page
const heard = { thunder: -Infinity, wind: -Infinity };

// The sunset's sky colour at a height, from 0 at the top to 1 at its horizon
const skyAt = (height) => {
    const next = SUNSET.findIndex(([stop]) => height <= stop);
    if (next <= 0) {
        return SUNSET[Math.max(0, next)][1];
    }
    const [[from, low], [to, high]] = [SUNSET[next - 1], SUNSET[next]];
    return low.map((value, c) => value + ((high[c] - value) * (height - from)) / (to - from));
};

export default class extends Controller {
    static values = { sky: Number };

    connect() {
        this.move = this.move.bind(this);
        this.leave = this.leave.bind(this);
        // On a flip card the pointer lands on the toggle laid under the card's content, so it is listened to on the flip card as a whole
        this.surface = this.element.closest(".flip-card") ?? this.element;
        if (!reduce.matches) {
            this.surface.addEventListener("pointermove", this.move);
            this.surface.addEventListener("pointerleave", this.leave);
            this.surface.addEventListener("pointerup", this.leave);
            this.surface.addEventListener("pointercancel", this.leave);
            // The phone leans the back on a page showing a card alone, which marks it "data-card-lean": an index of dozens of them all swaying would be noise
            if (this.element.closest("[data-card-lean]")) {
                this.lean = this.lean.bind(this);
                window.addEventListener("deviceorientation", this.lean);
            }
        }
        if (this.element.matches(".card-back--stars, .card-back--embers, .card-back--snow, .card-back--tone-storm, .card-back--lightning, .card-back--wind, .card-back--mist, .card-back--tone-dawn, .card-back--tone-sunset")) {
            this.sky();
        }
    }

    disconnect() {
        this.surface.removeEventListener("pointermove", this.move);
        this.surface.removeEventListener("pointerleave", this.leave);
        this.surface.removeEventListener("pointerup", this.leave);
        this.surface.removeEventListener("pointercancel", this.leave);
        window.removeEventListener("deviceorientation", this.lean);
        this.observer?.disconnect();
        cancelAnimationFrame(this.frame);
        this.canvas?.remove();
        // A card put back on the page starts its lightning over, under a new animation
        this.lightning = undefined;
        this.lastStrike = undefined;
        this.lastGust = undefined;
    }

    // The tilt, from where the pointer stands on the card: a pointer that touches leaves the phone out until it lifts
    move(event) {
        this.touching = true;
        const box = this.element.getBoundingClientRect();
        this.tilt((event.clientX - box.left) / box.width, (event.clientY - box.top) / box.height);
    }

    leave() {
        this.touching = false;
        this.tilt(0.5, 0.5);
    }

    // The phone held in the hand: its first reading is the way it is held, the lean is how far it moved since, 60° from one edge of the card to the other
    lean(event) {
        if (this.touching || null === event.beta || null === event.gamma) {
            return;
        }
        this.origin ??= { beta: event.beta, gamma: event.gamma };
        this.tilt(0.5 + (event.gamma - this.origin.gamma) / 60, 0.5 + (event.beta - this.origin.beta) / 60);
    }

    // The card leant towards a point of it, from 0 to 1 across and down
    tilt(x, y) {
        const clamp = (value) => Math.min(1, Math.max(0, value));
        this.element.style.setProperty("--card-ry", `${(clamp(x) - 0.5) * 16}deg`);
        this.element.style.setProperty("--card-rx", `${(0.5 - clamp(y)) * 16}deg`);
    }

    // The canvas laid over the card, drawn while the card is in view only: an index may hold dozens of them
    sky() {
        this.canvas = document.createElement("canvas");
        this.canvas.className = "card-back__sky";
        this.canvas.setAttribute("aria-hidden", "true");
        this.element.append(this.canvas);
        const picture = this.element.querySelector(".card-back__picture img");
        if (picture) {
            this.picture(picture);
        }
        this.observer = new IntersectionObserver(([entry]) => {
            cancelAnimationFrame(this.frame);
            if (entry.isIntersecting) {
                this.resize();
                this.draw(performance.now());
            }
        });
        this.observer.observe(this.element);
    }

    // A castle's picture, once loaded: repainted at sunset, and its sky kept for a night's stars - the sky line the site gives (the component's "sky"), else read off the picture
    picture(picture) {
        const loaded = (then) => (picture.complete ? then() : picture.addEventListener("load", then, { once: true }));
        if (this.element.classList.contains("card-back--tone-sunset")) {
            loaded(() => this.sunset(picture));
        }
        if (!this.element.classList.contains("card-back--stars")) {
            return;
        }
        if (this.hasSkyValue) {
            const line = this.skyValue / 100;
            this.inSky = (x, y) => y < line && this.moonless(x, y);
            this.night = NIGHT.filter((_, i) => 0 === i % 2).map((point) => ({ ...point, y: point.y * line })).filter((point) => this.moonless(point.x, point.y));
        } else {
            loaded(() => this.skyOf(picture));
        }
    }

    // The picture's sky: read small, as the CSS lays it over the back (cover, its top 40% kept), each column going down from the top until its colour leaves the average of what is above it for two cells running - a roof, a tower, a tree - no lower than its upper part
    skyOf(picture) {
        const { width, height, edge, depth } = SKY;
        const reader = document.createElement("canvas");
        reader.width = width;
        reader.height = height;
        const context = reader.getContext("2d", { willReadFrequently: true });
        const scale = Math.max(width / picture.naturalWidth, height / picture.naturalHeight);
        const drawnWidth = picture.naturalWidth * scale;
        const drawnHeight = picture.naturalHeight * scale;
        let pixels;
        try {
            context.drawImage(picture, (width - drawnWidth) / 2, (height - drawnHeight) * 0.4, drawnWidth, drawnHeight);
            pixels = context.getImageData(0, 0, width, height).data;
        } catch {
            return;
        }
        const skyline = Array.from({ length: width }, (_, x) => {
            const sum = [0, 0, 0];
            const apart = (row, count) => {
                const i = 4 * (Math.min(height - 1, row) * width + x);
                return [0, 1, 2].reduce((total, c) => total + Math.abs(pixels[i + c] - sum[c] / count), 0) / 765;
            };
            let y = 0;
            for (; y < height * depth; y++) {
                if (y > 2 && apart(y, y) > edge && apart(y + 1, y) > edge) {
                    break;
                }
                [0, 1, 2].forEach((c) => {
                    sum[c] += pixels[4 * (y * width + x) + c];
                });
            }
            return y / height;
        });
        // A star keeps off the skyline by a cell, beside a spire as above a roof
        this.inSky = (x, y) => {
            if (!this.moonless(x, y)) {
                return false;
            }
            const column = Math.min(width - 1, Math.floor(x * width));
            return y < Math.min(...[column - 1, column, column + 1].filter((c) => c >= 0 && c < width).map((c) => skyline[c])) - 1 / height;
        };
        this.night = NIGHT.filter((point) => this.inSky(point.x, point.y));
    }

    // Whether a point of the back stands clear of the night's moon (see card-back--tone-night in sass/_back.scss), measured round on a back 1.4 times as tall as it is wide
    moonless(x, y) {
        return !this.element.classList.contains("card-back--tone-night") || Math.hypot(x - 0.75, (y - 0.14) * 1.4) >= 0.12;
    }

    // The picture repainted at sunset, as the CSS lays it over the back: its sky - the bright and blue of it - takes the sunset's colours by its height, the stone the golden hour's warmth, the ground its shadow, the low sun blooming in the sky and lighting the walls' edges
    sunset(picture) {
        if (picture.src.startsWith("data:")) {
            return;
        }
        if (!sunsets.has(picture.src)) {
            // At the picture's own resolution, cut to the back's shape, so nothing is resampled and the castle stays sharp
            const width = Math.max(1, Math.round(Math.min(picture.naturalWidth, (picture.naturalHeight * 352) / 491)));
            const height = Math.round((width * 491) / 352);
            const painter = document.createElement("canvas");
            painter.width = width;
            painter.height = height;
            const context = painter.getContext("2d", { willReadFrequently: true });
            const scale = Math.max(width / picture.naturalWidth, height / picture.naturalHeight);
            try {
                context.drawImage(picture, (width - picture.naturalWidth * scale) / 2, (height - picture.naturalHeight * scale) * 0.4, picture.naturalWidth * scale, picture.naturalHeight * scale);
                const image = context.getImageData(0, 0, width, height);
                const pixels = image.data;
                for (let y = 0; y < height; y++) {
                    const sky = skyAt(Math.min(1, y / (height * 0.55)));
                    // The ground falls into shadow towards the bottom of the back
                    const shade = 1 - 0.55 * Math.max(0, (y / height - 0.45) / 0.55);
                    for (let x = 0; x < width; x++) {
                        const i = 4 * (y * width + x);
                        const [r, g, b] = [pixels[i], pixels[i + 1], pixels[i + 2]];
                        const light = (0.3 * r + 0.59 * g + 0.11 * b) / 255;
                        const weight = Math.min(1, Math.min(1, Math.max(0, (b - r) / 50)) * 1.2 * Math.min(1, light * 2.2));
                        // The low sun, a little right of the middle: its bloom in the sky, its rim of light on the stone
                        const glow = Math.exp(-((x / width - 0.72) ** 2 + ((y / height - 0.5) * 1.4) ** 2) / 0.06);
                        const stone = [r * 0.95 * shade + 89 * glow, g * 0.68 * shade + 60 * glow, b * 0.48 * shade + 28 * glow];
                        const air = [sky[0] * (0.75 + 0.55 * light) + 153 * glow, sky[1] * (0.75 + 0.55 * light) + 114 * glow, sky[2] * (0.75 + 0.55 * light) + 66 * glow];
                        [0, 1, 2].forEach((c) => {
                            pixels[i + c] = stone[c] + (air[c] - stone[c]) * weight;
                        });
                    }
                }
                context.putImageData(image, 0, 0);
                sunsets.set(picture.src, painter.toDataURL("image/webp", 0.95));
            } catch {
                return;
            }
        }
        picture.src = sunsets.get(picture.src);
    }

    resize() {
        this.canvas.width = this.canvas.clientWidth * devicePixelRatio;
        this.canvas.height = this.canvas.clientHeight * devicePixelRatio;
    }

    // One frame of every layer the card has, then the storm's thunder when its lightning strikes
    draw(time) {
        const context = this.canvas.getContext("2d");
        context.clearRect(0, 0, this.canvas.width, this.canvas.height);
        if (this.element.matches(".card-back--stars, .card-back--embers")) {
            this.stars(context, time);
        }
        if (this.element.classList.contains("card-back--snow")) {
            this.snow(context, time);
        }
        if (this.element.classList.contains("card-back--tone-storm")) {
            this.rain(context, time);
        }
        if (this.element.classList.contains("card-back--wind")) {
            this.wind(context, time);
        }
        if (this.element.matches(".card-back--tone-dawn, .card-back--tone-sunset")) {
            this.sun(context, time);
        }
        if (this.element.classList.contains("card-back--mist")) {
            this.mist(context, time);
        }
        if (this.element.classList.contains("card-back--lightning")) {
            this.thunder();
        }
        if (!reduce.matches) {
            this.frame = requestAnimationFrame((next) => this.draw(next));
        }
    }

    // The abyss sends its embers up, the sky drifts its stars slowly sideways and lets a star shoot every five seconds
    stars(context, time) {
        const { width, height } = this.canvas;
        const abyss = this.element.classList.contains("card-back--embers");
        const still = reduce.matches;
        // Drawn to the back's size, a thumbnail showing the stars of the back turned over, only smaller (see the "--back-px" of sass/_back.scss)
        const unit = width / 352;
        // A night back's stars hold still in its sky, twinkling, until it is found none is drawn
        const night = this.element.classList.contains("card-back--stars") && this.element.querySelector(".card-back__picture img");
        if (night && !this.night) {
            return;
        }
        drawStars(context, width, height, time, { abyss, still, unit, points: night ? this.night : POINTS, night: Boolean(night), inSky: (x, y) => !night || this.inSky(x, y) });
    }

    // Each flake falls at its own pace, swaying on two waves that never line up, the big ones nearer so faster and brighter
    snow(context, time) {
        const { width, height } = this.canvas;
        const still = reduce.matches;
        const unit = width / 352;
        FLAKES.forEach((flake) => {
            const drift = still ? 0 : flake.sway * (Math.sin(time / flake.pace + flake.phase) + 0.5 * Math.sin(time / (flake.pace * 0.37) + flake.phase * 2));
            const x = wrap(flake.x + drift);
            const y = still ? flake.y : wrap(flake.y + time / (flake.fall / (flake.size * 0.6)));
            context.fillStyle = `rgba(255, 255, 255, ${0.45 + flake.size * 0.18})`;
            context.beginPath();
            context.arc(x * width, y * height, flake.size * unit, 0, 2 * Math.PI);
            context.fill();
        });
    }

    // Each drop falls at its own speed and slant, the wind bending them all together, slowly, the long ones nearer so faster and brighter
    rain(context, time) {
        const { width, height } = this.canvas;
        const still = reduce.matches;
        const unit = width / 352;
        const gust = still ? 0 : 0.06 * Math.sin(time / 6000) + 0.02 * Math.sin(time / 2600);
        context.lineCap = "round";
        DROPS.forEach((drop) => {
            const slant = drop.slant + gust;
            const fall = still ? 0 : time / drop.fall;
            const y = wrap(drop.y + fall);
            const x = wrap(drop.x + slant * fall * (height / width));
            const length = drop.length * unit;
            context.strokeStyle = `rgba(205, 220, 245, ${drop.alpha})`;
            context.lineWidth = drop.width * unit;
            context.beginPath();
            context.moveTo(x * width, y * height);
            context.lineTo(x * width - slant * length, y * height - length);
            context.stroke();
        });
    }

    // The dawn and the sunset: the sun's rays at dawn alone, and birds crossing, leaving at dawn and coming home at dusk
    sun(context, time) {
        const dawn = this.element.classList.contains("card-back--tone-dawn");
        if (dawn) {
            this.rays(context, time);
        }
        this.birds(context, time, dawn);
    }

    // The dawn's rays fanning down from the top left, each a beam swaying a little and breathing
    rays(context, time) {
        const { width, height } = this.canvas;
        const still = reduce.matches;
        const origin = [0.18 * width, -0.08 * height];
        const reach = 1.3 * height;
        // Pictures standing in bright daylight already want stronger rays to show on them, which the site says in "--back-rays"
        this.strength ??= parseFloat(getComputedStyle(this.element).getPropertyValue("--back-rays")) || 0.16;
        const strength = this.strength;
        context.save();
        context.globalCompositeOperation = "lighter";
        RAYS.forEach((ray) => {
            const angle = 0.45 + ray.angle + (still ? 0 : 0.03 * Math.sin(time / 6000 + ray.phase));
            const glow = still ? 0.6 : 0.5 + 0.5 * Math.sin(time / 2600 + ray.phase);
            const gradient = context.createLinearGradient(origin[0], origin[1], origin[0] + Math.cos(angle) * reach, origin[1] + Math.sin(angle) * reach);
            gradient.addColorStop(0, `rgba(255, 210, 150, ${strength * glow})`);
            gradient.addColorStop(1, "rgba(255, 180, 100, 0)");
            context.fillStyle = gradient;
            context.beginPath();
            context.moveTo(origin[0], origin[1]);
            context.lineTo(origin[0] + Math.cos(angle - ray.width) * reach, origin[1] + Math.sin(angle - ray.width) * reach);
            context.lineTo(origin[0] + Math.cos(angle + ray.width) * reach, origin[1] + Math.sin(angle + ray.width) * reach);
            context.fill();
        });
        context.restore();
    }

    // The birds, leaving at dawn and coming home at dusk
    birds(context, time, dawn) {
        const { width, height } = this.canvas;
        const still = reduce.matches;
        const unit = width / 352;
        context.strokeStyle = "rgba(35, 22, 25, 0.8)";
        context.lineCap = "round";
        context.lineJoin = "round";
        BIRDS.forEach((bird) => {
            const run = still ? bird.x : wrap(bird.x + time / bird.pace);
            const x = (dawn ? run : 1 - run) * 1.4 - 0.2;
            const y = bird.y + (still ? 0 : 0.012 * Math.sin(time / 900 + bird.phase));
            const span = bird.size * unit;
            const lift = still ? 0.4 : Math.sin(time / bird.beat + bird.phase);
            context.lineWidth = 1.2 * unit;
            context.beginPath();
            context.moveTo(x * width - span, y * height - lift * span * 0.6);
            context.quadraticCurveTo(x * width - span * 0.4, y * height - span * 0.15, x * width, y * height);
            context.quadraticCurveTo(x * width + span * 0.4, y * height - span * 0.15, x * width + span, y * height - lift * span * 0.6);
            context.stroke();
        });
    }

    // The mist: banks of fog drifting both ways at their own pace, rising and sinking a little and thickening then thinning, so the castle comes and goes behind them
    mist(context, time) {
        const { width, height } = this.canvas;
        const still = reduce.matches;
        BANKS.forEach((bank) => {
            const x = (still ? bank.x : wrap(bank.x + (bank.way * time) / bank.pace)) * 1.8 - 0.4;
            const y = bank.y + (still ? 0 : 0.03 * Math.sin(time / 5000 + bank.phase));
            const alpha = bank.alpha * (still ? 1 : 0.7 + 0.3 * Math.sin(time / 3700 + bank.phase * 2));
            const radius = bank.size * width;
            context.save();
            context.translate(x * width, y * height);
            context.scale(1, 0.5);
            const puff = context.createRadialGradient(0, 0, 0, 0, 0, radius);
            puff.addColorStop(0, `rgba(236, 239, 243, ${alpha})`);
            puff.addColorStop(0.6, `rgba(236, 239, 243, ${alpha * 0.5})`);
            puff.addColorStop(1, "rgba(236, 239, 243, 0)");
            context.fillStyle = puff;
            context.beginPath();
            context.arc(0, 0, radius, 0, 2 * Math.PI);
            context.fill();
            context.restore();
        });
    }

    // The wind: wisps curling across and leaves carried with them, faster and brighter as each gust rises, a gust heard and felt as it comes
    wind(context, time) {
        const { width, height } = this.canvas;
        const still = reduce.matches;
        const unit = width / 352;
        const gust = still ? 0.5 : Math.sin((Math.PI * (time % GUST)) / GUST) ** 2;
        context.lineCap = "round";
        context.lineWidth = 1.3 * unit;
        WISPS.forEach((wisp) => {
            const head = still ? wisp.x : wrap(wisp.x + time / wisp.pace) * 1.8 - 0.3;
            const steps = 18;
            for (let k = 1; k <= steps; k++) {
                const point = (step) => {
                    const x = head - wisp.length * (1 - step / steps);
                    return [x * width, (wisp.y + wisp.wave * Math.sin(x * 9 + time / 900 + wisp.phase)) * height];
                };
                const [x0, y0] = point(k - 1);
                const [x1, y1] = point(k);
                context.strokeStyle = `rgba(240, 245, 240, ${(k / steps) * (0.12 + 0.3 * gust)})`;
                context.beginPath();
                context.moveTo(x0, y0);
                context.lineTo(x1, y1);
                context.stroke();
            }
        });
        LEAVES.forEach((leaf) => {
            const x = still ? leaf.x : wrap(leaf.x + time / leaf.pace);
            const y = wrap(leaf.y + (still ? 0 : 0.03 * Math.sin(time / 400 + leaf.phase) + time / (leaf.pace * 6)));
            context.save();
            context.translate(x * width, y * height);
            context.rotate(still ? leaf.phase : time / 250 + leaf.phase);
            context.fillStyle = `rgba(215, 200, 150, ${0.35 + 0.4 * gust})`;
            context.beginPath();
            context.ellipse(0, 0, leaf.size * unit, leaf.size * 0.45 * unit, 0, 0, 2 * Math.PI);
            context.fill();
            context.restore();
        });
        const at = time % GUST;
        // A new gust when the cycle starts over, not when a frame stamped a little before the first one comes back
        if (undefined !== this.lastGust && at < this.lastGust - GUST / 2) {
            this.felt("wind", whoosh, [15, 40, 25, 40, 15]);
        }
        this.lastGust = at;
    }

    // The thunder on the lightning's first flash (see card-back-lightning in sass/_back.scss), read off the CSS animation so sound and light keep in step
    thunder() {
        this.lightning ??= this.element.getAnimations({ subtree: true }).find((animation) => "card-back-lightning" === animation.animationName);
        if (!this.lightning || null === this.lightning.currentTime) {
            return;
        }
        const at = this.lightning.currentTime % 7000;
        const struck = undefined !== this.lastStrike && this.lastStrike < 4340 && at >= 4340;
        this.lastStrike = at;
        if (struck) {
            this.felt("thunder", rumble, [40, 60, 120]);
        }
    }

    // A sound of the weather, heard on a card turned over to its back alone, a pack's dozens of backs blowing or striking together, once for them all; felt in the hand through the app, which owns the phone's vibrator, or the browser's
    felt(kind, play, pattern) {
        if (!this.element.closest(".flip-card")?.classList.contains("is-flipped") || performance.now() - heard[kind] < 3000) {
            return;
        }
        heard[kind] = performance.now();
        play();
        if (window.ReactNativeWebView) {
            window.ReactNativeWebView.postMessage(kind);
        } else {
            navigator.vibrate?.(pattern);
        }
    }
}
