// What a site's own back controller extends to draw on a card back (see templates/components/Back.html.twig, its "controller"): a canvas laid over the back, drawn while in view only, an index possibly holding dozens of them, and once only for who asked for less motion. The site writes paint(context, time), and wanted() to leave a back without a canvas
import { Controller } from "@hotwired/stimulus";

const reduce = window.matchMedia("(prefers-reduced-motion: reduce)");

export default class extends Controller {
    connect() {
        if (!this.wanted()) {
            return;
        }
        this.canvas = document.createElement("canvas");
        this.canvas.className = "card-back__sky";
        this.canvas.setAttribute("aria-hidden", "true");
        this.element.append(this.canvas);
        this.observer = new IntersectionObserver(([entry]) => {
            cancelAnimationFrame(this.frame);
            if (entry.isIntersecting) {
                this.canvas.width = this.canvas.clientWidth * devicePixelRatio;
                this.canvas.height = this.canvas.clientHeight * devicePixelRatio;
                this.draw(performance.now());
            }
        });
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer?.disconnect();
        cancelAnimationFrame(this.frame);
        this.canvas?.remove();
    }

    // Whether this back draws anything
    wanted() {
        return true;
    }

    // One frame, the canvas cleared
    paint() {}

    // Whether to draw a still picture rather than a moving one
    get still() {
        return reduce.matches;
    }

    draw(time) {
        const context = this.canvas.getContext("2d");
        context.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.paint(context, time);
        if (!this.still) {
            this.frame = requestAnimationFrame((next) => this.draw(next));
        }
    }
}
