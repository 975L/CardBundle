# Changelog

## v1.6.1

Backs and skins taken back on refund, held ones never deleted

- Backs and skins bought on the site taken back on a full refund, a test basket giving nothing (12/11/2026)
- A shared order whose account is gone refused at the checkout instead of landing on the payer (12/11/2026)
- A back or a skin someone holds refused at deletion, the batch delete removed (12/11/2026)
- A store purchase given to an account only, never to a device's token (12/11/2026)
- Two packs giving one skin product id stop the stores' publishing (12/11/2026)
- A back or a skin paid but gone, or a store back not given, logged for the admin (12/11/2026)
- `card_back_standard()` reads the standard back and each pack's picture once per request (12/11/2026)
- `HolderStoreInterface::moveHolder()` recounts the daily caps against the account's (12/11/2026)
- Requires c975l/core-bundle ^1.90 and c975l/payment-bundle ^6.26 (12/11/2026)

## v1.6.0

Card skins held, chosen and sold pack by pack

- `CardSkin`, `CardSkinPicture` and `CollectedSkin`: skins of the card faces, a picture per card, held pack by pack (10/11/2026)
- `SkinCollector` gives, takes back and chooses a skin for one pack, `chosen()` reading every pack's in one query (10/11/2026)
- A device's skins moved to the account and erased with it, the account's choice winning pack by pack (10/11/2026)
- Skins sold per pack in the app stores (`SkinInAppProductProvider`) and on the site (`SkinBasketItemProvider`, kind `cardskin`) (10/11/2026)
- `<twig:c975LCard:BackChoice kind="skin" pack="...">` offers a skin for one pack (10/11/2026)
- Back-office screens for the skins and their pictures, each with its guided project (10/11/2026)
- Skins in the member's data export (10/11/2026)
- `CardCatalog::packName()` names a pack, its key when the site lists none (10/11/2026)

## v1.5.3

Composer states why a PHP version failed to resolve

- `bin/ci.sh`: Composer states why a PHP version failed to resolve (03/11/2026)

## v1.5.2

CI tests PHP 8.5 and 8.6, bin/ci.sh replays them locally

- CI runs the tests on PHP 8.5 and 8.6 besides the quality job on 8.4 (03/11/2026)
- `bin/ci.sh` runs the tests on each other PHP version installed locally (03/11/2026)
- `bin/ci.sh` installs the latest lizard instead of 1.17.31, as the CI does (03/11/2026)

## v1.5.1

Corrected stale ci.sh comment

- Corrected `bin/ci.sh` comment and message: vendor stays at its composer.lock (02/11/2026)

## v1.5

Card backs' tones and effects left to the sites

- `BackTone`, `BackEffect`, their styles and drawing, `sky.js` and `audio.js` removed, see the README's *Upgrading to v1.5* (01/11/2026) **Breaking change**
- `CardBack::getTone()` and `getEffect()` are strings, the stored values unchanged (01/11/2026) **Breaking change**
- `--back-rays` and the `sky` of the `c975LCard:Back` component removed (01/11/2026) **Breaking change**
- `BackStyleProviderInterface`, the tones and effects a site offers in the back office (01/11/2026)
- `assets/js/back-canvas.js`, the controller a site extends to draw on a back's canvas (01/11/2026)
- `controller`, `format` and `plain` on the `c975LCard:Back` component (01/11/2026)
- Twig component `c975LCard:BackChoice`: a back to choose, or padlocked, seen large and bought from its price (01/11/2026)
- `BackImageOptions`: a back's thumbnail and pictures take a jpeg, png, webp or gif only (01/11/2026)
- The demo's backs left without tone nor effect (01/11/2026)
- `eslint.config.mjs` and `.stylelintrc.json`, read by Codacy and by `GitPush.sh` (01/11/2026)

## v1.4.2

Card backs drawn by the bundle, a standard one worn by every card

- `CardBack::$standard`, the back every card wears until another is chosen (31/10/2026) **Needs db update**
- One standard back per site, the one saved as such unmarking the others (31/10/2026)
- The standard back is never sold nor given, chosen as none, left out of the variants (31/10/2026)
- `CardBackRepository::findStandard()` (31/10/2026)
- Guided project `card-back` asks whether it is the standard back (31/10/2026)
- Twig component `c975LCard:Back`, a card back drawn and moving, the site laying its own content over it (31/10/2026)
- `still` on the `c975LCard:Back` component, for paper (31/10/2026)
- Stimulus controller `card-back` (`assets/js/back.js`) drawing the backs' effects (31/10/2026)
- The storm's thunder and the wind's gusts heard and felt, in `assets/js/audio.js` (31/10/2026)
- `assets/js/audio.js` and `assets/js/sky.js` importable by a site as `@c975l/card-bundle/audio.js` and `@c975l/card-bundle/sky.js` (31/10/2026)
- Stylesheet `sass/_back.scss`, its colours set by the site through `--back-frame`, `--back-light`, `--back-shade` and `--back-rays` (31/10/2026)
- `AssetProvider`, the backs' controller and stylesheet loaded by UiBundle's layout (31/10/2026)
- `ImportmapProvider`, the importmap entry `c975l:config:check-importmap` expects (31/10/2026)
- Twig function `card_back_standard(pack)`, the standard back as the component draws it (31/10/2026)
- `CardBackPageProviderInterface`, the site's page showing a back alone (31/10/2026)
- `BackThumbnailMaker`, the backs' thumbnails shot from that page by a local Chrome (31/10/2026)
- Command `card:back-thumbnails [slugs] [--delay]` (31/10/2026)
- Dashboard tile making the backs' thumbnails, shown only where Chrome is installed (31/10/2026)
- Guided project `card-back-thumbnails` for that tile (31/10/2026)
- `ChromeSession::screenshotElement()`, one element shot as a transparent PNG (31/10/2026)

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
