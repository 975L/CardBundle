// A card back leant by the pointer or the phone (see templates/components/Back.html.twig). What it draws on a canvas is the site's own (see back-canvas.js)
import { Controller } from "@hotwired/stimulus";

const reduce = window.matchMedia("(prefers-reduced-motion: reduce)");

export default class extends Controller {
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
    }

    disconnect() {
        this.surface.removeEventListener("pointermove", this.move);
        this.surface.removeEventListener("pointerleave", this.leave);
        this.surface.removeEventListener("pointerup", this.leave);
        this.surface.removeEventListener("pointercancel", this.leave);
        window.removeEventListener("deviceorientation", this.lean);
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
}
