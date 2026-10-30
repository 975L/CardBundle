# Changelog

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
