---
name: c975l-card
description: "Use this skill when working with collectible cards in a Symfony application built on the c975L ecosystem with c975l/card-bundle. Covers the collection held under a device token then an account, the three finishes, the card backs held for every pack and one chosen for all the cards, the standard back, the Back component drawing them, their thumbnails shot by a local Chrome, and the plumbing of a mobile app's card api. Triggers on: cards, card collection, collectible cards, album, CollectedCard, CardCollector, grant a card, CardFinish, finish, shiny, magic, CardHolder, holder, device token, connect a device, CardBack, CollectedBack, CardBackPicture, findImagesOfPack, back picture, back thumbnail, BackImageTrait, BackCollector, card back, choose a back, pack, sell a back, back price, BackInAppProductProvider, BackBasketItemProvider, revoke, standard back, findStandard, c975LCard:Back, Back component, card_back_standard, card-back controller, CardBackPageProviderInterface, BackThumbnailMaker, card:back-thumbnails, back thumbnails tile, BackStyleProviderInterface, BackStyles, back tone, back effect, back-canvas, c975LCard:BackChoice, BackChoice, BackImageOptions, CardDemoFixtureProvider, demo dataset, demo backs, CardRequest, app api, /app/api/cards, HolderStoreInterface, CardCatalogInterface, CardPackProviderInterface, Plumes, UserAnonymizedEvent, account export."
---

# c975L CardBundle

> Collectible cards on the c975L core — what each player holds and in which finish, kept on the device before any account exists, card backs to win or choose, and the plumbing of the mobile app's api. The catalogue stays the site's own.

**Package:** `c975l/card-bundle` · **Namespace:** `c975L\CardBundle\` · **Translation domain:** `card` (narrations in `card_narration`)

**Key source paths** (relative to the package root):
`src/Entity/`, `src/Enum/`, `src/Repository/`, `src/Service/`, `src/Contract/`, `src/Form/`, `src/EventListener/`, `src/Management/`, `src/Controller/Management/`, `src/Command/`, `src/Twig/`, `templates/components/`, `assets/`, `sass/`, `config/services.yaml`

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
- Never change the values of `CardFinish`, nor those a site's `BackStyleProviderInterface` offers: they are stored.
- A back is given only published, through `BackCollector::grant()` — except a paid one (`paid: true`, as the two Payment providers pass it), given even unpublished since, a purchase being owed — and chosen through `BackCollector::choose()`, one for all the holder's cards, which refuses a back unpublished or not held: never set `chosen` by hand. Earned or bought, a back is the same: never give it per pack. Unpublishing a back withdraws it from `BackCollector::chosen()` without clearing the choice.
- The standard back (`CardBack::$standard`, read by `CardBackRepository::findStandard()`) is the one every card wears until another is chosen, one per site: never give it, sell it nor list it among the variants; `BackCollector::choose()` on it clears the choice, as `null` does.
- A back's own thumbnail (`CardBack::$image`, card-sized, in portrait) is what the basket, the order emails and the invoice show, no pack drawing it there. A back's picture for a pack is a `CardBackPicture`, its back and pack locked once created, the pack key in letters, digits, dashes and underscores; read a pack's pictures with `CardBackPictureRepository::findImagesOfPack()`, a pack with none drawing its own picture in the back's tone.
- Draw a back with `<twig:c975LCard:Back back="{{ back }}">` (`still` for paper, `format="square"` or `--back-ratio` and `--card-width` for its size, `plain` for the shape alone), `back` holding `image`, `tone`, `effect` and optionally `label`, the site's own content inside the tag; `card_back_standard(pack)` gives the standard back in that shape. The bundle being public, a site's tones and effects stay in the site: list them with `BackStyleProviderInterface`, draw them on the classes `card-back--tone-<value>` and `card-back--<value>`, and on a canvas with a controller extending `@c975l/card-bundle/back-canvas.js`, named by `controller` on the component. Offer a back to choose with `<twig:c975LCard:BackChoice>`. Remove a back through Doctrine, never by raw SQL: its pictures go with it one by one, so that their files are deleted too.
- A back is on sale once priced and published (`CardBack::isForSale()`), at one price in the app and on the site. Never type or store a store product id: it is `CardBack::getProductId()`, derived from the slug, which is lowercase words joined by hyphens and locked once created (the back office disables it on edit).
- Never write a site provider to sell backs: `BackInAppProductProvider` (the stores) and `BackBasketItemProvider` (the basket, kind `cardback`) are the bundle's. A refund goes through `BackCollector::revoke($holder, $back, $source)`, which removes only what that source gave.
- Never change a site's app route nor its JSON when moving it onto this bundle: the published apps are built against them.

## What the site plugs in

Each interface is tagged by autoconfiguration, so implementing it is all it takes:

- `HolderStoreInterface`: `hasHolder()`, `moveHolder()`, `deleteByHolder()`, for what else the site keeps under a holder's key.
- `CardCatalogInterface`: `getCardName()`, the name of a card from its reference, read by the account export.
- `CardPackProviderInterface`: `getPacks()`, the packs a back's picture can be made for (key => name), turning the back office's pack field into a list.

- `CardBackPageProviderInterface`: `getBackPageUrl()`, the absolute url of the site's page showing a back alone, from which `BackThumbnailMaker` shoots its thumbnail.
- `CardExportSourceInterface`: `getExportItems(?string $target)`, the pages to export as `PdfItem` and `SocialItem`, rendered by the site's Twig and passed through `PageRenderer::onDisk()`.

## Exports

- The backs' thumbnails: one command, `card:back-thumbnails [slugs] [--delay]`, and one dashboard tile, both only where Chrome is installed (never on the managed production server).

- One command, `card:export [target] [--force]`, and one dashboard tile: never write a site command or a site exporter around Chrome, Ghostscript or ffmpeg, hand the pages to `CardExportSourceInterface`.
- A page must render the same twice: no date, random id or token in it, or its fingerprint moves at every run and the export redoes it every time.
- Paths are relative to the project, outside `public/`; two items never write the same file.
- A social page exposes `still()` and `frame(t)` (seconds, 7 s at 30 fps).
- The site routes `c975L\CardBundle\Message\ExportCardsMessage` to its async transport.

## Back office

Two screens, the card backs (`CardBackCrudController`) and their pictures pack by pack (`CardBackPictureCrudController`), behind `site-role-editor`, each with its guided project (`CardGuidedProjectProvider`, `card-back` and `card-back-picture`); the export and thumbnails tiles have theirs, `card-export` and `card-back-thumbnails`, offered only where each can run. The backs' listing shows nothing of their look: a site showing it there overrides `templates/bundles/c975LCardBundle/management/field_card_back.html.twig` rather than replacing the controller.

The demo dataset (`CardDemoFixtureProvider`, UiBundle's `DemoFixtureProviderInterface`) seeds three published backs, slugs `demo-dawn`, `demo-storm` and `demo-night`, their pictures over the site's first two packs (raw `north`/`south` when it lists none), each illustrated by its `keyed_images['card/<slug>']` placeholder or one of the generic pool — no catalogue and no collection. They are sample rows, not content to build on.
