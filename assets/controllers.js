import { startStimulusApp } from '@symfony/stimulus-bundle';

// Front-end controllers on public pages, loaded as their own <script type="module"> tag (see the site's importmap.php), joining the one Stimulus application the other c975L bundles share
globalThis.c975lStimulusApp ??= startStimulusApp();
const app = globalThis.c975lStimulusApp;

// Dynamic import() so AssetMapper marks these lazy (an importmap entry, but no <link rel="modulepreload">). Keys are the Stimulus identifiers as registered, matching what the templates write in data-controller
const LAZY_CONTROLLERS = {
    'card-back': () => import('./js/back.js'),
};

const registered = new Set();

// Registers only the lazy controllers this document actually contains, the barrel being loaded site-wide while a back is drawn on a few pages. Stimulus connects a controller as soon as it is registered, so a late registration still picks up elements already in the DOM
function registerPresentControllers() {
    for (const [identifier, load] of Object.entries(LAZY_CONTROLLERS)) {
        if (registered.has(identifier) || !document.querySelector(`[data-controller~="${identifier}"]`)) {
            continue;
        }

        registered.add(identifier);
        load().then((module) => app.register(identifier, module.default));
    }
}

registerPresentControllers();

// Turbo swaps the <body> without re-running this module, so a page reached by navigation would otherwise never get its own lazy controllers
document.addEventListener('turbo:load', registerPresentControllers);
