# Domestic Cups & Ghost Teams

How a country's national cups and supercup are declared, populated and run, and how lower-division clubs take part without carrying a squad.

## The Problem

Only the top flight of most countries is playable, but a real cup runs through the whole pyramid: the FA Cup's third round fields the Premier League against the Championship, and its first two rounds are League One, League Two and non-league sides. Seeding every one of those clubs with a roster would multiply the player tables for competitions nobody manages.

## Ghost Teams

A **ghost team** is a `Team` row with a name, a country, an optional crest and stadium, and no players. It only ever appears in cup competitions:

- Ghosts are created by the reference-data seeder from a cup's `teams.json`, keyed by Transfermarkt `id` like every other club: the id supplies the crest, links the club to its league row when it has one, and keeps a ghost the same club across seasons. The seeder refuses an id that resolves to a club of another country, which is how a mistyped id would otherwise drag a foreign club into the cup.
- Matches involving a ghost are resolved from a default strength. In AI-only ties a ghost can win; in the user's own match a ghost cannot score, so an upset only ever comes on penalties.
- Ghosts are excluded from the transfer market, job offers, pre-season friendlies and every other flow that needs a squad, because they are only registered in cup competitions (`Team::scopeTransferMarketEligible`).

Spain's Copa del Rey already used this shape for its regional entrants; a country whose only playable tier is the top flight extends it to whole divisions.

## Declaring a Cup

Everything a cup needs beyond its participants and calendar lives in `config/countries.php`:

| Key | Purpose |
|-----|---------|
| `domestic_cups.<cup>.handler` | Competition handler, `knockout_cup` for every current cup. |
| `domestic_cups.<cup>.config_class` | Prize money per round (a `CompetitionConfig` implementation — `KnockoutCupConfig` and `SupercupConfig` are the shared ones). |
| `domestic_cups.<cup>.draw_pairing` | Draw strategy: `CrossCategoryPairing` for a Copa del Rey–style draw, `SeededBracketPairing` for a supercup whose ties follow from the qualifying seeds, random when unset. |
| `domestic_cups.<cup>.short_name` / `abbreviation` | Compact labels for tight layouts. |
| `domestic_cups.<cup>.neutral_venues` | Round name ⇒ venue for ties away from the home ground; `'*'` for every round. |
| `supercup` | Which cup and league feed the supercup, `teams` (4 for a final four, 2 for champion v cup winner), and `cup_entry_round` when the supercup field skips ahead in the main cup. |
| `cup_qualification.<cup>` | Which playable tiers qualify and Spain's `target_size` floor. |
| `cup_winner_slot` | A list of cup ⇒ UEFA competition places, applied best competition first. England declares two: the FA Cup pays a Europa League place, the EFL Cup a Conference League one. |

The data folder supplies the rest: `data/<season>/<cup>/teams.json` lists the participants (`id`, `name`, optional `entryRound`, `stadiumName`, `stadiumSeats`) and `schedule.json` the knockout rounds. The final is always the last round of the schedule, so no round number is repeated in config.

## Season Flow

**Closing pipeline.** `SupercupQualificationProcessor` derives next season's supercup field from the cup final and the league table (final four with the RFEF cascade, or champion v cup winner), and writes each qualifier's `seed` so the bracket survives to the draw. `DomesticCupQualificationProcessor` then rebuilds each cup's field from the tier rules, leaving ghosts untouched. `UefaQualificationProcessor` hands the cup winner its European slot.

**Setup pipeline.** Before any draw, `CupEntryRoundService` settles the round every club joins at:

1. Every club takes the round its `teams.json` entry declared, or the first round when it declared none.
2. The supercup field moves to `cup_entry_round` (Spain's four Supercopa clubs skip to the round of 32).

A supercup is not drawn: `SeededBracketPairing` pairs its seeds top against bottom (1 v 4, 2 v 3), which for Spain's qualifying order gives the RFEF fixtures — cup winner v league runner-up, cup runner-up v league champion. A game's first season has no in-game qualification history, so its imported supercup field is unseeded and falls back to a shuffle.

`SeasonInitializationService::conductCupDraws` then draws round 1 of every cup the user's club is in. Later rounds are drawn as ties resolve (`ConductNextCupRoundDraw`); rounds that only involve ghosts are simulated as ordinary AI batches on their scheduled date.

## Where a Cup's Shape Comes From

A knockout is a chain of halvings, so a cup only works if each round's field is even. That is a property of the competition as its federation designed it, and it belongs in the data: `entryRound` per club in `teams.json` is what makes the FA Cup's 80 lower-league clubs play rounds one and two while its 44 league clubs come in at round three. Nothing in the engine derives it, so nothing in the engine has to be taught a new country's format.

The engine keeps the field even against simulation drift the other way: `cup_qualification.target_size` holds the cup at its expected size as reserve teams climb divisions, and `DomesticCupQualificationProcessor` throws rather than let a shortfall through silently. A cup whose declared rounds don't halve cleanly will surface at the draw as an odd pool.

## Adding a Cup to a Country

Spain and England have cups declared. To bring another country to the same point, nothing beyond config and data should be needed:

1. Declare the cups, supercup, qualification rules and cup-winner slot in `config/countries.php`, following the Spanish block.
2. Add a prize config per cup under `app/Modules/Competition/Configs/`.
3. Drop `teams.json` and `schedule.json` into `data/<season>/<cup>/` (the scraper's `season-config.js` needs a row per cup; trim scraped cup lists to the rounds proper). Ghost clubs carry `entryRound`.
4. Add any new round names to `lang/en/cup.php` and `lang/es/cup.php`.
5. Run `php artisan app:validate-season <season>` and re-seed.

## Key Files

| File | Purpose |
|------|---------|
| `config/countries.php` | Cup, supercup and qualification declarations per country |
| `app/Modules/Competition/Services/CupEntryRoundService.php` | Entry-round assignment at season setup |
| `app/Modules/Season/Processors/UefaQualificationProcessor.php` | Cup-winner European places and their cascades |
| `app/Modules/Competition/Services/CupDrawService.php` | Draw mechanics and pairing-strategy resolution |
| `app/Modules/Competition/Services/NeutralVenueResolver.php` | Config-declared and UEFA neutral grounds |
| `app/Modules/Season/Processors/SupercupQualificationProcessor.php` | Supercup field derivation |
| `app/Modules/Season/Processors/DomesticCupQualificationProcessor.php` | Cup field rebuild each season |
| `app/Console/Commands/SeedReferenceData.php` | Ghost team creation (`seedCupTeams`) |

## England: a cup trimmed to its playable rounds

Only England's top flight is playable, so the FA Cup's qualifying rounds and the
EFL Cup's early rounds contain nobody the user can be. Each English cup therefore
starts at the round the Premier League joins:

| Cup | Field | Rounds |
|-----|-------|--------|
| `ENGCUP` (FA Cup) | 64 — 20 Premier League clubs + 44 ghosts | third round to the final |
| `ENGLC` (EFL Cup) | 32 — 20 Premier League clubs + 12 ghosts | third round to the final, semi-finals over two legs; pays half the FA Cup (`EflCupConfig`) |
| `ENGSUP` (Community Shield) | 2 — champion v FA Cup winner | one round, at Wembley |

Round *numbers* in `schedule.json` run from 1; the round *names* carry the real
competition's round, which is why the FA Cup opens on `cup.third_round`. Because
every club enters at round 1, England needs no `entryRound` anywhere — the field
halves cleanly on its own. A country that wants its full pyramid still can:
`entryRound` is what makes that work, and Spain's Copa is the model.

Three things worth knowing before adding a cup to a third country:

- **A ghost can win a cup.** It is then refused the European place its cup pays
  (`UefaQualificationProcessor` treats a winner with no squad as no winner) and
  the place cascades to the league table. The deeper the ghost field, the more
  often this matters.
- **`target_size` is not always wanted.** It only repairs a shortfall by pulling
  more clubs from `top_per_group`. A country whose only playable tier is the top
  flight has no such group, so declaring it could only turn a shortfall into a
  thrown season transition. England omits it.
- **A draw pairing is a choice, not a default.** `CrossCategoryPairing` keeps the
  big clubs apart, which suits a Copa del Rey field spanning four divisions. In a
  country where every playable club is tier 1 and every ghost tier 99 it would
  make a top-flight tie impossible, so England declares none and gets the open
  draw `RandomPairing` provides.
