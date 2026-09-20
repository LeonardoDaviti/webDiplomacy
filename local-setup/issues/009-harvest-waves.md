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

---

## Wave 1 (Classic-map rule variants) — candidate set

Scope, as briefed: **every vDiplomacy variant that is a Classic-map *rule* variant** — one that
plays on the standard 34-supply-centre Classic board, or whose `install.php` is a stub relying on
another variant's map — and that is not already in `variant-registry.md`.

Source: `Sleepcap/vDiplomacy` @ `72c81f0dd73bedccc11f13a750dfcc62f580549a`, cloned to `/tmp/vdip`.

### How the set was determined

Every `/tmp/vdip/variants/*/variant.php` was read for `$id`/`$mapID`/`$countries`, and every
`install.php` was parsed for its territory-name set and compared, name for name, against this
install's `variants/Classic/install.php` (81 territories, 34 supply centres). Three outcomes:

- **exact name-set match** with our Classic → Classic-map rule variant → **candidate**;
- **stub installer** (`require_once('variants/Classic/install.php')`) → **candidate**, and the
  only kind allowed to share `$mapID` 1;
- anything else (extra or missing territories, changed terrain) → **not a rule variant**, out of
  scope for this wave.

### Candidates (16)

| Variant | vDip `$id` | vDip `$mapID` | Players | What the rule change is | Installer |
| --- | ---: | ---: | ---: | --- | --- |
| ClassicCrowded | 14 | 14 | 11 | Classic for 11 — four extra powers (Balkan, Lowland, Norway, Spain) | full |
| ClassicGvR | 25 | 25 | 2 | Germany vs Russia | full |
| Classic1897 | 28 | 28 | 7 | Starts in Winter 1897 with a build phase | full |
| ClassicFog | 30 | 30 | 7 | Fog of war | full |
| ClassicNoNeutrals | 38 | 38 | 7 | No neutral centres; solo target 12 | full |
| ClassicOctopus | 40 | 40 | 7 | Extra "octopus" borders between sea spaces | full |
| ClassicVS | 42 | 42 | 2–7 | "Pick your countries" — powers chosen from the game *name* | full |
| ClassicFGA | 48 | 48 | 3 | France vs Germany vs Austria | full |
| ClassicIER | 49 | 49 | 3 | Italy+ vs England+ vs Russia | full |
| ClassicGreyPress | 50 | **1** | 7 | Classic, plus anonymous ("grey") press | **stub** |
| ClassicChaoctopi | 54 | 54 | 34 | Chaos (one power per centre) with Octopus borders | full |
| ClassicAnkaraCrescent | 90 | 90 | 7 | Rule-change chaos variant after the forum game | full |
| ClassicBritain | 122 | 122 | 7 | England starts with six armies | full |
| ClassicBrazilian | 123 | 123 | 7 | The Brazilian edition's starting units | full |
| Classic1898 | 133 | 133 | 7 | Each power starts with one unit | full |
| Classic1898Fog | 134 | 134 | 7 | 1898 plus fog of war | full |

**Every upstream `$id` and `$mapID` above is free** in `variant-registry.md` (taken: 1–5, 9, 12,
15, 17, 19, 20, 22, 23, 26, 45, 46, 62, 70, 91), so no 900-block renumbering is needed for any of
them. ClassicGreyPress is the only one that may share `$mapID` 1, because its installer is a
one-line stub with no territory data of its own and its only class is a `Chatbox` subclass that
never names a territory.

### Explicitly considered and **excluded** from this wave

Not rule variants — each changes the board itself, so each is a map harvest for a later wave:

| Variant | vDip `$id` | Why excluded |
| --- | ---: | --- |
| Classic1880 | 34 | Adds Morocco, Algeria, Persia, Siberia, Dagestan; drops North Africa and Tuscany |
| Classic1913 | 106 | Adds Milan, Cologne, Egypt, Cyrenaica…; drops Venice, Ruhr, Tunis, Tuscany |
| ClassicCataclysm | 84 | Every territory is `Land`; all six coast children removed |
| ClassicCroatia | 119 | Adds Croatia and Sarajevo; drops Trieste and Tuscany |
| ClassicEconomic | 53 | Adds 20-odd resource territories (Coal, Beer, Cotton…) |
| ClassicEgypt | 120 | Adds Egypt, Libya, Suez, Red Sea and two new coasts |
| ClassicFlorence | 121 | Replaces the Italian peninsula (Florence, Campania, Taranto, Venetia) |
| ClassicLayered | 86 | Two stacked copies of the whole board (`Berlin 1`, `Berlin 2`, …) |
| ClassicMilan | 10 | Adds Milan and Savoy; drops Piedmont, Venice, Tuscany |
| ClassicPilot | 60 | Drops Heligoland Bight — 80 territories, not 81 |
| ClassicSevenIslands | 18 | Adds seven islands (Sicily, Sardinia, Corsica, Crete, Cyprus, Ireland, Iceland) |
| ClassicTouchy | 64 | Its own smaller board and coordinates |
| GobbleEarth, USofA | 96, 56 | World maps that happen to reuse many Classic names |
| Pure | 11 | Named in the brief, but it is **not** the Classic board: its own 82-line installer, no sea spaces, one territory per home centre |
| SailHo2 | 16 | Named in the brief, but its own four-power map, not Classic |

### Wave 1 — how each install was run

Identical for every variant, following issue 008's proven order:

1. Folder copied from `/tmp/vdip/variants/<Name>/`; `variants/<Name>/cache/` created `chmod 777`
   (gitignored at `.gitignore:23`, so it never arrives with a download).
2. `resources/darkMode/style.css` generated from the variant's own `resources/style.css` with the
   colours lightened for a dark background — none of them ships one, and `lib/html.php:646-647`
   links it **unconditionally** for every enabled variant when the viewer has dark mode on. The
   light-mode selector prefixes were checked first and are correct in every case
   (`.variantClassicGvR`, `.variantClassicFGA`, …), so the issue-007 `GoT2` wrong-selector bug
   does not recur.
3. ID added to `Config::$variants`.
4. `POST admincp.php actionName=wipeVariants` **before** the first page load, then one
   authenticated `GET /gamecreate.php` as `admin`, which auto-installs the map.
5. `POST admincp.php actionName=updateVariantInfo variantID=<id>`.
6. Acceptance game, then `POST admincp.php actionName=togglePause gameID=<id>`.

**Maintenance mode was on throughout** (a DATC run owns it) and was **not touched**. Two
consequences, both worked around rather than fixed: the gamemaster only processes for an admin's
own request, so every phase was driven by `GET /gamemaster.php` as `admin`; and `playerN`
accounts cannot log in at all while it is on, so every player action was issued from the admin
session with `?auid=<userID>`, the admin user-switch at `header.php:244` /
`libAuth::adminUserSwitch()`. That branch is taken **before** the maintenance check, which is why
it works.

### Wave 1 batch 1 — ClassicGvR, ClassicFGA, ClassicIER, ClassicBritain

| Variant | `$id` | `$mapID` | Players | Territories / SCs | Solo target | gameID | Verdict |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| ClassicGvR | 25 | 25 | 2 | 81 / 34 | 18 | **18** | **Pass** |
| ClassicFGA | 48 | 48 | 3 | 81 / 34 | 18 | **19** | **Pass** |
| ClassicIER | 49 | 49 | 3 | 81 / 34 | 18 | **20** | **Pass** |
| ClassicBritain | 122 | 122 | 7 | 81 / **37** | 20 | **21** | **Pass** |

**Security review — clean, all four.** Every `.php` file read in full (8 files: a `variant.php`,
an `install.php` and `classes/{adjudicatorPreGame,drawMap}.php` each). Zero hits anywhere for
`eval`, `assert`, `create_function`, `preg_replace`, `exec`, `shell_exec`, `system`, `passthru`,
`proc_open`, `popen`, backticks, `base64_decode`, `gzinflate`, `str_rot13`, `unserialize`,
`curl_*`, `fsockopen`, sockets, stream wrappers, any file read or write, remote includes,
`wD_Users` / `wD_Sessions` / `wD_ApiKeys`, `$_SESSION`, `$_GET` / `$_POST` / `$_COOKIE` /
`$_REQUEST` / `$_SERVER`, `Config::`, `$$`, `call_user_func`, or any obfuscated or
dynamically-built executed string. The only `require_once` in any of them is
`variants/install.php`, the in-tree base installer. All eight files carry the standard
`defined('IN_CODE') or die(...)` guard.

**Dependencies:** none. All four extend `WDVariant`, `adjudicatorPreGame` and `drawMap` directly,
all of which exist in this tree with compatible signatures. **None may share `$mapID` 1**: each
ships a full 570-odd-line installer with its own territory IDs and pixel coordinates (the same
finding as issue 008's ClassicEvT / ClassicFGvsRT), so each keeps its own upstream `$mapID`.

**IDs:** 25, 48, 49 and 122 were all free, so all four kept their upstream `$id` and `$mapID`.
No 900-block renumbering; nothing displaced.

**One compatibility fix, inside a variant folder:**

- **`variants/ClassicIER/variant.php` — an apostrophe in `$description` broke the admin panel.**
  The text read `Russia\'s four builds`, and `admincp`'s `updateVariantInfo` interpolates the
  description straight into SQL without escaping it, so the action died with
  *"You have an error in your SQL syntax … near 's four builds"* and the variant never got a
  `wD_VariantInfo` row. Reworded to *"the four Russian builds"*, with a comment saying why.
  The underlying escaping bug is in core `admin/`, not in the variant, and was deliberately
  **not** patched here — **any future harvest whose description contains an apostrophe will hit
  it.**

**Acceptance, per variant:**

- **ClassicGvR (25) — gameID 18.** `admin` = Germany, `player2` = Russia. Start matches
  `classes/adjudicatorPreGame.php` exactly: Germany F Kiel, A Berlin, A Munich; Russia
  F Sevastopol, A Warsaw, A Moscow, F St. Petersburg (South Coast). Spring 1901: Russia
  `F St. Petersburg (SC) → Livonia` **supported** by `A Moscow` succeeded. Autumn 1901 and the
  Winter builds processed (Germany built A Kiel, Russia A Sevastopol). Played on to **Spring
  1902**, which produced the cross-power **bounce**: Germany `F Denmark → Baltic Sea` and Russia
  `F Prussia → Baltic Sea` both failed, while both sides' supported moves succeeded. Ends 4 SCs /
  4 units vs 5 / 5. `board.php?gameID=18` → 200 (legacy); `map.php?gameID=18&turn=1` → an 18 KB
  PNG. The two powers are not adjacent in 1901, which is why the bounce had to wait for 1902.
- **ClassicFGA (48) — gameID 19.** France / Austria / Germany, three units each, matching
  `adjudicatorPreGame.php`. Spring 1901 contained **both** required cases in the same phase:
  France `A Paris → Burgundy` and Germany `A Munich → Burgundy` **bounced**, and France
  `F Brest → Gascony` **supported** by `A Marseilles`, Austria `A Vienna → Galicia` supported by
  `A Budapest` and (in autumn) Germany `F Denmark → Kiel` supported by `A Berlin` all succeeded.
  Autumn and builds processed; 4 / 4 / 3 centres at **Spring 1902**. 19 KB map PNG.
- **ClassicIER (49) — gameID 20.** England / Italy / Russia, **four units each** — England's
  extra is `A Holland` and Italy's is `F Trieste`, exactly as the description says. Spring 1901:
  supported moves succeeded for all three (e.g. England `F London → Yorkshire` supported by
  `A Liverpool`). **No bounce is reachable in 1901** — no two of England, Italy and Russia have a
  unit adjacent to a common free territory — so the game was played to **Spring 1903**, where
  England `A Sweden → Finland` and Russia `F St. Petersburg (SC) → Finland` **bounced**. Ends
  6 / 4 / 5 centres. 19 KB map PNG.
- **ClassicBritain (122) — gameID 21.** Seven accounts, `admin` = England. The variant's whole
  point is visible at once: England starts **six armies** on Clyde, Edinburgh, Liverpool, London,
  Wales and Yorkshire, and all six are supply centres — hence **37 SCs** on the board and a solo
  target of 20 rather than 18, both confirmed in `wD_VariantInfo`. Spring 1901: France
  `A Paris → Burgundy` vs Germany `A Munich → Burgundy` **bounced**; five supported moves
  succeeded. Autumn and builds processed; England reaches 6 centres / 6 units at **Spring 1902**.
  20 KB map PNG.

All four: the New Game dropdown shows them, `variants.php` renders each with its full name and
player count ("Classic - Britain (7 Players)"…), and `board.php?gameID=…` returns **200 without
redirecting** — legacy board, as expected off the React whitelist.

**PHP 8.4:** `php -l` clean on all eight files, and `docker compose logs php-fpm` shows no fatal,
deprecation, warning or "undefined" attributable to any of them across install, four games and
every phase. (The `sql_tabl() on null` fatals in the log are the known error-page artefact from
repeated `POST /logon.php` during tooling setup — `RUNBOOK.md` §8 — not variant code.)

### Wave 1 batch 2 — ClassicBrazilian, ClassicNoNeutrals, ClassicCrowded, ClassicGreyPress

| Variant | `$id` | `$mapID` | Players | Territories / SCs | Solo target | gameID | Verdict |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| ClassicCrowded | 14 | 14 | 11 | 81 / 35 | 18 | — | **Pass (render-only)** |
| ClassicNoNeutrals | 38 | 38 | 7 | 81 / 22 | 12 | **23** | **Pass**, with a builds caveat |
| ClassicGreyPress | 50 | **1** | 7 | 81 / 34 | 18 | **24** | **Pass** |
| ClassicBrazilian | 123 | 123 | 7 | 81 / 35 | 19 | **22** | **Pass** |

Territory and supply-centre counts were read from each `install.php`'s literal **and** from
`wD_Territories` after install: **they match exactly** (81/35, 81/22, *stub*, 81/35).

**Security review — clean, all four.** Nine `.php` files read in full. Same negative checklist as
batch 1, with one deliberate exception worth recording:
`variants/ClassicGreyPress/classes/Chatbox.php` reads `$_REQUEST['newmessage']` — it is a
`Chatbox` subclass whose whole job is to re-route a message, and the in-tree core
`board/chatbox.php:92` reads exactly the same input in exactly the same way. It performs no file,
network or user-table access and builds no dynamic code. Accepted.

**Dependencies — the one interesting case in this wave.** `ClassicGreyPressVariant`
**extends `ClassicVariant`**, not `WDVariant`, and its installer is a one-line
`require_once('variants/Classic/install.php')`. So it (a) requires variant 1 to stay in the tree,
and (b) is the **only** wave-1 variant allowed to share `$mapID` 1 — the brief's exception for a
stub installer. It does not carry vDiplomacy's Classic territory data at all; the `require_once`
resolves to **our** `variants/Classic/install.php`, so it installs and draws our map 1, and no
vDip-specific territory ID can leak in. The other three ship full installers with their own IDs
and coordinates and keep their own `$mapID`.

**IDs:** 14, 38, 50 and 123 were all free; all four kept their upstream `$id`. No renumbering.

**No code fixes were needed** in any of the four beyond the generated dark-mode stylesheet.

**Acceptance:**

- **ClassicBrazilian (123) — gameID 22.** Start matches `adjudicatorPreGame.php`: the Brazilian
  edition's differences are visible immediately — England is **F London**, F Edinburgh,
  A Liverpool and Italy is A Venice, **F Rome**, F Naples, against Classic's F London/A Liverpool
  and A Rome. Spring 1901: Italy `A Venice → Tyrolia` and Germany `A Munich → Tyrolia`
  **bounced**; England `F Edinburgh → Clyde` supported by `A Liverpool` and France
  `F Brest → Picardy` supported by `A Paris` **succeeded**. Autumn and Winter builds processed;
  **Spring 1902** with 3/4/3/3/3/5/4 centres. `board.php?gameID=22` → 200; 20 KB map PNG.
- **ClassicNoNeutrals (38) — gameID 23.** 22 supply centres, every one a home centre; the
  starting position is the standard Classic one. Spring 1901: England
  `F London → English Channel` and France `F Brest → English Channel` **bounced**; England
  `F Edinburgh → Clyde` supported by `A Liverpool` and France `A Paris → Burgundy` supported by
  `A Marseilles` **succeeded**. Autumn resolved with centre counts 3/3/3/3/3/3/4 = **22, the
  whole board**, which is exactly right. **No builds phase was reached**, and this is structural
  rather than a fault: with no neutral centres, nobody can gain one without dislodging a rival,
  so a year-one game has nothing to build. The game was played on to **turn 12 (Spring 1907)** to
  try to provoke one and the count never moved. The builds pipeline is untouched core code here —
  the variant subclasses only `adjudicatorPreGame` and `drawMap` — and builds were exercised on
  the other three variants in this batch. Recorded as a caveat, in the same spirit as issues 007
  and 008's "a disband was never exercised".
- **ClassicGreyPress (50) — gameID 24.** Standard Classic start on map 1, confirmed unit for
  unit. Spring 1901: England `F London → English Channel` vs France `F Brest → English Channel`
  **bounced**; two supported moves succeeded. Autumn and builds processed; **Spring 1902**,
  3/4/3/4/3/5/4. **The rule change itself was exercised**: posting to the extra *Grey Press* tab
  (`msgCountryID` 8, one past the seven countries) as Turkey with the body `Italy: …` produced
  two rows in `wD_GameMessages` — the message delivered **to Italy from country 8**, and a
  `[to: Italy]` receipt back to the sender. The recipient never sees Turkey. That is the variant
  working end to end. `board.php?gameID=24` → 200.
- **ClassicCrowded (14) — render-only, no gameID.** Eleven powers, and this install has ten
  accounts, so per the brief the acceptance is install + render + counts. Done: the variant is in
  the New Game dropdown; `variants.php` renders *"Classic - Crowded (11 Players)"*;
  `map.php?variantID=14` returns a 17 KB PNG; `wD_VariantInfo` holds 35/18 for 11 countries; and
  `wD_Territories` for map 14 holds 81 territories with **35 supply centres, every one a home
  centre** (3+3+3+3+3+3+4+4+3+3+3), i.e. no neutrals — which is what "crowded" means here. As a
  substitute for a played game, all 35 starting units in `classes/adjudicatorPreGame.php` were
  checked against the installed map: every one names a real territory that is a supply centre
  owned by the right power, with terrain that permits the unit type. Nothing is left to verify
  but the adjudication, which is shared core code.

**A tooling note that will bite the next batch: game creation costs points.** Every
`gamecreate.php` bets 5 points, and after a dozen acceptance games `admin` and `player2` were on
zero and creation failed with *"5 is an invalid bet size"*. Fixed with the admin panel's own
**`givePoints`** action (`userID`, `points`) for each of the ten accounts — not with SQL.

### Wave 1 batch 3 — Classic1897, ClassicOctopus, ClassicAnkaraCrescent, Classic1898

| Variant | `$id` | `$mapID` | Players | Territories / SCs / borders | Solo target | gameID | Verdict |
| --- | ---: | ---: | ---: | --- | ---: | ---: | --- |
| Classic1897 | 28 | 28 | 7 | 81 / 34 / 431 | 18 | **27** | **Pass** |
| ClassicOctopus | 40 | 40 | 7 | 81 / 34 / **1,205** | 18 | **25** | **Pass** |
| ClassicAnkaraCrescent | 90 | 90 | 7 | 81 / 34 / **1,691** | 18 | **26** | **Pass** |
| Classic1898 | 133 | 133 | 7 | 81 / 34 / 431 | 18 | **28** | **Pass** |

Classic's own map has 431 border rows, so the two "everyone is your neighbour" variants are
almost entirely *data*: same 81 territories, three to four times the adjacency.

**Security review — clean, all four.** 19 `.php` files, the largest set in this wave, because
these four hook more of the engine than batches 1 and 2:

| Variant | Classes it subclasses |
| --- | --- |
| Classic1897 | `adjudicatorPreGame`, `drawMap`, **`processGame`**, `processOrderBuilds`, `OrderInterface`, `userOrderBuilds`, **`panelGameBoard`** |
| ClassicOctopus | `adjudicatorPreGame`, `drawMap`, `OrderInterface` |
| ClassicAnkaraCrescent | `adjudicatorPreGame`, `drawMap`, `OrderInterface` (empty), `userOrderDiplomacy` (empty) |
| Classic1898 | `adjudicatorPreGame`, `drawMap`, `OrderInterface`, **`processOrderBuilds`**, **`userOrderBuilds`** |

The same negative checklist as the earlier batches holds: no `eval`/`exec`/`system`/`passthru`,
no network calls, no file access, no user or session tables, no dynamically-built executed
string, and `defined('IN_CODE')` in every file. These four *do* contain SQL — `processGame`,
`processOrderBuilds`, `userOrderBuilds` and `Classic1897`'s `OrderInterface` all run queries —
and every one was read: they interpolate only `$Game->id`, `$this->gameID`, `$this->countryID`,
`$this->toTerrID`, `MAPID` and `$GLOBALS['GAMEID']`, all internal integers, never request data.
The two `OrderInterface` subclasses do a `str_replace` on `libHTML::$footerScript` to chain an
extra JS call, and the JS they add (`convoydisplayfix.js`, `supplycenterscorrect*.js`) makes no
network calls.

**Base-class check.** Every method these four override or call still exists in this tree with a
compatible signature: `processGame::{changePhase,setPhase,archiveTerrStatus,updateOwners}`
(`gamemaster/game.php`), `processOrderBuilds::{create,apply}` (`gamemaster/orders/builds.php`),
`userOrderBuilds::toTerrIDCheck` (`board/orders/builds.php`), `OrderInterface::jsLoadBoard`, and
`datetxt($turn=false)` — which is **not** on `panelGameBoard` but on `Game`, from which
`panelGame` inherits, so `Classic1897`'s `parent::datetxt()` resolves. `MAPID` and
`$GLOBALS['GAMEID']` both exist. One thing worth flagging for the future:
**`Classic1898`'s `processOrderBuilds::apply()` is a copy of the *pre-2024* core implementation**
— upstream has since split `apply()` (2024 Renegade rules for choosing which unit a failed
`Destroy` removes) from `apply_pre2024Rules()`. The variant therefore keeps the old disband-choice
behaviour. It is not a fault and it does not break anything, but a later upstream merge should
re-read it.

**Dependencies:** none beyond core. **IDs 28, 40, 90 and 133 were all free**; all four kept their
upstream `$id`/`$mapID`. None may share map 1 — all four ship full installers.

**No code fixes were needed** beyond the generated dark-mode stylesheets.

**Acceptance:**

- **ClassicOctopus (40) — gameID 25.** Standard Classic start. The rule is "double movement",
  and it is visible in the very first phase: **`A Moscow → Norway`** was a legal, submitted order
  — two hops on the Classic map — and it bounced against England's `F Edinburgh → Norway`, a
  cross-power **bounce** between units that are nowhere near each other on the real board.
  Supports succeeded (`F London → Yorkshire` supported by `A Liverpool`). Autumn and builds
  processed; **Spring 1902**, 3/6/3/3/5/4/5 centres. 21 KB map PNG.
- **ClassicAnkaraCrescent (90) — gameID 26.** Standard start, 1,691 borders. Spring 1901:
  England `F London → English Channel` vs France `F Brest → English Channel` **bounced**; two
  supported moves succeeded. Autumn and builds processed; **Spring 1902**, 3/3/3/3/4/5/4. 20 KB
  map PNG.
- **Classic1897 (28) — gameID 27.** The variant's shape is confirmed exactly:
  `classes/adjudicatorPreGame.php` **disables** `assignUnits()` and `assignUnitOccupations()`, so
  the game opens with **no units at all**, in a **Builds phase at turn 0** that
  `processGame::changePhase()` inserts and `panelGameBoard::datetxt()` renames *"Autumn, 1897"*.
  All seven powers placed their one opening unit (A Edinburgh, A Brest, A Naples, A Kiel,
  A Vienna, A Constantinople, A Sevastopol), the phase processed into Spring 1898, a
  cross-power **bounce** followed at Venice (Italy vs Austria), and later turns produced
  successful **supported** moves (`A Liverpool → Clyde` supported by `A Edinburgh`, and two
  others) and a second builds phase. Played to **turn 5**. 19 KB map PNG.
- **Classic1898 (133) — gameID 28.** Each power starts with **one unit and one home centre** —
  `wD_Territories` for map 133 has exactly one territory per country and **74 neutrals**, which
  is why the variant has to bring its own build rule. Start matched `adjudicatorPreGame.php`
  unit for unit. Spring/Autumn 1899 produced a **bounce** (Italy `A Rome → Tuscany` vs Austria
  `A Venice → Tuscany`) and, once powers had two units, successful **supported** moves
  (`A Brest → Picardy` supported by `A Paris`; `A Smyrna → Constantinople` supported by
  `A Bulgaria`). Builds processed twice. **The build-anywhere rule was exercised deliberately**:
  at turn 5's builds, Germany built **`A Sweden`**, Austria **`A Venice`** and Turkey
  **`A Bulgaria`** — all conquered neutrals, none a home centre, all accepted by the variant's
  own `userOrderBuilds::toTerrIDCheck()`. On unmodified Classic every one of those is illegal.
  19 KB map PNG.

All four appear in the New Game dropdown, render in `variants.php` with their player counts, and
answer `board.php?gameID=…` with **200** (legacy board).

### Wave 1 batch 4 — ClassicVS, ClassicChaoctopi, and the two fog variants

| Variant | `$id` | `$mapID` | Players | Territories / SCs | gameID | Verdict |
| --- | ---: | ---: | ---: | ---: | ---: | --- |
| ClassicFog | 30 | 30 | 7 | 91 / 34 | 31 (cancelled) | **Deferred** |
| ClassicVS | 42 | 42 | 2–7 | **82** / 34 | **29**, **30** | **Pass** |
| ClassicChaoctopi | 54 | 54 | 34 | 81 / 34 | — | **Pass (render-only)** |
| Classic1898Fog | 134 | 134 | 7 | — | — | **Deferred, not installed** |

**Security review — clean** for ClassicVS (8 files) and ClassicChaoctopi (8 files); same negative
checklist. Both contain SQL in their `processOrderBuilds` / `userOrderBuilds` subclasses and both
interpolate only internal integers. ClassicChaoctopi's `Chatbox` subclass rewrites message bodies
for display and its `panelMembersHome` rewrites the 34-power member table; neither touches input.

- **ClassicVS (42) — gameIDs 29 and 30.** *"Classic - Pick your countries"*, and the rule is
  genuinely unusual: the variant's `__call()` override parses the **game's name** for a
  parenthesised code and rewrites `$countries` before `Members`, `processMembers`,
  `panelMembers` or `panelMembersHome` is constructed. Both paths were tested.
  **gameID 29**, named without a code, filled seven seats with the standard Classic start and
  played Spring → Autumn → Builds to **Spring 1902** (bounce at the English Channel, two
  supported moves, 3/4/3/4/3/5/4 centres). **gameID 30**, named
  `ClassicVS pick (EFG) 009`, **started as a three-player game** with exactly England
  (F London, F Edinburgh, A Liverpool), France (F Brest, A Paris, A Marseilles) and Germany
  (F Kiel, A Berlin, A Munich) — nine units, no Italy/Austria/Turkey/Russia — and played to
  Spring 1902. That makes **four-, five- and six-player games servable on this install for the
  first time**, which is worth more than the variant count: see `PLAYING.md` §B.1.
  Its map is **82 territories**, Classic's 81 plus a dummy `PreGameCheck` used by its
  pre-game logic. Own installer and `$mapID`.
- **ClassicChaoctopi (54) — render-only.** Chaos × Octopus: 34 one-centre powers on the Classic
  board with Octopus's double-movement borders (**1,205** border rows). Thirty-four players
  against ten accounts, so acceptance is install + render + counts, as the brief allows. Verified:
  in the dropdown; `variants.php` renders *"Classic - Chaoctopi (34 Players)"*;
  `map.php?variantID=54` → an 18 KB PNG; `wD_VariantInfo` 34/18 for 34 countries;
  `wD_Territories` 81 territories / 34 SCs with **every SC owned by its own power** (34 of 34 have
  a non-zero home country) and 1,205 borders. Like Classic1897 it disables `assignUnits()` and
  opens in a turn-0 Builds phase, so there is no starting-unit table to check — each player
  builds their single unit on their own centre.

#### The two fog variants — **deferred**, and why

**ClassicFog (30) failed at the board, not at the install.** It installed cleanly (91
territories: Classic's 81 plus ten fog pseudo-territories such as `Denmark - Seas` and
`Irish Sea - Islands`; 34 SCs), appeared in the dropdown, and a seven-player game started with
the correct standard position. Then every member's `board.php` died with:

```
Error triggered: A software exception was not caught: "Undefined constant "STATICSRV""
Raised: "/application/variants/ClassicFog/classes/OrderInterface.php"  Line: "22"
```

`STATICSRV` is a **vDiplomacy-only constant** (its static-asset host) and is defined nowhere in
webDiplomacy. Two further blockers sit behind it, so this is not a one-line fix:

- `classes/Maps.php` declares `class Fog_Maps extends Maps` — **`Maps` does not exist in this
  codebase.** vDiplomacy refactored map display into a `Maps` class; webDiplomacy never did.
  Nothing here ever asks for `$Variant->Maps()`, so the class is merely inert rather than fatal,
  but it means the fogged-map substitution has no hook of its own.
- `classes/OrderArchiv.php` extends **`OrderArchiv`**, also absent (the same dangling reference
  the in-tree Zeus5 and Duo have). In vDiplomacy that class is what hides *other players' orders*
  in the archive, so even with `STATICSRV` defined and the board fixed, the fog would be
  incomplete — a fog variant that leaks is worse than no fog variant.

Per the twenty-minute rule it was **removed from `Config::$variants`**, its acceptance game
(gameID 31) was cancelled through `admincp actionName=cancelGame` so that no game references a
disabled variant, and the orphan map-30 rows were deleted from `wD_Territories`, `wD_Borders` and
`wD_CoastalBorders`.

**One deliberate departure from "leave the folder": `variants/ClassicFog/` was deleted.** It
ships three front controllers under `resources/` — `fogmap.php`, `orders.php` and
`jsonBoardData.php` — which `require_once('header.php')` and run a full request. While the folder
was present, `GET /variants/ClassicFog/resources/fogmap.php` returned **200 and executed**
("gameID or turn not provided; cannot draw map"). Leaving unreachable-by-design third-party front
controllers executing in the webroot for a variant that is not even enabled is not worth it; the
package is one `git clone` away in `/tmp/vdip` if anyone revisits. After deletion the same URL is
**404**. This is flagged for the human sign-off rather than buried.

**Classic1898Fog (134) was not installed at all.** Its `classes/OrderInterface.php:22` is the
identical `STATICSRV` line, and it ships the same `Maps`, `OrderArchiv` and `resources/fogmap.php`.
Installing it would have reproduced a known failure. One fix unblocks both.

**IDs 30 and 134 are reserved permanently** even though neither is installed, per the registry's
never-reuse rule.

### Wave 1 — summary and the final `config.php` line

Sixteen candidates, **fourteen playable, two deferred**, nothing renumbered: every one kept its
upstream `$id` and `$mapID`, because all sixteen upstream IDs were free.

| Verdict | Variants |
| --- | --- |
| **Playable, played** (12) | ClassicGvR 25, Classic1897 28, ClassicNoNeutrals 38, ClassicOctopus 40, ClassicVS 42, ClassicFGA 48, ClassicIER 49, ClassicGreyPress 50, ClassicAnkaraCrescent 90, ClassicBritain 122, ClassicBrazilian 123, Classic1898 133 |
| **Playable, render-only** (2) | ClassicCrowded 14 (11 players), ClassicChaoctopi 54 (34 players) |
| **Deferred** (2) | ClassicFog 30, Classic1898Fog 134 — both `STATICSRV` / `Maps` / `OrderArchiv` |

Acceptance games **18–30**, all **paused** afterwards. Thirteen games, because ClassicVS needed
two (seven-player and three-player).

`config.php` is gitignored, so this is the only versioned record. **Thirty-three variants:**

```php
public static $variants=array(1=>'Classic',2=>'World',3=>'FleetRome',4=>'CustomStart',5=>'BuildAnywhere',9=>'AncMed',12=>'Colonial',14=>'ClassicCrowded',15=>'ClassicFvA',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',22=>'Duo',23=>'ClassicGvI',25=>'ClassicGvR',26=>'ClassicFGvsRT',28=>'Classic1897',38=>'ClassicNoNeutrals',40=>'ClassicOctopus',42=>'ClassicVS',45=>'GoT',46=>'GoT2',48=>'ClassicFGA',49=>'ClassicIER',50=>'ClassicGreyPress',54=>'ClassicChaoctopi',62=>'ClassicEvT',70=>'Zeus5',90=>'ClassicAnkaraCrescent',91=>'ColdWar',122=>'ClassicBritain',123=>'ClassicBrazilian',133=>'Classic1898');
```

Every key unique — `sed -n '163p' config.php | grep -o "[0-9]\+=>'" | sort | uniq -d` prints
nothing.

### Wave 1 — things worth carrying forward

- **`admincp`'s `updateVariantInfo` does not escape the variant description.** One apostrophe in
  `ClassicIER`'s `$description` was enough to kill the action with a SQL syntax error. Worked
  around in the variant; the escaping bug is in core `admin/` and is still there. **Any future
  harvest whose description contains an apostrophe will hit it.**
- **Creating games costs points.** Thirteen acceptance games emptied `admin` and `player2`, and
  creation then fails with the unhelpful *"5 is an invalid bet size"*. The admin panel's
  **`givePoints`** action fixes it in one request per account.
- **Maintenance mode makes `playerN` accounts unusable, but `?auid=<userID>` does not care.**
  `header.php:244` takes the Admin branch before the maintenance check, so an admin session can
  act as any user with `?auid=N` (and `auid=0` to switch back) even while maintenance is on. That
  is how all thirteen games were played without touching the DATC run's maintenance flag.
  (Maintenance was on when this wave started and was **off** by the end — turned off by whoever
  owns the DATC run, not by this work.)
- **A disabled variant must not be left with games pointing at it.** Before removing `30` from
  `Config::$variants`, the ClassicFog acceptance game was cancelled; otherwise the gamemaster
  would have tried to load a variant that is no longer in the config on every pass.
  `cancelGame` needs `formTicket` **and** `actionConfirm=on`, unlike `wipeVariants`,
  `updateVariantInfo`, `togglePause` and `givePoints`, which need neither.
- **Rule variants divide into two kinds, and the second kind is nearly free.** ClassicOctopus,
  ClassicAnkaraCrescent and ClassicChaoctopi change nothing but the **border table** — 1,205 and
  1,691 rows against Classic's 431 — and carry almost no PHP. Those are the safest harvests
  there are. The ones that hook `processGame`, `processOrderBuilds` or `OrderInterface`
  (Classic1897, Classic1898, ClassicVS, the fog pair) are where the risk lives, and the fog pair
  is where it actually bit.
- **Every wave-1 variant is legacy-board only**, as the issue predicts: `Game::isClassicGame()`
  whitelists by *name*, so even the fourteen that play on Classic's own geometry render on
  `board.php`. All of them return 200 there without redirecting.

---

## Wave 3 (top non-Classic maps) — candidate set

Scope, as briefed: vDiplomacy's **standalone maps with ten players or fewer** that are not
already in `variant-registry.md`, not Classic-board rule variants (wave 1) and not in the wave
4/5 lists.

Source, as for waves 1 and 2: `Sleepcap/vDiplomacy` @
`72c81f0dd73bedccc11f13a750dfcc62f580549a`, cloned to `/tmp/vdip`.

### The four headline maps named in the brief

| Named | Outcome |
| --- | --- |
| **Ancient Mediterranean** | Already ours — `AncMed` 9, enabled since before issue 009. **Skipped.** |
| **Modern Diplomacy II** | Already ours — `Modern2` 19. vDip's `Modern2` was diffed against ours: **same `$id` 19, same `$mapID` 19, and an exact 143-for-143 territory-name match**, so it is the same map. **Skipped.** Only `variant.php`, `install.php`, `classes/OrderInterface.php` and `resources/supplycenterscorrect.js` differ at all, and vDip additionally ships an `interactiveMap/` directory that this codebase has no subsystem for. |
| **World War II** | `WWII` 87, 5 players, 186 territories / 74 SCs — in the candidate list below. |
| **South America** | vDip has **three** South America maps, not one: `SouthAmerica4` (7, 4 players), `SouthAmerica5` (6, 5 players) and `SouthAmerica8` (24, 8 players). All three are candidates. |
| **Greek Diplomacy** | `GreekDip` 35, 6 players, 110 territories / 34 SCs — in the candidate list below. |

### How the set was determined

Every `/tmp/vdip/variants/*/variant.php` was read for `$id`, `$mapID` and `$countries`, and every
`install.php` for its `$territoryRawData` row count and supply-centre count. A variant is a
wave-3 candidate when all of these hold:

- it has its own full `install.php` (not a stub `require_once` of another variant's);
- `count($countries) <= 10`, so an acceptance game is servable from this install's ten accounts;
- its name does not begin with `Classic` — the whole `Classic*` family, including wave 1's
  sixteen and the twelve near-misses wave 1 explicitly excluded (Classic1880, Classic1913,
  ClassicCataclysm, ClassicCroatia, ClassicEconomic, ClassicEgypt, ClassicFlorence,
  ClassicLayered, ClassicMilan, ClassicPilot, ClassicSevenIslands, ClassicTouchy), is out of
  scope here;
- it is not already in `variant-registry.md` (AncMed, ColdWar, Colonial, Duo, Empire4, Modern2,
  World, Zeus5 and the three Classic-map derivatives);
- it is not in the wave 4/5 lists: Machiavelli and MachiavelliTTR, KnownWorld_901, Colonial1885,
  YoungstownRedux and YoungstownWWII, Sengoku5 and Sengoku6, Africa, and everything with more
  than ten players (GobbleEarth, EastIndies, World10, Haven, A_Modern_Europe, the WWIV family,
  Divided_States, Pirates, Imperial2, FantasyWorld, Rinascimento, WorldAtWar1937, Crusades1201,
  MongolianEmpire).

`TenSixtySix_V2` (85) and `TenSixtySix_V3` (94) fall out of the list because neither declares a
`$countries` array in its own `variant.php` at all; they are left for a later wave.

**67 candidates**, ordered as they were worked — smallest territory count first:

| # | Variant | vDip `$id` | vDip `$mapID` | Players | Territories / SCs | Full name |
| ---: | --- | ---: | ---: | ---: | ---: | --- |
| 1 | Pure | 11 | 11 | 7 | 7 / 7 | Pure |
| 2 | NorthSeaWars | 73 | 73 | 4 | 34 / 15 | NorthSea Wars |
| 3 | Caucasia | 118 | 118 | 5 | 37 / 23 | Caucasia |
| 4 | TreatyOfVerdun | 58 | 58 | 3 | 38 / 15 | 843: Treaty of Verdun |
| 5 | War2020 | 61 | 61 | 10 | 43 / 17 | War in 2020 |
| 6 | Hundred | 8 | 8 | 3 | 45 / 17 | Hundred |
| 7 | SouthAmerica4 | 7 | 7 | 4 | 48 / 24 | South America (4 players) |
| 8 | WhoControlsAmerica | 43 | 43 | 8 | 50 / 26 | Who controls America |
| 9 | BalkanWarsVI **(collision)** | 46 | 46 | 6 | 52 / 26 | Balkan Wars VI |
| 10 | Chromatic | 93 | 93 | 5 | 56 / 21 | Chromatic |
| 11 | SouthAmerica5 | 6 | 6 | 5 | 56 / 24 | South America (5 players) |
| 12 | SouthSahara | 149 | 149 | 5 | 60 / 25 | South of Sahara |
| 13 | Chesspolitik | 132 | 132 | 4 | 64 / 32 | Chesspolitik |
| 14 | PunicWars | 208 | 208 | 4 | 64 / 17 | Punic Wars |
| 15 | SailHo2 | 16 | 16 | 4 | 64 / 16 | Sail Ho II |
| 16 | TenSixtySix | 55 | 55 | 3 | 69 / 18 | 1066 |
| 17 | Baron1900 | 1900 | 1900 | 7 | 72 / 16 | 1900 |
| 18 | WesternEurope1300 | 145 | 145 | 5 | 73 / 36 | Western Europe 1300 |
| 19 | AnarchyInTheUK | 79 | 79 | 6 | 78 / 34 | Anarchy in the UK |
| 20 | Alacavre | 31 | 31 | 7 | 81 / 34 | Alacavre |
| 21 | Renaissance1453 | 107 | 107 | 7 | 82 / 35 | Renaissance - 1453 |
| 22 | Balkans1860 | 103 | 103 | 7 | 84 / 37 | Balkans 1860 |
| 23 | Maharajah | 74 | 74 | 7 | 84 / 37 | Maharajah |
| 24 | Scottish_Clan_Wars | 141 | 141 | 7 | 86 / 33 | Scottish Clan Wars |
| 25 | CelticBritain | 75 | 75 | 8 | 88 / 43 | Celtic Britain |
| 26 | ManifestDestiny | 112 | 112 | 5 | 88 / 39 | Manifest Destiny |
| 27 | Canton | 108 | 108 | 7 | 89 / 36 | Canton Diplomacy |
| 28 | Hussite | 47 | 47 | 9 | 90 / 47 | Hussite Wars |
| 29 | SpiceIslands | 116 | 116 | 7 | 90 / 35 | Spice Islands |
| 30 | AgeOfPericles | 78 | 78 | 7 | 91 / 39 | Age of Pericles |
| 31 | Imperium | 13 | 13 | 6 | 91 / 28 | Imperium Diplomacy |
| 32 | Karibik **(collision)** | 45 | 45 | 8 | 93 / 38 | Karibik |
| 33 | USofA | 56 | 56 | 8 | 93 / 38 | USA |
| 34 | Fubar | 39 | 39 | 6 | 95 / 34 | Fubar |
| 35 | Migraine | 21 | 21 | 8 | 95 / 38 | Migraine |
| 36 | SouthAmerica8 | 24 | 24 | 8 | 96 / 40 | South American Supremacy |
| 37 | HeptarchyIV | 89 | 89 | 7 | 98 / 38 | HeptarchyIV |
| 38 | Lepanto | 41 | 41 | 2 | 98 / 38 | Lepanto |
| 39 | DarkAges | 82 | 82 | 7 | 100 / 37 | Dark Ages |
| 40 | ColdWarRedux | 128 | 128 | 4 | 103 / 28 | Cold War Redux |
| 41 | DutchRevolt **(no `$id`)** | None | None | 5 | 103 / 40 | The Dutch Revolt |
| 42 | EmpiresCoalitions | 113 | 113 | 9 | 104 / 44 | 1800 - Empires and Coalitions |
| 43 | SpeedEuropa | 253 | 253 | 7 | 104 / 35 | Speed Europa |
| 44 | Germany1648 | 36 | 36 | 7 | 106 / 53 | Germany 1648 |
| 45 | AustrianSuccession | 117 | 117 | 9 | 108 / 51 | War of Austrian Succession |
| 46 | GreekDip | 35 | 35 | 6 | 110 / 34 | Greek Diplomacy |
| 47 | GreatLakes | 77 | 77 | 9 | 116 / 52 | Indians of the Great Lakes |
| 48 | MateAgainstMate | 37 | 37 | 8 | 116 / 47 | Mate Against Mate |
| 49 | Napoleonic | 101 | 101 | 10 | 116 / 35 | Napoleonic |
| 50 | Edwardian3 | 130 | 130 | 7 | 117 / 51 | Edwardian - 3rd Edition |
| 51 | Edwardian | 110 | 110 | 7 | 118 / 50 | Edwardian |
| 52 | Enlightenment | 76 | 76 | 10 | 120 / 57 | Enlightenment & Succession |
| 53 | AtlanticColonies | 99 | 99 | 4 | 121 / 49 | Atlantic Colonies |
| 54 | Habelya | 68 | 68 | 8 | 121 / 43 | Habelya |
| 55 | Abstraction3 | 67 | 67 | 7 | 122 / 48 | Abstraction III |
| 56 | TiglathPileser | 137 | 137 | 8 | 127 / 52 | Tiglath-Pileser |
| 57 | Europe1600 | 97 | 97 | 9 | 129 / 53 | 1600 |
| 58 | Mars | 80 | 80 | 6 | 130 / 40 | Mars |
| 59 | FirstCrusade | 98 | 98 | 7 | 134 / 52 | First Crusade |
| 60 | Europe1939 | 72 | 72 | 8 | 150 / 55 | Europe 1939 |
| 61 | AberrationV | 88 | 88 | 9 | 152 / 53 | Aberration V |
| 62 | WesternWorld_901 | 127 | 127 | 9 | 167 / 64 | Western World 901 |
| 63 | AmericanConflict | 69 | 69 | 6 | 173 / 56 | American Conflict |
| 64 | Empire1on1 | 33 | 33 | 2 | 180 / 59 | Fall of the American Empire: Civil War! |
| 65 | Viking | 63 | 63 | 8 | 184 / 85 | Viking Diplomacy IV |
| 66 | WWII | 87 | 87 | 5 | 186 / 74 | World War II |
| 67 | RatWars | 65 | 65 | 4 | 191 / 31 | Rat Wars |

Three of them cannot keep their upstream numbers: **BalkanWarsVI** wants 46 (GoT2 holds it),
**Karibik** wants 45 (GoT holds it), and **DutchRevolt** declares no `$id` at all.

### The 900 block does not work on this schema

Wave 3's first renumber, BalkanWarsVI 46 → **946**, installed a map with **zero territories** and
then created a game whose `variantID` came back as **255**. Both columns are
`tinyint(3) unsigned`:

```
wD_Games.variantID       tinyint(3) unsigned
wD_Territories.mapID     tinyint(3) unsigned
wD_VariantInfo.mapID     smallint(4) unsigned     <- which is why it looked fine in one place
```

MySQL clamps 946 to 255 on the way in, silently, so the variant info row says 946, the map rows
say 255 and nothing matches. `variant-registry.md`'s 900-block rule has been **corrected**:
renumbered ports take an ID from **254 downwards**. BalkanWarsVI is 254. The same limit rules out
three upstream IDs outright — `Baron1900` (1900), `SpeedEuropa` (253, which is fine) and
`PunicWars` (208, also fine); only Baron1900 needs renumbering for this reason.

### Wave 3 — how each install was run

Per variant, inside the twenty-minute budget:

1. **Security read first, before the folder is copied.** Every `.php` in the package is scanned
   for the usual negative list (`eval`, `exec`/`system`/`shell_exec`/`passthru`, backticks,
   `base64_decode`, remote includes, file writes, `wD_Users`/`wD_Sessions`, `$_GET`/`$_POST`/
   `$_REQUEST`/`$_SESSION`, `call_user_func`, `$$`) **plus the three wave-1 tells**: `STATICSRV`,
   `extends Maps` and `extends OrderArchiv`. Anything that hits is not copied at all.
   `extends OrderArchiv` **on its own** is not a blocker — it is the same inert dangling
   reference the in-tree, upstream Zeus5 and Duo already carry.
2. **Dependency check**: grep the package for `extends <Something>Variant` naming a variant that
   is not in the tree. This is what caught `SouthSahara` → `RuleExtensions`.
3. Folder copied; `cache/` created `chmod 777`; `resources/darkMode/style.css` generated from the
   variant's own `resources/style.css` (**none of the 67 ships one**, and `lib/html.php:646-647`
   links it unconditionally for every enabled variant in dark mode) by mixing each colour towards
   white until its luminance reaches 160, and rewriting `.country0` to
   `rgba(255, 255, 255, 0.8)`.
4. ID added to `Config::$variants`.
5. `POST admincp.php actionName=wipeVariants`, then
   `POST admincp.php actionName=updateVariantInfo variantID=<id>` **on a cold cache** — see the
   gotcha below — then the same action once more after the rows are committed.
6. `wD_Territories` row and supply-centre counts compared against the variant's own
   `install.php`; dropdown, `variants.php` and `map.php?variantID=<id>` checked.
7. Acceptance game with exactly the variant's player count (`admin` plus `player2..playerN`),
   driven through **Spring → Autumn → Builds** and left **paused**.

Maintenance mode was **off** for all of wave 3, so every player acted from their own logged-in
session rather than through wave 1's `?auid=N` admin switch.

### The gotcha that cost wave 3 the most time

**A variant can end up registered, cached, in the dropdown and drawing a map while
`wD_Territories` holds not one row for it.** `Alacavre`, `AnarchyInTheUK` and `Chesspolitik` all
did. The mechanism:

- the map rows are written by `WDVariant::initialize()`, which only runs when
  `variants/<X>/cache/data.php` is **absent** (`lib/variant.php:150`);
- the only `COMMIT` in a web request is in `close()` (`header.php:304`), reached from
  `libHTML::footer()`. A request that builds the variant object but exits another way —
  `map.php`, which writes a PNG and dies — writes `data.php` and commits **nothing**;
- `admincp actionName=wipeVariants` deletes *every* variant's `data.php` at once, and the SSE
  server's gamemaster driver is hitting the site once a second, so the window for some other
  request to rebuild `data.php` without committing is wide open.

Once `data.php` exists the install never runs again, and the variant is permanently half-installed.
The fix in the procedure: drive the install with `updateVariantInfo` (a normal page that reaches
the footer) on a **cold** `data.php`, then **verify the row count against `install.php` instead of
assuming it**, and delete `data.php` and retry if it is zero. `wD_VariantInfo` also has to be
written *after* the rows exist, because it is built from the variant object — Chesspolitik's info
row was empty until a second `updateVariantInfo` pass.

### Wave 3 batch 1 — sixteen maps

| Variant | `$id` | `$mapID` | Players | Territories / SCs (install.php = DB) | Solo target | gameID | Verdict |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Pure | 11 | 11 | 7 | 7 / 7 | 4 | **35** | **Pass** |
| SouthAmerica5 | 6 | 6 | 5 | 56 / 24 | 13 | **43** | **Pass** |
| SouthAmerica4 | 7 | 7 | 4 | 48 / 24 | 13 | **41** | **Pass** |
| Hundred | 8 | 8 | 3 | 45 / 17 | 9 | **40** | **Pass** |
| SailHo2 | 16 | 16 | 4 | 64 / 16 | 9 | **47** | **Pass** |
| Alacavre | 31 | 31 | 7 | 81 / 34 | 18 | **50** | **Pass** |
| WhoControlsAmerica | 43 | 43 | 8 | 50 / 26 | 14 | **42** | **Pass** |
| TreatyOfVerdun | 58 | 58 | 3 | 38 / 15 | 8 | **37** | **Pass** |
| War2020 | 61 | 61 | 10 | 43 / 17 | 9 | **39** | **Pass** |
| NorthSeaWars | 73 | 73 | 4 | 34 / 15 | 8 | **36** | **Pass** |
| AnarchyInTheUK | 79 | 79 | 6 | 78 / 34 | 18 | **51** | **Pass** |
| Chromatic | 93 | 93 | 5 | 56 / 21 | 11 | **44** | **Pass** |
| Caucasia | 118 | 118 | 5 | 37 / 23 | 12 | **38** | **Pass** |
| Chesspolitik | 132 | 132 | 4 | 64 / 32 | 17 | **49** | **Pass** |
| SouthSahara | 149 | 149 | 5 | 60 / 25 | 13 | **48** | **Pass** |
| BalkanWarsVI | **254** | **254** | 6 | 52 / 26 | 14 | **46** | **Pass** (renumbered from 46) |

**Every one of the sixteen matches its own `install.php` exactly** on both territory and
supply-centre count. None declares a `$supplyCenterTarget` of its own, so all sixteen take
`WDVariant::initialize()`'s `round(18/34 * supplyCenterCount)` — the issue-007 target trap does
not arise for any of them.

Worth calling out individually:

- **Pure (11)** is the smallest map this install has ever run: seven land territories, each a
  home supply centre, no sea and no neutrals. Like ClassicNoNeutrals, **it cannot reach a Builds
  phase in year one** — nobody gains a centre without dislodging a rival — so gameID 35 is two
  adjudicated Diplomacy phases and no builds.
- **War2020 (61)** is a **custom-start** variant like CustomStart, Classic1897 and
  ClassicChaoctopi: the game opens at turn 0 in a Builds phase with zero units, and all ten
  powers placed their opening units there. It is also the only wave-3 map that uses **all ten
  accounts**.
- **Chesspolitik (132)** is a chessboard — 64 territories, 32 of them supply centres, **756
  borders**, four powers, solo on 17.
- **Alacavre (31)** has the same 81 territories / 34 supply centres as Classic and is not
  remotely the Classic board; its `install.php` also uses an eight-column row format whose last
  column is the owning country's *name*.
- **SouthSahara (149)** is the only wave-3 variant with a **package dependency**: its variant
  class and two of its classes extend `RuleExtensionsVariant*`. `variants/RuleExtensions/` is now
  in the tree as a dependency only — it has a `variant.php` but no `install.php` and no `$id`,
  and it is deliberately **not** in `Config::$variants`.
- **Chromatic (93)** needed a third year: its neutrals are nowhere near the opening positions, so
  gameID 44 was played to **Winter 1903**, where the builds phase placed three units and
  destroyed one.
- **TreatyOfVerdun (37)** exercised **declining a build** — a country with a build due and every
  home centre occupied submitted `Wait`.

### Wave 3 — deferred so far

| Variant | vDip `$id` | Why |
| --- | ---: | --- |
| TenSixtySix | 55 | Fog family: `STATICSRV`, `extends Maps`, `extends OrderArchiv`, and `resources/{fogmap,fogmap_old,jsonBoardData}.php`. Placed and installed before the security read finished, then **fully backed out** — config entry, map 55 rows, `wD_VariantInfo` row and the folder itself. **ID 55 stays reserved.** |
| PunicWars | 208 | The same fog trio plus `resources/{orders,fogmap,jsonBoardData}.php`. **Never placed** — the security read now runs first. **ID 208 stays reserved.** |

Four fog variants are now deferred for one reason (ClassicFog 30, Classic1898Fog 134,
TenSixtySix 55, PunicWars 208). Defining `STATICSRV` and porting `Maps` and `OrderArchiv` would
unblock all four at once, and is the single highest-value piece of variant work left.

### The `config.php` line after wave 3 batch 1

```php
public static $variants=array(1=>'Classic',2=>'World',3=>'FleetRome',4=>'CustomStart',5=>'BuildAnywhere',6=>'SouthAmerica5',7=>'SouthAmerica4',8=>'Hundred',9=>'AncMed',11=>'Pure',12=>'Colonial',14=>'ClassicCrowded',15=>'ClassicFvA',16=>'SailHo2',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',22=>'Duo',23=>'ClassicGvI',25=>'ClassicGvR',26=>'ClassicFGvsRT',28=>'Classic1897',31=>'Alacavre',38=>'ClassicNoNeutrals',40=>'ClassicOctopus',42=>'ClassicVS',43=>'WhoControlsAmerica',45=>'GoT',46=>'GoT2',48=>'ClassicFGA',49=>'ClassicIER',50=>'ClassicGreyPress',54=>'ClassicChaoctopi',58=>'TreatyOfVerdun',61=>'War2020',62=>'ClassicEvT',70=>'Zeus5',73=>'NorthSeaWars',79=>'AnarchyInTheUK',90=>'ClassicAnkaraCrescent',91=>'ColdWar',93=>'Chromatic',118=>'Caucasia',122=>'ClassicBritain',123=>'ClassicBrazilian',132=>'Chesspolitik',133=>'Classic1898',149=>'SouthSahara',254=>'BalkanWarsVI');
```

### Wave 3 batch 2 — eleven more maps, and one schema wall

| Variant | `$id` | `$mapID` | Players | Territories / SCs (install.php = DB) | Solo target | gameID | Verdict |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Hussite | 47 | 47 | 9 | 90 / 47 | 24 | **61** | **Pass** |
| Maharajah | 74 | 74 | 7 | 84 / 37 | 19 | **56** | **Pass** (one PHP 8 fix) |
| CelticBritain | 75 | 75 | 8 | 88 / 43 | 23 | **58** | **Pass** (one PHP 8 fix) |
| AgeOfPericles | 78 | 78 | 7 | 91 / 39 | 20 | **63** | **Pass** |
| Balkans1860 | 103 | 103 | 7 | 84 / 37 | 19 | **55** | **Pass** |
| Renaissance1453 | 107 | 107 | 7 | 82 / 35 | 18 | **54** | **Pass** |
| Canton | 108 | 108 | 7 | 89 / 36 | 19 | **60** | **Pass** |
| ManifestDestiny | 112 | 112 | 5 | 88 / 39 | 21 | **59** | **Pass** |
| SpiceIslands | 116 | 116 | 7 | 90 / 35 | 19 | **62** | **Pass** |
| Scottish_Clan_Wars | 141 | 141 | 7 | 86 / 33 | 17 | **57** | **Pass** |
| WesternEurope1300 | 145 | 145 | 5 | 73 / 36 | 19 | **53** | **Pass** |
| Baron1900 | ~~252~~ (upstream 1900) | — | 7 | 97 / 39 | — | — | **Deferred** |

Again every one matches its own `install.php` exactly, and none declares its own
`$supplyCenterTarget`.

**Two PHP 8 breakages, one fix.** `implode($array, $glue)` — the argument order removed in PHP 8
— appears in `Maharajah/classes/OrderInterface.php:47` and in two files of `CelticBritain`. With
it, every member's `board.php` dies with *"implode(): Argument #2 ($array) must be of type
?array, string given"* and the game can never leave its first Diplomacy phase, because no one can
submit orders. The arguments are now swapped in place, with a comment, and the check is part of
the wave-3 procedure: **13 of the 67 candidates contain the pattern** (Africa, CelticBritain,
ClassicCataclysm, Edwardian, Edwardian3, EmpiresCoalitions, GobbleEarth, Maharajah, Mars, Viking,
World10, WWIV_V6 — and the in-tree Zeus5, where it sits in a file nothing calls).

**Baron1900 is the first variant to hit a schema wall.** Its `install.php` writes
`wD_Territories.buildEligibilityFlags`, a **column vDiplomacy has and this schema does not**, and
the autumn adjudication dies with `Unknown column 't.buildEligibilityFlags' in 'WHERE'`. Two
other vDip-only dependencies were fixed inside its folder on the way there and are worth
recording because they are cheap to spot:

- `classes/processMembers.php` reads **`$Game->targetSCs`** (vDiplomacy's per-game custom solo
  target) and **`$Game->maxTurns`** (its per-game turn limit). Neither property exists on
  webDiplomacy's `Game`, and under this install's error handler reading one is fatal. Both are
  now guarded with `isset()`, so the variant falls back to its own `$supplyCenterTarget`, which
  is what the code means anyway. **Baron1900 is the only one of the 67 candidates that uses
  either.**

It was backed out completely — config entry, map rows, `wD_VariantInfo` row and its acceptance
game — but **the folder was kept**, because unlike the fog variants it ships no front controller
under `resources/`.

Two operational traps met in this batch, both worth remembering:

- **A game with inconsistent pause fields breaks `index.php` for every user, not just its own
  players.** `objects/game.php:432-444` insists that a paused game has `processTime` NULL and
  `pauseTimeRemaining` set, and a not-paused game the reverse; violate it and *every* page that
  lists that game raises *"Not-paused game process-time values incorrectly set."* Never set
  `processTime` by hand on a paused game — unpause it with `admincp actionName=togglePause`
  first.
- **A crashed game stays crashed.** `processStatus='Crashed'` is sticky and the gamemaster skips
  the game forever after; it has to be put back to `Not-processing` once the cause is fixed.

### The `config.php` line after wave 3 batch 2

```php
public static $variants=array(1=>'Classic',2=>'World',3=>'FleetRome',4=>'CustomStart',5=>'BuildAnywhere',6=>'SouthAmerica5',7=>'SouthAmerica4',8=>'Hundred',9=>'AncMed',11=>'Pure',12=>'Colonial',14=>'ClassicCrowded',15=>'ClassicFvA',16=>'SailHo2',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',22=>'Duo',23=>'ClassicGvI',25=>'ClassicGvR',26=>'ClassicFGvsRT',28=>'Classic1897',31=>'Alacavre',38=>'ClassicNoNeutrals',40=>'ClassicOctopus',42=>'ClassicVS',43=>'WhoControlsAmerica',45=>'GoT',46=>'GoT2',47=>'Hussite',48=>'ClassicFGA',49=>'ClassicIER',50=>'ClassicGreyPress',54=>'ClassicChaoctopi',58=>'TreatyOfVerdun',61=>'War2020',62=>'ClassicEvT',70=>'Zeus5',73=>'NorthSeaWars',74=>'Maharajah',75=>'CelticBritain',78=>'AgeOfPericles',79=>'AnarchyInTheUK',90=>'ClassicAnkaraCrescent',91=>'ColdWar',93=>'Chromatic',103=>'Balkans1860',107=>'Renaissance1453',108=>'Canton',112=>'ManifestDestiny',116=>'SpiceIslands',118=>'Caucasia',122=>'ClassicBritain',123=>'ClassicBrazilian',132=>'Chesspolitik',133=>'Classic1898',141=>'Scottish_Clan_Wars',145=>'WesternEurope1300',149=>'SouthSahara',254=>'BalkanWarsVI');
```
