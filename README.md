# CardBundle

Symfony bundle for collectible cards on the c975L core — what each player holds and in which finish, kept on the device before any account exists, card backs to win or choose, skins of the card faces pack by pack, and the plumbing of the mobile app's API. The catalogue stays the site's own.

[![GitHub](https://img.shields.io/github/license/975L/CardBundle)](https://github.com/975L/CardBundle/blob/main/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/card-bundle)](https://packagist.org/packages/c975l/card-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/card-bundle)](https://packagist.org/packages/c975l/card-bundle)

**[Bundle page](https://bundles.975l.com/en/pages/card-bundle) · [Tutorials](https://bundles.975l.com/en/tutoriels/card-bundle) · [Live demo](https://bundles.975l.com/demo/) · [Demo back-office](https://bundles.975l.com/demo/login)**

> **BUNDLE UNDER DEVELOPMENT — USE AT YOUR OWN RISK**

---

## Why CardBundle

![CardBundle](.github/images/CardBundle.svg)

Add CardBundle on top of the shared [CoreBundle](https://github.com/975L/CoreBundle) foundation when a site gives cards to collect — its own catalogue, whatever it is made of. The bundle holds the collection, never the cards: a card is named by its reference, so a site whose cards are its existing pages (the fiches of a universe) and a site with a catalogue of its own both plug in without a row being copied.

---

> **TL;DR** — `CardCollector::grant()` gives a card in a finish, `CardHolder::resolve()` says whose collection a call is about (the device's token, then the account it is connected to), `BackCollector` gives and chooses card backs, `SkinCollector` the skins pack by pack, `CardRequest` is the rate limit, token and private answer every app call starts with. The routes and their JSON stay the site's.

## Contents

- **Setup** — [requirements](#requirements) · [installation](#installation)
- **Using it** — [the collection](#the-collection) · [card backs](#card-backs) · [card skins](#card-skins) · [the app's api](#the-apps-api) · [exports](#exports) · [what the site plugs in](#what-the-site-plugs-in) · [translations](#translations) · [guided projects](#guided-projects) · [what it does not contribute](#what-this-bundle-deliberately-does-not-contribute) · [AI agent skills](#ai-agent-skills)

## Features

- A collection held under a key rather than an account: the token a device made up, then `u<id>` once a member connects it — a player collects on the first evening, before anyone signs up, and nothing is lost when they do
- Three finishes (`CardFinish`: normal ●, shiny ◆, magic ✦), the finish being no other card; the ladder (shiny only over normal) is offered, never imposed
- Card backs: variants held once for every pack, each with a tone, an effect, a picture per pack and a thumbnail of its own for the basket, given by a reward, a purchase or a code, one chosen for all the holder's cards
- Card backs drawn by the bundle (`<twig:c975LCard:Back>`): picture and frame, leant on screen and still on paper, the site laying its own content over them and drawing its own tones and effects (`BackStyleProviderInterface`); `c975l:config:check-importmap` adds `@c975l/card-bundle/controllers.js` to the site's importmap, the site declaring `@c975l/card-bundle/back-canvas.js` itself if it draws on a canvas
- A back to choose (`<twig:c975LCard:BackChoice>`): chosen, held, or padlocked, seen large in a dialog and bought from its price
- A standard back, the one every card wears until another is chosen: never sold nor given, one per site
- Card skins: the card faces in another style (toon), held, chosen and sold pack by pack, each with a picture per card, a card without one keeping its own; chosen with the same `<twig:c975LCard:BackChoice>`
- The backs' thumbnails shot from the site's page showing each alone (`card:back-thumbnails`, or a dashboard tile where Chrome is installed)
- Card backs for sale, in the app stores and on the site, through PaymentBundle (`InAppProductProviderInterface` and `BasketItemProviderInterface`), at one price
- A device's collection handed over to the account on its first connected call, the account keeping its own where both hold the same card
- Everything erased with the account (ConfigBundle's `UserAnonymizedEvent`), the site's own stores included
- Every card, back and skin in the member's data export (ConfigBundle's `/account/export`, through `AccountDataProviderInterface`)
- One export of the cards, paper and social networks alike, that only redoes what changed since the last run (`card:export`, or a dashboard tile where Chrome is installed)
- Four back-office screens contributed to the EasyAdmin dashboard (`MenuProviderInterface`): the card backs and their pictures pack by pack, the skins and their pictures card by card
- Guided projects (`GuidedProjectProviderInterface`): making a card back, giving it its picture for a pack, making a skin, giving it its picture for a card, exporting the cards
- A demo dataset (UiBundle's `DemoFixtureProviderInterface`): three card backs, their pictures over the site's first two packs, each illustrated by its own placeholder when the site declares one under `keyed_images['card/<slug>']` (`demo-dawn`, `demo-storm`, `demo-night`), one of the generic pool otherwise — no catalogue, no collection and no skin, a skin's picture needing a card reference only the site knows
- Its own `card` translation catalogue, in English, French and Spanish
- **A skill for coding agents**, shipped in the package and read straight from `vendor/` — see [AI agent skills](#ai-agent-skills)

---

## Requirements

- PHP >= 8.4, Symfony 8
- [c975L/CoreBundle](https://github.com/975L/CoreBundle) — ConfigBundle and UiBundle in one package: user accounts, the dashboard, the media uploads, the device token the favorites already use

---

## Installation

### Download

```bash
composer require c975l/card-bundle
```

### Run migrations

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Seven tables: `card_collected`, `card_back`, `card_back_picture`, `card_collected_back`, `card_skin`, `card_skin_picture` and `card_collected_skin`. No foreign key ties a holding to an account: the key is `u<id>`, erased by the bundle when the account goes.

### Upgrading to v1.3

A back now holds for every pack, its pictures moving to `card_back_picture`, and a holder chooses one back for all their cards. Before running `migrate`, edit the generated migration so that, after the `CREATE TABLE card_back_picture` and before the `ALTER TABLE card_back` dropping `pack` and `image`, it carries the pictures over and keeps only each holder's latest choice:

```php
// The pictures already uploaded, their files staying where they are
$this->addSql("INSERT INTO card_back_picture (back_id, pack, slug, image, modification) SELECT id, pack, CONCAT(slug, '-', LOWER(pack)), image, modification FROM card_back WHERE image IS NOT NULL");

// One chosen back per holder, the latest, where they chose one per pack
$this->addSql('UPDATE card_collected_back c LEFT JOIN (SELECT holder, MAX(id) AS id FROM card_collected_back WHERE chosen = 1 GROUP BY holder) k ON k.id = c.id SET c.chosen = 0 WHERE c.chosen = 1 AND k.id IS NULL');
```

### Upgrading to v1.5

`BackTone`, `BackEffect`, their styles and their drawing leave the bundle, which is public, and the stored values stay as they are, with no migration. A site using them moves them to its own code: a `BackStyleProviderInterface` listing every value its backs hold, since one it no longer lists shows empty in the listing and has to be chosen again on saving the back; the `card-back--tone-<value>` and `card-back--<value>` classes in its stylesheet; its canvas in a controller extending `@c975l/card-bundle/back-canvas.js`, named by `controller` on the component; its copy of `sky.js` and `audio.js`, if it uses them. `--back-rays` and the component's `sky` are gone with them, and `back.tone` and `back.effect` are now plain strings, no longer enums.

### Upgrading to v1.6

The skins add three tables, `card_skin`, `card_skin_picture` and `card_collected_skin`, which `doctrine:migrations:diff` writes as they are: nothing to edit by hand. `<twig:c975LCard:BackChoice>` keeps posting `back` for a back, `kind` and `pack` being new and optional.

### Nothing to register by hand

No route to import, no config key: the menu entry, the guided projects, the account export, the anonymization listener and the site's own implementations (see [what the site plugs in](#what-the-site-plugs-in)) are picked up by autoconfiguration. A back's or a skin's picture goes through UiBundle's `block_media` upload mapping, under `public/medias/cards/backs/` and `public/medias/cards/skins/`, at the size of a card face (800 px, its thumbnail 500).

---

## The collection

```php
use c975L\CardBundle\Enum\CardFinish;
use c975L\CardBundle\Service\CardCollector;
use c975L\CardBundle\Service\CardHolder;

$holder = $this->cardHolder->resolve($token);           // null: neither an account nor a usable token
$collected = $this->cardCollector->grant($holder, 'GUE 02', CardFinish::Shiny, 'quiz');
// null when that finish is held already, a gift sent twice at once included
```

Which card and which finish is the site's decision, taken before calling: a site giving a finish only over the one below checks `CardFinish::requires()` against `CollectedCardRepository::finishesByReference()` itself. The source (`'quiz'`, `'draw'`, `'app'`) is the site's own word, kept so a collection can tell its ways in apart. The same gift sent twice at once is refused by the unique index rather than answered with a 500, but Doctrine then closes the EntityManager: nothing else is written in that request.

`CardHolder::ofUser($user)` is the key of an account, `CardHolder::isAccount()` whether the caller is signed in to a saved account.

## Card backs

A `CardBack` is a variant held once for every pack — the packs being the site's own keys: a castle's prefix, a set's slug — and only a published one is ever given. Earned or bought, it is the same back: `BackCollector::grant($holder, $back, $source)` gives it, `BackCollector::choose($holder, $back)` makes it the one all the holder's cards wear (`null` for the standard back), refusing a back not held or unpublished, and `BackCollector::chosen($holder)` returns it. Unpublishing a back withdraws it: it is no longer worn, the choice being kept so that republishing it puts it back. A back's slug is set on creation and locked afterwards, lowercase words joined by hyphens, and the backs are read by position, those given none last.

A back's picture for a pack is a `CardBackPicture` — that castle at dawn — uploaded on its own screen, its back and pack locked once created and the pack key in letters, digits, dashes and underscores, removed with its back along with its files; `CardBackPictureRepository::findImagesOfPack($pack)` reads a pack's pictures in one query, and a pack with none draws its own picture in the back's tone. Any tone goes with any effect. A site drawing its backs in the back office's listing as its app does overrides `templates/bundles/c975LCardBundle/management/field_card_back.html.twig`, which shows nothing otherwise.

### The standard back

The back marked standard is the one every card wears until its holder chooses another, one per site: ticking it on a back unticks the other. It is never sold nor given, `BackCollector::choose()` on it clears the choice, and it is left out of the variants listed, sold or shot. `CardBackRepository::findStandard()` reads it, null while none is published.

### Drawing a back

`<twig:c975LCard:Back back="{{ back }}">` draws a back — its picture, its frame, its lean under the pointer (Stimulus controller `card-back`) — the site laying its own content (a crest, a name) inside the tag; `still` draws it without any controller, for paper. `back` holds `image`, `tone`, `effect` and optionally `label`; `card_back_standard(pack)` gives the standard back in that shape, null while there is none. The stylesheet and the controller are loaded by UiBundle's layout, the site setting the frame's colours through `--back-frame`, `--back-light` and `--back-shade`.

`format="square"` (or `--back-ratio`) and `--card-width` size it, a memory game's tile for one; `plain` keeps the shape alone, without picture, rule, frame, lean nor controller, its look then all the site's.

The tones and effects are the site's own, the bundle being public: a `BackStyleProviderInterface` lists those the back office offers, each value (10 characters at most, stored) becoming the classes `card-back--tone-<value>` and `card-back--<value>` for the site's stylesheet. To draw on a canvas, the site's Stimulus controller extends `@c975l/card-bundle/back-canvas.js`, writing `paint(context, time)`, and is named by `controller="..."` on the component; the site's `importmap.php` declares it: `'@c975l/card-bundle/back-canvas.js' => ['path' => '@c975l/card-bundle/js/back-canvas.js']`.

`<twig:c975LCard:BackChoice choice="{{ choice }}" name="..." chooseUrl="...">` shows one back to choose from, the site's drawing of it in the tag, inside a `<div class="card-back-choices" data-controller="basket">`: chosen, held, or padlocked, seen large in a dialog and bought from its price, in the shop's currency unless `currency` names another. `choice` holds `slug`, `held`, `chosen` and, not held, `forSale`, `price` (in cents) and `id`; a tap on a held back posts `back` and `_token` to `chooseUrl`, for the site to check as the CSRF token `submit` and pass to `BackCollector::choose()`.

### The backs' thumbnails

A site implementing `CardBackPageProviderInterface` — the absolute url of its page showing a back alone — gets each published variant's thumbnail shot there by a headless Chrome, after a few seconds for its effect to move, and stored as an upload would be: `php bin/console card:back-thumbnails [slugs…] [--delay=4000]`, or the dashboard tile. Both exist only where Chrome is installed, the local machine and not a managed server; elsewhere the thumbnails are uploaded by hand.

### Selling a back

A back given a price (in cents, VAT included, with its own VAT rate, none under the VAT franchise) and published is on sale, at that one price in the app and on the site: 1,00 € and 2,00 € are both price points of the stores. Its store product id is derived from its slug, `back.<slug>` with underscores for hyphens (`CardBack::getProductId()`, see PaymentBundle's `InAppProduct::idFor()`), never typed, which is why the slug never changes once created. A purchase is owed: `BackCollector::grant(..., paid: true)` gives a bought back even one unpublished since, and a basket paid for a back the account earned in the meantime is logged as a warning for the admin to refund.

- **In the app**, `BackInAppProductProvider` is PaymentBundle's `InAppProductProviderInterface`: `c975l:payment:store:publish` creates the products from it, named « Card back “variant” » in each language (the variant alone past Apple's 30 characters), one product for all the packs, and a verified purchase gives the back through `BackCollector::grant()` under the store's name, `apple` or `google`, to an account only (`u<id>`): a device's token moves to the account it signs in to, where a store refund would no longer find it, so a purchase sent for a device is logged and not given. A refund takes it back through `BackCollector::revoke()`, which removes only what that store gave: the same back won in a quest stays. The site's own app endpoints still ask for the account token and send the proofs (see PaymentBundle's *selling in a mobile app*).
- **On the site**, `BackBasketItemProvider` puts a back in PaymentBundle's basket under the `cardback` kind: once per back, refused when the account already holds it, the visitor asked to sign in at the checkout (`AccountBasketItemProviderInterface`). It is digital content supplied at once (`Basket::CONTENT_FLAG_DIGITAL`), which asks for the waiver of the right of withdrawal, and once paid it is given as `shop`, taken back on a full refund (`RefundableBasketItemProviderInterface`), a basket in test mode giving nothing. A basket already an order whose account is gone (a shared order paid by a third party) is refused, never landing on whoever is signed in. Where the site shows the button is its own: a back is bought where its collection shows it, adding `{kind: cardback, id}` to the basket.

## Card skins

A `CardSkin` dresses the card faces in another style — toon, sketch — and, unlike a back, is held pack by pack: a castle's skin dresses that castle's cards, and the next castle's is won or bought on its own. Its picture for one card is a `CardSkinPicture`, named by the card's reference (`GOB 01`) and filed under the card's pack, its skin, pack and card locked once created: the packs a skin dresses are those of its pictures, so the bundle never asks which pack a card belongs to. A card with no picture keeps its own, and a finish still changes the frame and the numbers, never the skin.

```php
use c975L\CardBundle\Service\SkinCollector;

$this->skinCollector->grant($holder, $toon, 'GOB', 'quest'); // false: not published, held already, or no picture in that pack
$this->skinCollector->choose($holder, 'GOB', $toon);         // that pack's cards wear it, the other packs keep theirs; null for their own pictures
$chosen = $this->skinCollector->chosen($holder);             // ['GOB' => $toon], one query for the whole collection
$images = $this->cardSkinPictureRepository->findImagesOfPack($toon, 'GOB'); // ['GOB 01' => 'medias/cards/skins/toon-gob-01.webp', ...]
```

A device's skins move to the account as its backs do, the account's choice winning pack by pack, and go with the account.

### Choosing a skin

`<twig:c975LCard:BackChoice kind="skin" pack="GOB" choice="{{ choice }}" name="..." chooseUrl="...">` offers a skin for one pack, as a back is offered: a tap on a held skin posts `skin`, `pack` and `_token` to `chooseUrl`, for the site to check as the CSRF token `submit` and pass to `SkinCollector::choose()`; one not held is seen large and bought, `choice.id` being its store product id.

### Selling a skin

A skin given a price and published is on sale in every pack it dresses, at that one price per pack. Its store product id is derived from its slug and the pack, `skin.<slug>.<pack>` in lowercase with underscores (`CardSkin::getProductId($pack)`, `skin.toon.gob`), never typed; `CardSkinRepository::findByProductId()` reads it back into the skin and the pack.

- **In the app**, `SkinInAppProductProvider` publishes one product per skin and pack, named « Skin “Toon” Gobelins » in each language (the skin and the pack alone past Apple's 30 characters), and a verified purchase gives the skin for that pack under the store's name, even once its pictures there are gone, one that could not be given being logged, an account only being given one as for a back; a refund takes back only what that store gave, found through the packs it was given in. Two pack keys giving the same product id (« GOB » and « gob ») stop `c975l:payment:store:publish`, naming both.
- **On the site**, `SkinBasketItemProvider` puts a skin for one pack in the basket under the `cardskin` kind, its item id being the store product id: once per pack, refused when the account already holds it there, given as `shop` once paid and taken back on a full refund, as for a back.

## The app's api

The routes and their JSON stay in the site's controllers — an app's api is what it is (an album and its Plumes, a collection and its packs) and its payload is what the app was built against. What every call shares is `CardRequest`:

```php
use c975L\CardBundle\Service\CardRequest;

#[Route('/app/api/cards/collection', methods: ['POST'])]
public function collection(Request $request, CardRequest $cardRequest): Response
{
    $holder = $cardRequest->holder($request);   // a 429 Response over UiBundle's favorites limit
    if ($holder instanceof Response) {
        return $holder;
    }

    return CardRequest::private(new JsonResponse($this->presenter->collection($holder)));
}
```

`CardRequest::body($request)` is the json body, empty when it does not parse.

## Exports

One export, `php bin/console card:export [target] [--force]` or the dashboard tile (shown only where Chrome and Ghostscript are installed — the local machine, not a managed server — and run by the Messenger worker). The site hands its pages over through `CardExportSourceInterface`, rendered by its own Twig under its own stylesheets, the bundle only shooting them:

- `PdfItem($path, $html, $faces)`: a document Chrome prints to PDF — a booklet, a plate, a series of printer-ready cards — and, with faces, one image per page at 600 dpi (Ghostscript) in the folder named after it, a face left `null` being a page dropped
- `SocialItem($path, $postHtml, $reelHtml, $manifest, $entry)`: one card in one finish, a 1080x1350 image and a 7-second 1080x1920 video (Chrome held open, ffmpeg), the page exposing `still()` and `frame(t)`; the manifest lists every card for the posts' drafts

`PageRenderer` gives the source what a page opened from disk needs: `stylesheets($paths, $screen)` (urls put onto the disk, the print rules dropped on screen) and `onDisk($html)` (images loaded eagerly, site and AssetMapper paths put onto the disk).

It is idempotent: each item's fingerprint — its pages, and the date and size of every file they read — is kept in `Construction/card-export.json`, and an item whose fingerprint has not moved, its files still there, is left as it is. The first run shoots everything; the next one only what was edited. A full run also removes the files of what the site no longer exports; a target (read by the site: a series, a card) never removes anything. The site routes `c975L\CardBundle\Message\ExportCardsMessage` to its async transport, or the tile runs inside the request, with no time limit. Only one run at a time: a second, from the tile or the terminal, is refused while one is under way.

## What the site plugs in

Six interfaces, each tagged by autoconfiguration — implementing one is all it takes:

| Interface | What for | Without it |
| --- | --- | --- |
| `HolderStoreInterface` | whatever else the site keeps under a holder's key (a child's Plumes), moved and erased with the cards | only the cards, the backs and the skins move |
| `CardCatalogInterface` | the name of a card, from its reference | the reference is shown |
| `CardPackProviderInterface` | the packs a back's or a skin's picture can be made for | the pack is typed in the back office |
| `CardExportSourceInterface` | the pages to export, paper and social (see [exports](#exports)) | no tile, `card:export` has nothing to do |
| `CardBackPageProviderInterface` | the page showing a back alone (see [the backs' thumbnails](#the-backs-thumbnails)) | the thumbnails are uploaded by hand |
| `BackStyleProviderInterface` | the tones and effects the back office offers, which the site draws (see [drawing a back](#drawing-a-back)) | a back has neither |

## Translations

Everything the back office shows is in the `card` domain, its spoken narrations in `card_narration`, both in English, French and Spanish.

---

## Guided projects

The bundle contributes six guided projects, in the 11000 block `GuidedProjectProviderInterface` reserves it. `card-back` makes a variant of the backs: it opens on the card backs' listing, then walks the form field by field — the name, the tone, the effect, the thumbnail, the price when it is for sale — then the box that publishes it, a back being given only once published, the box marking it standard, and ends on the save button. `card-back-picture` gives a back its picture for one pack: the back, the pack, the picture, then the save button. `card-skin` makes a skin the same way — the name, the thumbnail, the price, the box that publishes it — and `card-skin-picture` gives it its picture for one card: the skin, the pack, the card's reference, the picture. All four are offered to `site-role-editor`, the role the screens themselves ask for.

`card-export` opens on the dashboard, highlights the export tile, then tells how to follow the run on the tile. Like the tile, it is offered only where the export can run, to `ROLE_SUPER_ADMIN`. `card-back-thumbnails` does the same for the thumbnails' tile, offered only where they can be made.

The ways a back is then *had* — a reward, a purchase, a code — are not walked: a reward and a code are the site's own code calling `BackCollector::grant()`, a purchase PaymentBundle's, with nothing to do in the back office beyond the price.

---

## What this bundle deliberately does not contribute

ConfigBundle and UiBundle expose a long list of contribution points, and not branching one is a valid answer — it is only worth writing down:

| Point | Why not |
| --- | --- |
| Config keys (`configs.json`) | it reads only the core's `site-role-editor`, and the limit is UiBundle's favorites one |
| "What's new" (`whatsnew.json`) | prose to be written when the bundle is actually deployed somewhere, not before — its guided projects are written, see above |
| Maintenance task | nothing here runs on a schedule: a card is given by the site's own call, and a collection only goes with its account |
| The catalogue | the cards are the site's own entities, named here by reference |
| Routes | an app's api is the site's own: its payload is what the app was built against — the only route is the export tile's |
| Account section | "My collection" counts and links to the site's own collection page |
| Blocks | a collection is shown by the site's own pages and app |
| Emails, sitemap, feeds | no public page and nothing to announce of its own |
| Content translation | a back's name is short and the site's; translated catalogues are the site's |
| Content import / export | a collection is what players did, not content |

---

## AI agent skills

The package ships a skill of its own, `skills/c975l-card/SKILL.md`, written for the coding agent of the site installing this bundle rather than for someone modifying it. Point your agent at it:

```text
vendor/c975l/card-bundle/skills/
```

It holds what an agent gets wrong when left to its own habits — that a shiny card is the same card in another finish and never a catalogue entry of its own, that a holding is tied to no account by a foreign key, that a device's collection moves on every call rather than in a login listener, that what else a site keeps under the holder's key implements `HolderStoreInterface` instead of a second listener, and that the stored values of the enums and an app's routes never change — alongside the services, the entities and the interfaces, each named as it actually is in the sources.

Nothing is installed, nothing is copied into your project: the file sits in `vendor/` like any other part of the package and follows it at each `composer update`. A user of Claude Code wanting it to load by itself symlinks it into their own skills directory:

```bash
ln -s ../../vendor/c975l/card-bundle/skills/c975l-card .claude/skills/c975l-card
```

`Tests\SkillsTest` keeps the file honest: every path, route, config slug, command, class member, Twig function, block kind and component it quotes is checked against the sources, so renaming any of them fails the build rather than leaving an agent confidently wrong.

---

> [!TIP]
> If this project **helps you save development time**:
>
> - [**star** it on GitHub](https://github.com/975L/CardBundle) — helps others find it
> - [**open an issue**](https://github.com/975L/CardBundle/issues/new) to share how you use it — genuinely useful feedback
>
> And if you'd like to support the work directly, the **Sponsor** button at the top of the GitHub page is there for that. Thank you!
