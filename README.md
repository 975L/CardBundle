# CardBundle

Symfony bundle for collectible cards on the c975L core — what each player holds and in which finish, kept on the device before any account exists, card backs to win or choose, and the plumbing of the mobile app's API. The catalogue stays the site's own.

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

> **TL;DR** — `CardCollector::grant()` gives a card in a finish, `CardHolder::resolve()` says whose collection a call is about (the device's token, then the account it is connected to), `BackCollector` gives and chooses card backs, `CardRequest` is the rate limit, token and private answer every app call starts with. The routes and their JSON stay the site's.

## Contents

- **Setup** — [requirements](#requirements) · [installation](#installation)
- **Using it** — [the collection](#the-collection) · [card backs](#card-backs) · [the app's api](#the-apps-api) · [what the site plugs in](#what-the-site-plugs-in) · [translations](#translations) · [guided project](#guided-project) · [what it does not contribute](#what-this-bundle-deliberately-does-not-contribute) · [AI agent skills](#ai-agent-skills)

## Features

- A collection held under a key rather than an account: the token a device made up, then `u<id>` once a member connects it — a player collects on the first evening, before anyone signs up, and nothing is lost when they do
- Three finishes (`CardFinish`: normal ●, shiny ◆, magic ✦), the finish being no other card; the ladder (shiny only over normal) is offered, never imposed
- Card backs: variants of a pack's back, with a picture, a tone and an effect, given by a reward, a purchase or a code, and chosen one per pack
- A device's collection handed over to the account on its first connected call, the account keeping its own where both hold the same card
- Everything erased with the account (ConfigBundle's `UserAnonymizedEvent`), the site's own stores included
- Every card and back in the member's data export (ConfigBundle's `/account/export`, through `AccountDataProviderInterface`)
- One back-office screen contributed to the EasyAdmin dashboard (`MenuProviderInterface`): the card backs
- A guided project (`GuidedProjectProviderInterface`): making a card back
- A demo dataset (UiBundle's `DemoFixtureProviderInterface`): three card backs over the site's first two packs, each illustrated by its own placeholder when the site declares one under `keyed_images['card/<slug>']` (`demo-dawn`, `demo-storm`, `demo-night`), one of the generic pool otherwise — no catalogue and no collection, both being a site's own
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

Three tables: `card_collected`, `card_back` and `card_collected_back`. No foreign key ties a holding to an account: the key is `u<id>`, erased by the bundle when the account goes.

### Nothing to register by hand

No route to import, no config key: the menu entry, the guided project, the account export, the anonymization listener and the site's own implementations (see [what the site plugs in](#what-the-site-plugs-in)) are picked up by autoconfiguration. A back's picture goes through UiBundle's `block_media` upload mapping, under `public/medias/cards/backs/`.

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

A `CardBack` belongs to a pack — the site's own key: a castle's prefix, a set's slug — and only a published one is ever given. `BackCollector::grant($holder, $back, $source)` gives it, `BackCollector::choose($holder, $pack, $back)` makes it the one that pack's cards wear (`null` for the pack's own picture), refusing a back not held, unpublished or of another pack, and `BackCollector::chosen($holder)` lists the choices by pack. Unpublishing a back withdraws it: it is no longer worn, the choice being kept so that republishing it puts it back. A back's pack is set on creation and locked afterwards, and a pack's backs are read by position, those given none last. How a tone or an effect is drawn is the site's stylesheet and its app's: the bundle only names them (`BackTone`, `BackEffect`) and refuses the two drawn on the same layer. The back office lists each back by its uploaded picture; a site drawing its backs there as its app does overrides `templates/bundles/c975LCardBundle/management/field_card_back.html.twig`.

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

## What the site plugs in

Three interfaces, each tagged by autoconfiguration — implementing one is all it takes:

| Interface | What for | Without it |
| --- | --- | --- |
| `HolderStoreInterface` | whatever else the site keeps under a holder's key (a child's Plumes), moved and erased with the cards | only the cards and the backs move |
| `CardCatalogInterface` | the name of a card, from its reference | the reference is shown |
| `CardPackProviderInterface` | the packs a back can be made for | the pack is typed in the back office |

## Translations

Everything the back office shows is in the `card` domain, its spoken narrations in `card_narration`, both in English, French and Spanish.

---

## Guided project

The bundle contributes one guided project, in the 11000 block `GuidedProjectProviderInterface` reserves it: `card-back`, making a variant of a pack's back. It opens on the card backs' listing, then walks the form field by field — the pack, the name, the picture, the tone, the effect — then the box that publishes it, a back being given only once published, and ends on the save button. It is offered to `site-role-editor`, the role the screen itself asks for.

The ways a back is then *had* — a reward, a purchase, a code — are not walked: they are the site's own code calling `BackCollector::grant()`, with nothing to do in the back office.

---

## What this bundle deliberately does not contribute

ConfigBundle and UiBundle expose a long list of contribution points, and not branching one is a valid answer — it is only worth writing down:

| Point | Why not |
| --- | --- |
| Config keys (`configs.json`) | it reads only the core's `site-role-editor`, and the limit is UiBundle's favorites one |
| "What's new" (`whatsnew.json`) | prose to be written when the bundle is actually deployed somewhere, not before — its guided project is written, see above |
| Maintenance task | nothing here runs on a schedule: a card is given by the site's own call, and a collection only goes with its account |
| The catalogue | the cards are the site's own entities, named here by reference |
| Routes | an app's api is the site's own: its payload is what the app was built against |
| Account section | "My collection" counts and links to the site's own collection page |
| Blocks | a collection is shown by the site's own pages and app |
| Emails, sitemap, feeds | no public page and nothing to announce of its own |
| Content translation | a back's name is short and the site's; translated catalogues are the site's |
| Import / export | a collection is what players did, not content |
| Paper and social exports | planned for v1.1, against `CardCatalogInterface` |
| Sales of backs | planned, through PaymentBundle's basket and the app stores (`CardBack::productId`) |

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
