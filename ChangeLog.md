# Changelog

## v1.4.1

A card back has its own thumbnail, shown by the basket

- `CardBack` thumbnail, its image on its own, shown by the basket, the order emails and the invoice (31/10/2026) **Needs db update**
- `BackImageTrait`, the image a back and its picture for a pack share (31/10/2026)
- Guided project `card-back` asks for the thumbnail (31/10/2026)
- Requires PaymentBundle ^6.22.3, which draws a line's picture with no page of its own (31/10/2026)
- Fixed the export of an AssetMapper file whose digest holds a dash: read from its source rather than left as a broken image (31/10/2026)

## v1.4

Any tone of a card back goes with any effect

- `CardBack` no longer refuses a tone and an effect drawn on the same layer (31/10/2026)
- `BackTone::drawsOverPicture()` and `BackEffect::drawsOverPicture()` removed (31/10/2026) **Breaking change**
- Guided project `card-back` no longer warns of a refused tone and effect (31/10/2026)

## v1.3.0

A card back held for every pack, one chosen for all the cards

- `CardBack` is a variant held once for every pack, earned or bought alike: its `pack` and its picture are gone, see the README's *Upgrading to v1.3* (31/10/2026) **Breaking change** **Needs db update**
- `CardBackPicture`: a back's picture for one pack, on its own back-office screen with its guided project, `card-back-picture` (31/10/2026)
- `BackCollector::choose($holder, $back)` and `chosen($holder)`: one back for all the holder's cards, no longer one per pack (31/10/2026) **Breaking change**
- `CardBackRepository::findPublished()` replaces `findPublishedOfPack()`, `CardBackPictureRepository::findImagesOfPack()` reads a pack's pictures (31/10/2026) **Breaking change**
- A store product and a basket line sell a back for all the packs, named « Card back “variant” » (31/10/2026)
- `CardCatalog::packName()` and `backName()` removed, a back being named by its own name (31/10/2026) **Breaking change**
- The device's chosen back is carried to the account only when the account chose none (31/10/2026)
- The device's chosen back is carried to the account one at most, even from several legacy choices (31/10/2026)
- `CardBackPicture`: the pack key in letters, digits, dashes and underscores, a file required on creation (31/10/2026)
- `CardBack::$pictures`: a back's pictures removed with it, their files included (31/10/2026)
- Guided project `card-back` points to the pictures and no longer speaks of one pack (31/10/2026)

## v1.2.0

Card backs for sale, in the app stores and on the site

- `CardBack::$price` and `$vat`: a back priced and published is on sale, the same price in the app and on the site; `productId` is now derived from the slug, `back.<slug>`, and no longer stored (30/10/2026) **Needs db update**
- `BackInAppProductProvider`: PaymentBundle's `InAppProductProviderInterface`, the backs on sale as the stores sell them, given under the store's name and taken back on a refund (30/10/2026)
- `BackBasketItemProvider`: PaymentBundle's basket, a back sold once to an account as digital content, given as `shop` once paid (30/10/2026)
- `BackCollector::revoke()`: takes back a back only the given source gave (30/10/2026)
- `CardBackRepository::findForSale()` and `findByProductId()` (30/10/2026)
- `CardCatalog::packName()` and `backName()`: the account export names a back by its pack as the site calls it, « Hurlton - À l'aube » rather than « hur - À l'aube » (30/10/2026)
- Back office: the price and the VAT replace the store product field, and the `card-back` guided project walks the price and its VAT (30/10/2026)
- `CardBack::$slug`: lowercase words joined by hyphens, locked once created (30/10/2026)
- `BackCollector::grant()` takes `paid`: a bought back is given even if unpublished since (30/10/2026)
- `BackBasketItemProvider` logs a paid back it could not give (30/10/2026)
- `CardCatalog::currency()`, and the packs read once per request (30/10/2026)
- `card:export` stopped by Ctrl+C or a kill releases its run, the next one no longer refused (30/10/2026)
- The `card-back` guided project highlights the picture's file input, `#CardBack_imageFile_file`, the Vich field itself carrying no id (30/10/2026)
- Requires `c975l/core-bundle` ^1.82 and `c975l/payment-bundle` ^6.22 (30/10/2026)

## v1.1.0

One export of the cards, paper and social networks, redoing only what changed

- Added `card:export [target] [--force]` and its dashboard tile, run by the Messenger worker (30/10/2026)
- Added `CardExportSourceInterface`, the site handing over `PdfItem` and `SocialItem` pages (30/10/2026)
- Added `PageRenderer` and `ChromeSession`, moved from Papa Câlin and La Guilde (30/10/2026)
- Added `ExportState`, keeping each item's fingerprint so an unchanged one is not shot again (30/10/2026)
- Added `ExportStatus`, where the tile reads an export under way, waiting or failed (30/10/2026)
- Added the `card-export` guided project, offered where the export can run (30/10/2026)
- Requires `symfony/console`, `symfony/filesystem`, `symfony/messenger` and `symfony/process` (30/10/2026)

## v1.0.1

Demo card backs

- `CardDemoFixtureProvider`: three published backs over the site's first two packs, for the bundles' demo (30/10/2026)
- Demo backs illustrated by their `card/<slug>` placeholder, else one of the pool picked by slug (30/10/2026)
- Demo backs copied through UiBundle's `DemoFixtureMediaCopier` (30/10/2026)
- Requires `c975l/core-bundle` ^1.81.3 (30/10/2026)
- README and skill `c975l-card`: the demo dataset (30/10/2026)

## v1.0.0

Create the bundle: card collection, card backs and app api plumbing

- `CollectedCard`, `CardCollector`, `CardFinish`: a card held in a finish under a holder's key, the catalogue being the site's own (30/10/2026) **Needs db update**
- `CardHolder`: the device's token, then the account it is connected to, every `HolderStoreInterface` handed over on the way (30/10/2026)
- `CardBack`, `CollectedBack`, `BackCollector`: variants of a pack's back, given and chosen one per pack, with their back-office screen and guided project (30/10/2026) **Needs db update**
- `CardRequest`: the rate limit, the token, the json body and the private answer of every app call (30/10/2026)
- The collection erased with the account and exported with its data (30/10/2026)
- `CardCatalogInterface` and `CardPackProviderInterface`, tagged, for the site's own catalogue (30/10/2026)
