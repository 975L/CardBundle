---
name: c975l-card
description: "Use this skill when working with collectible cards in a Symfony application built on the c975L ecosystem with c975l/card-bundle. Covers the collection held under a device token then an account, the three finishes, the card backs given and chosen per pack, and the plumbing of a mobile app's card api. Triggers on: cards, card collection, collectible cards, album, CollectedCard, CardCollector, grant a card, CardFinish, finish, shiny, magic, CardHolder, holder, device token, connect a device, CardBack, CollectedBack, BackCollector, card back, choose a back, pack, BackTone, BackEffect, CardDemoFixtureProvider, demo dataset, demo backs, CardRequest, app api, /app/api/cards, HolderStoreInterface, CardCatalogInterface, CardPackProviderInterface, Plumes, UserAnonymizedEvent, account export."
---

# c975L CardBundle

> Collectible cards on the c975L core — what each player holds and in which finish, kept on the device before any account exists, card backs to win or choose, and the plumbing of the mobile app's api. The catalogue stays the site's own.

**Package:** `c975l/card-bundle` · **Namespace:** `c975L\CardBundle\` · **Translation domain:** `card` (narrations in `card_narration`)

**Key source paths** (relative to the package root):
`src/Entity/`, `src/Enum/`, `src/Repository/`, `src/Service/`, `src/Contract/`, `src/EventListener/`, `src/Management/`, `src/Controller/Management/`, `config/services.yaml`

**Related documentation:** this package's `README.md` is the reference. The ecosystem's rules (configuration, management contributions, accounts) live in `c975l/core-bundle`.

**Related skills:** `c975l-management` in `c975l/core-bundle`.

## What the site does, what the bundle does

- The site owns the catalogue: its cards are its own entities (a fiche, a card of a set). The bundle names a card by its reference string and never stores it.
- The site decides which card and which finish a player wins (a quiz, a draw, a parcours reward), then calls the bundle:

```php
use c975L\CardBundle\Enum\CardFinish;

$holder = $this->cardHolder->resolve($token);
if (null !== $holder) {
    $this->cardCollector->grant($holder, $card->getReference(), CardFinish::Normal, 'quiz');
}
```

- The site keeps its own app routes and their JSON. Each starts with `CardRequest::holder()`, answers with `CardRequest::private()` and reads its body with `CardRequest::body()`.

## Rules an agent must keep

- A holding is a card and a finish, never a card of its own: never add a "shiny" card to the catalogue.
- Never tie a holding to the user by a foreign key: the key is `u<id>` for an account (`CardHolder::ofUser()`), the device's 32-hex token otherwise, so a device collects before anyone signs up.
- Never move a device's collection in a login listener: `CardHolder::resolve()` does it on every call, the token living in the device's storage that no login event sees.
- Anything else the site keeps under the holder's key (a child's Plumes) implements `HolderStoreInterface`, on its repository: it is then moved with the cards and erased with the account. Never write a second listener for it.
- `CardCollector::grant()` refuses only a finish already held, a gift sent twice at once included: the unique index refuses the second and Doctrine then closes the EntityManager, so grant last in a request. A site whose finishes form a ladder checks `CardFinish::requires()` first.
- The source passed to `grant()` is the site's own short word (20 characters at most).
- Never change the values of `CardFinish`, `BackTone` or `BackEffect`: they are stored.
- A back is given only published, through `BackCollector::grant()`, and chosen through `BackCollector::choose()`, which refuses a back unpublished or of another pack: never set `chosen` by hand. Unpublishing a back withdraws it from `BackCollector::chosen()` without clearing the choice.
- Never move a back to another pack: the back office locks it once created, its holders' choices being by pack.
- Never change a site's app route nor its JSON when moving it onto this bundle: the published apps are built against them.

## What the site plugs in

Each interface is tagged by autoconfiguration, so implementing it is all it takes:

- `HolderStoreInterface`: `hasHolder()`, `moveHolder()`, `deleteByHolder()`, for what else the site keeps under a holder's key.
- `CardCatalogInterface`: `getCardName()`, the name of a card from its reference, read by the account export.
- `CardPackProviderInterface`: `getPacks()`, the packs a back can be made for (key => name), turning the back office's pack field into a list.

- `CardExportSourceInterface`: `getExportItems(?string $target)`, the pages to export as `PdfItem` and `SocialItem`, rendered by the site's Twig and passed through `PageRenderer::onDisk()`.

## Exports

- One command, `card:export [target] [--force]`, and one dashboard tile: never write a site command or a site exporter around Chrome, Ghostscript or ffmpeg, hand the pages to `CardExportSourceInterface`.
- A page must render the same twice: no date, random id or token in it, or its fingerprint moves at every run and the export redoes it every time.
- Paths are relative to the project, outside `public/`; two items never write the same file.
- A social page exposes `still()` and `frame(t)` (seconds, 7 s at 30 fps).
- The site routes `c975L\CardBundle\Message\ExportCardsMessage` to its async transport.

## Back office

One screen, the card backs (`CardBackCrudController`), behind `site-role-editor`, with its guided project (`CardGuidedProjectProvider`, `card-back`); the export tile has its own, `card-export`, offered only where the export can run. Its listing shows the uploaded picture only: how a tone or an effect is drawn is the site's stylesheet, and a site showing it there overrides `templates/bundles/c975LCardBundle/management/field_card_back.html.twig` rather than replacing the controller.

The demo dataset (`CardDemoFixtureProvider`, UiBundle's `DemoFixtureProviderInterface`) seeds three published backs, slugs `demo-dawn`, `demo-storm` and `demo-night`, over the site's first two packs (raw `north`/`south` when it lists none), each illustrated by its `keyed_images['card/<slug>']` placeholder or one of the generic pool — no catalogue and no collection. They are sample rows, not content to build on.
