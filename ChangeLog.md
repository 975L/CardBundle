# Changelog

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
