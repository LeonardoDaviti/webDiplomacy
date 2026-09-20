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
