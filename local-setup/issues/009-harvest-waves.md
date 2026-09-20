---
id: 009
title: Harvest community variants in supervised waves
label: needs-human
phase: P4
depends-on: [007, 008]
---

# 009 — Variant harvest

Breadth is the point: trying a new map should be a ten-minute job. This issue is the machinery
for that, run in waves, **supervised** — every wave installs third-party PHP that the site will
execute, so a human signs off each batch.

## Waves, recomputed

The draft's waves assumed an empty tree. **Fourteen variants already ship here and nine are
already enabled.** The draft's Chaos, Build Anywhere, Cold War, France vs Austria and Germany vs
Italy are all already present. Corrected:

### Wave 0 — zero harvest, config only. Do this first.

Five variants are in the tree but not registered in the config array. Enabling them is one
config entry each plus the admin variant-info refresh:

| Variant | `$id` | `$mapID` |
| --- | ---: | ---: |
| FleetRome | 3 | 1 |
| CustomStart | 4 | 1 |
| BuildAnywhere | 5 | 1 |
| Colonial | 12 | 12 |
| Zeus5 | 70 | 70 |

This is the cheapest possible end-to-end test of the add-a-variant pipeline on code that is
already known good. If wave 0 does not work, nothing harvested will.

Note the three that share **map ID 1** with Classic. Derivatives sharing a parent's map is
correct and must not be "fixed".

### Wave 1 — the named wants

Westeros (issue 007) and the two-player roster (issue 008). Already carved out; not repeated
here.

### Wave 2 — supervised harvest

Community variants from the vDiplomacy family, in batches of five or so. Prefer maps someone has
actually asked for over completeness.

### Wave 3 — the deferred list

Everything that failed triage. Revisited only on demand. Expect most failures to be PHP 8.4
breakage in the variant's own classes.

## The twenty-minute triage rule

Per variant, a hard budget of **twenty minutes** from first look to working starting position.
When it expires, the variant goes on the deferred list with a one-line note of what broke, and
you move to the next one. No exceptions, no "just one more thing".

This is the rule that makes breadth achievable. A deferred variant costs nothing; an afternoon
lost to one stubborn map costs five other maps.

## Per-variant procedure

1. **Security review** (as in issue 007): read the variant definition, the installer, and every
   file in `classes/`. Reject anything making network calls, touching files outside its own
   directory, calling `eval`/`exec`/`system`/`shell_exec`/`passthru`, touching user or session
   tables, or executing an obfuscated string.
2. Check its `$id` and `$mapID` against `variant-registry.md`. On collision, **renumber into the
   900 block** and record the original ID in Notes. Never displace an incumbent.
3. Place the folder; create a writable `cache/` directory (gitignored, will not arrive with the
   download).
4. Confirm it ships `resources/style.css` (**not** `variant.css`) and dark-mode resources.
5. Register the ID in the config array; first load auto-installs.
6. Admin panel: variant-info update, then variant wipe to clear caches.
7. Run the five-item acceptance checklist from `SPEC.md`.
8. **Update the registry in the same change** — never afterwards.

## Done when

- [ ] Wave 0's five variants are enabled and each is `playable` in the registry.
- [ ] Wave 2 has been run in at least one supervised batch, with a human sign-off recorded.
- [ ] Every variant attempted has a registry row with a status — no variant is undocumented.
- [ ] The deferred table lists every failure with what broke.
- [ ] No ID collisions: every `$id` in the config array is unique and matches the registry.
- [ ] A human has signed off each batch before it was enabled.

## Verification

```
# every enabled ID appears once
grep -o '[0-9]\+=>' config.php | sort | uniq -d     # must print nothing
curl -s http://127.0.0.1:43000/gamecreate.php | grep -c '<option'
docker compose logs --tail=200 php-fpm | grep -iE 'fatal|deprecated'
```

Then per variant, in a browser, the five acceptance items.

## Notes / gotchas

- **Every harvested variant is legacy-board only.** Do not spend triage time wondering why a new
  map does not render on the modern board.
- The variant API is still version 1 and the base class has not changed in three years. When a
  port fails, look at PHP 8.4 compatibility and the autoloader, not the API.
- Preserve each variant's author attribution into the registry. Do not strip it.

---

## Wave 0 status — **done** (2026-09-20)

All five variants that were `present` in the tree are now registered, installed, played and
`playable` in `variant-registry.md`. No harvest, no code from outside the checkout, and no
renumbering: every one kept its upstream `$id` and `$mapID`.

### The `config.php` line after wave 0

`config.php` is gitignored, so this is the only versioned record. Wave 0 added `3`, `4`, `5`,
`12` and `70`; issue 008's harvest later added `22`, `26` and `62` (see that issue for the final
line).

```php
public static $variants=array(1=>'Classic',2=>'World',3=>'FleetRome',4=>'CustomStart',5=>'BuildAnywhere',9=>'AncMed',12=>'Colonial',15=>'ClassicFvA',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',23=>'ClassicGvI',45=>'GoT',46=>'GoT2',70=>'Zeus5',91=>'ColdWar');
```

### Install procedure actually run (per variant)

1. `cache/` already existed and was already `drwxrwxrwx` for all five — they ship in this
   checkout, so unlike a download nothing had to be created.
2. ID added to `Config::$variants`; one authenticated `GET /gamecreate.php` as `admin`
   auto-installed the map. (This worked first time here only because the variant cache happened
   to be cold — see issue 008's install steps. Run `wipeVariants` **before** the first load.)
3. `POST admincp.php actionName=updateVariantInfo variantID=<id>`, then
   `POST admincp.php actionName=wipeVariants`. No `formTicket` is needed for either.
4. Acceptance game, then `POST admincp.php actionName=togglePause gameID=<id>`.

### Territory and supply-centre counts — install.php vs the database

Counted from each `install.php`'s `$territoryRawData` literal and from `wD_Territories`
after install. **They match exactly.**

| Variant | `$id` | `$mapID` | install.php rows | DB rows | install.php SCs | DB SCs |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| FleetRome | 3 | 1 | *stub* | 81 | *stub* | 34 |
| CustomStart | 4 | 1 | *stub* | 81 | *stub* | 34 |
| BuildAnywhere | 5 | 1 | *stub* | 81 | *stub* | 34 |
| Colonial | 12 | 12 | 125 | 125 | 62 | 62 |
| Zeus5 | 70 | 70 | 113 | 113 | 41 | 41 |

The three Classic derivatives' `install.php` is a one-line
`require_once('variants/Classic/install.php')` — they deliberately share map 1 and install no
rows of their own. That is correct and must not be "fixed".

`wD_VariantInfo` was refreshed for **all sixteen** enabled IDs, not just the five, because the
acceptance tooling needs `variantID → mapID` to be resolvable from SQL.

### Acceptance

| Variant | `$id` | gameID | Players | Result |
| --- | ---: | ---: | ---: | --- |
| FleetRome | 3 | **9** | 7 | **Pass** |
| CustomStart | 4 | **10** | 7 | **Pass** |
| BuildAnywhere | 5 | **11** | 7 | **Pass** |
| Colonial | 12 | **12** | 7 | **Pass** |
| Zeus5 | 70 | **13** | 7 | **Pass** |

Per variant, what was actually exercised:

- **FleetRome (gameID 9).** The whole point of the variant is one unit: `wD_Units` shows Italy
  with a **Fleet in Rome** plus Fleet Naples and Army Venice, against Classic's Army Rome.
  Spring 1901 adjudicated with a support and a bounce in the same phase — France
  `A Paris → Burgundy` supported by `A Marseilles` succeeded, and Germany `A Munich → Tyrolia`
  vs Austria `A Vienna → Tyrolia` **bounced**, both units staying put. `board.php?gameID=9`
  returns **200** on the legacy board (FleetRome is not React-whitelisted, only Classic,
  ClassicFvA and ClassicGvI are). One phase, as the brief allowed for a Classic-map rule variant.
- **CustomStart (gameID 10).** Its distinguishing feature is that the game *opens* in a Builds
  phase with **zero units** and every country placing its own start. Confirmed: at creation all
  seven members had `unitNo = 0` and their normal SC counts (3, or 4 for Russia). All 22 opening
  builds were placed and processed — armies and fleets, including `F Brest`, `F Kiel`,
  `F Trieste`, `F Ankara`, `F Sevastopol` — and the game moved to Diplomacy turn 0. A Diplomacy
  phase was then run with the same support-plus-bounce pair as FleetRome and adjudicated
  correctly.
- **BuildAnywhere (gameID 11).** "Build in any owned supply centre" cannot be tested in year one,
  because no one owns a non-home centre until an autumn resolves, so this one was played to
  **Winter 1902**. Russia took Rumania in autumn 1901 (a neutral SC, not a Russian home centre),
  vacated it in 1902 while keeping ownership, took Sweden in autumn 1902 for a sixth centre, and
  then **built `A Rumania`** — a build in a conquered, non-home supply centre, accepted and
  processed. On unmodified Classic that order is illegal. The variant works.
- **Colonial (gameID 12).** Starting position checked unit-for-unit against
  `variants/Colonial/classes/adjudicatorPreGame.php`: Britain 6 (A Madras, A Delhi, F Bombay,
  F Aden, F Hong Kong, F Singapore), China 5, Russia 5, Japan 4, France 3, Holland 3, Turkey 3
  — 29 units, and each sits on a supply centre owned by that country in `install.php`. Spring
  1901: China `A Sinkiang → Mongolia` **supported** by `A Peking` succeeded; China
  `A Manchuria → Seoul` and Russia `A Vladivostok → Seoul` **bounced**. Autumn 1901 left China
  on 6 centres / 5 units, which produced exactly one build (`A Sinkiang`), and the builds phase
  processed. Reached **Diplomacy turn 2**. `board.php?gameID=12` → 200;
  `map.php?gameID=12&turn=1` → a 23 KB PNG; `map.php?variantID=12` → a 21 KB PNG.
- **Zeus5 (gameID 13).** Also a custom-start variant —
  `variants/Zeus5/classes/adjudicatorPreGame.php` is
  `class Zeus5Variant_adjudicatorPreGame extends CustomStartVariant_adjudicatorPreGame {}`, so
  **Zeus5 depends on CustomStart's class being loadable**; enabling Zeus5 without CustomStart in
  the tree would be a fatal. (Both are in the tree, so this is a note, not a problem.) The game
  opened in Builds with 0 units; all 24 opening builds were placed. Spring: Germany
  `A Munich → Low Countries` **supported** by `F Hamburg` succeeded, Italy `A Venice → Piedmont`
  and `F Rome → Piedmont` **bounced**. Autumn gave Germany Poland — 4 centres / 3 units — and the
  resulting single build processed. Reached **Diplomacy turn 2**. `board.php?gameID=13` → 200;
  `map.php?gameID=13&turn=1` → a 36 KB PNG; `map.php?variantID=70` → a 34 KB PNG.

### Compatibility review

All five are in-tree upstream 1.83 code, so no security review was required — nothing was
downloaded. They were still read for PHP 8.4 problems before being enabled:

- `Colonial/variant.php:73-76` and `Zeus5/variant.php:77-80` **already override `initialize()`**
  to restore their declared `$supplyCenterTarget` (30 and 21) after
  `WDVariant::initialize()` overwrites it from the database. The bug fixed by hand for GoT/GoT2
  in issue 007 does not exist here. Confirmed live in `wD_VariantInfo`: Colonial 30/62,
  Zeus5 21/41.
- The three Classic derivatives inherit `ClassicVariant`, so they inherit its target (18/34) —
  correct, since they share its map.
- **`php-fpm` logged no fatal, deprecation, warning or "undefined"** from any of the five across
  install, five games and every phase. The only new log lines are
  `libpng warning: iCCP: known incorrect sRGB profile`, emitted by GD when it reads the Colonial
  and Zeus5 `resources/map.png` files — a colour-profile nit in the shipped PNGs, cosmetic, and
  it does not affect the rendered board.

### One fix made, in four variant folders

**`variants/{FleetRome,CustomStart,BuildAnywhere,Colonial}/resources/darkMode/style.css` —
missing mandatory resource.** `lib/html.php:646-647` loops `Config::$variants` and links
`variants/<Name>/resources/darkMode/style.css` *unconditionally* for a dark-mode viewer, so the
moment these four were enabled every dark-mode page gained four 404s. None of the four shipped
one (Zeus5 does). Added: the three Classic derivatives get `variants/Classic`'s dark-mode
stylesheet with the selector prefix rewritten to their own `.variant<Name>`, which is what
`lib/html.php:922` builds the page class from; Colonial gets its own seven colours lightened for
a dark background. Nothing else in any of the five folders was touched.

### Notes

- **`Misc.Maintenance` stops the gamemaster.** With maintenance mode on, the SSE server's
  anonymous `GET /gamemaster.php` dies in `header.php:260` before reaching any processing code,
  so games silently stop processing while `gamemaster.php` keeps returning 200. `RUNBOOK.md` §4
  says this in passing; it is worth saying loudly. An admin's own request to `gamemaster.php`
  still processes, because `header.php:244` takes the Admin branch first.
- The verification command at the top of this issue,
  `grep -o '[0-9]\+=>' config.php | sort | uniq -d`, **gives false positives**: it matches every
  `N=>` in the file, including `Config::$serverMessages` and the bot/variant-mod arrays, so it
  prints duplicates that are not duplicate variant IDs. Scope it to the one line instead:
  `sed -n '163p' config.php | grep -o "[0-9]\+=>'" | sort | uniq -d` — which prints nothing.
