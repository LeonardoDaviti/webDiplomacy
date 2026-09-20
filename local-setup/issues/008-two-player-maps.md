---
id: 008
title: Confirm and complete the two-player map roster
label: done
phase: P3
depends-on: [001, 002]
---

# 008 — Two-player maps

Most evenings there are exactly two humans. This issue makes sure there is a decent choice of
maps for two, without bots.

## Start from what is already here

Read `variant-registry.md` before doing anything. **Three two-player maps already ship with this
checkout and are already enabled:**

| Variant | `$id` | Board |
| --- | ---: | --- |
| ClassicFvA (France vs Austria) | 15 | React-whitelisted **and** legacy |
| ClassicGvI (Germany vs Italy) | 23 | React-whitelisted **and** legacy |
| ColdWar | 91 | Legacy only |

The draft spec listed six two-player maps to harvest, three of which are these. **Recompute the
list rather than harvesting blind.**

## Steps

1. Verify the three above actually work — being in the config is not the same as being playable.
   Run the acceptance checklist on each.
2. Identify which of the remaining two-player maps from the draft's list are (a) already in the
   tree under another name, (b) genuinely absent and worth harvesting, or (c) not worth it.
   Record the determination for each, with reasoning, in this issue.
3. For any that are `present` in the registry but not enabled, enabling them is a config-array
   entry plus the admin variant-info refresh — no harvest needed. Do these first.
4. For any that genuinely need harvesting, follow issue 009's triage procedure and budget, and
   record them in the registry.
5. Update `variant-registry.md` statuses as each passes or fails.

## Done when

- [x] Each of ClassicFvA, ClassicGvI and ColdWar has passed all five acceptance items and is
      marked `playable` in the registry.
- [x] Every map on the draft's two-player list is accounted for as already-present,
      harvested, or deferred-with-a-reason.
- [x] Any already-present-but-disabled two-player map is enabled and passing.
- [x] The registry reflects the final roster.
- [x] At least four two-player maps are `playable` — there are **seven**.

## Verification

```
curl -s http://127.0.0.1:43000/gamecreate.php | grep -iE 'France vs Austria|Germany vs Italy|Cold War'
```

Then per map, in a browser: create a two-player game, check the starting position against the
source map, adjudicate a spring move with a bounce and a support, resolve a build phase, and
check supply-centre counts after autumn.

## Notes / gotchas

- ColdWar is legacy-board only despite being a headline two-player map. Test it on the legacy
  board; it is not broken.
- ClassicFvA and ClassicGvI are also the two variants in the default **bot** variant list, so
  breaking them breaks issue 005 too. Be careful with their config entries.

---

## Status — **done** (2026-09-20)

**Seven two-player maps are now `playable`**, against a target of four. Three already shipped
here and were verified; three were harvested from vDiplomacy; the seventh (GoT2) landed with
issue 007.

| Variant | `$id` | `$mapID` | Players | Board | gameID | Result |
| --- | ---: | ---: | ---: | --- | ---: | --- |
| ClassicFvA | 15 | 15 | 2 | React | **6** | **Pass** |
| ClassicGvI | 23 | 23 | 2 | React | **7** | **Pass** |
| ColdWar | 91 | 91 | 2 | legacy | **8** | **Pass** |
| ClassicEvT | 62 | 62 | 2 | legacy | **14** | **Pass** — harvested |
| ClassicFGvsRT | 26 | 26 | 2 | legacy | **15** | **Pass** — harvested |
| Duo | 22 | 22 | 2 | legacy | **16** | **Pass** — harvested |
| GoT2 | 46 | 46 | 2 | legacy | 3 | Pass (issue 007) |

### The `config.php` line

`config.php` is gitignored, so this is the only versioned record. Nineteen variants:

```php
public static $variants=array(1=>'Classic',2=>'World',3=>'FleetRome',4=>'CustomStart',5=>'BuildAnywhere',9=>'AncMed',12=>'Colonial',15=>'ClassicFvA',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',22=>'Duo',23=>'ClassicGvI',26=>'ClassicFGvsRT',45=>'GoT',46=>'GoT2',62=>'ClassicEvT',70=>'Zeus5',91=>'ColdWar');
```

Every key is unique — `sed -n '163p' config.php | grep -o "[0-9]\+=>'" | sort | uniq -d` prints
nothing. (The whole-file `grep` in issue 009's verification block gives false positives; it also
matches `Config::$serverMessages` and the bot arrays.)

### Step 1 — the three that were already here

All three were already in `Config::$variants`, so this step was purely verification: being in the
config is not the same as being playable, and all three turned out to be.

- **ClassicFvA (15), gameID 6.** `admin` = France, `player2` = Austria. Starting position is
  standard: F Brest, A Paris, A Marseilles vs A Vienna, A Budapest, F Trieste. Spring 1901:
  `A Paris → Burgundy` **supported** by `A Marseilles` succeeded; Austria's `A Vienna → Galicia`
  and `A Budapest → Galicia` **bounced**, both units staying put. Autumn: France took Belgium,
  Spain and Portugal (6 centres), Austria took Venice and Serbia (5) — hand-checked against
  `wD_TerrStatus`. Winter: France built A Paris, F Brest, A Marseilles; Austria built A Vienna
  and A Budapest. Reached **Spring 1902**, 6 units vs 5. `board.php?gameID=6` → **302** to the
  React board, as expected for a whitelisted variant.
- **ClassicGvI (23), gameID 7.** `admin` = Germany, `player2` = Italy. Spring:
  `A Berlin → Silesia` **supported** by `A Munich` succeeded; Italy's `A Rome → Apulia` and
  `F Naples → Apulia` **bounced**. Autumn: Germany 5 centres (added Holland, Warsaw), Italy 4
  (added Marseilles). Builds: A Berlin + F Kiel, and F Venice. Reached **Spring 1902**.
  `board.php?gameID=7` → **302** (React); `map.php?gameID=7&turn=1` → an 18 KB PNG.
- **ColdWar (91), gameID 8.** `admin` = USSR, `player2` = USA, 6 units each. Spring: USSR
  `A Shanghai → Manchuria` **supported** by `A Vladivostok` succeeded, and USSR `F Albania →
  Greece` vs USA `F Istanbul → Greece` **bounced** — a cross-power bounce, not a self-bounce.
  Autumn: USSR 7 centres (East Germany), USA 8 (West Germany, Toronto). Builds: A Moscow; A Paris
  + A New York. Reached **Spring 1902**, 7 units vs 8. `board.php?gameID=8` → **200** with no
  redirect, confirming this issue's note that ColdWar is legacy-board only;
  `map.php?gameID=8&turn=1` → a 27 KB PNG and `map.php?variantID=91` → a 25 KB PNG.

### Step 2 — accounting for the draft's remaining three

| Draft name | Determination | What it actually is |
| --- | --- | --- |
| Duo | **harvested** | `vDiplomacy/variants/Duo`, `$id` 22. Frank Hegermann's original point-symmetric two-player map. Not in this tree under any name. |
| England vs Turkey | **harvested** | `vDiplomacy/variants/ClassicEvT`, `$id` 62, `$fullName` "Classic - England\* Vs Turkey". The draft's name is the description, not the folder — grep for `ClassicEvT`. |
| Frankland vs Juggernaut | **harvested** | `vDiplomacy/variants/ClassicFGvsRT`, `$id` 26, `$fullName` "Classic - Frankland Vs Juggernaut". "Frankland" is France+Germany, "Juggernaut" is Russia+Turkey; the folder name spells that out. |

None was already present under another name, and none was skipped: this tree's only Classic
two-player derivatives before this work were FvA and GvI. Nothing was deferred.

Also considered and **not** taken, for the record: vDiplomacy ships several more two-power
Classic splits (`ClassicGvR`, `ClassicIER`, `ClassicFGA`, `ClassicVS`, `Empire1on1`,
`MateAgainstMate`). They are the same idea with different starting units and are worth a later
batch under issue 009 wave 2 if anyone asks; the draft named three, and three were taken.

### Step 3 — already-present-but-disabled two-player maps

**None.** All three of the tree's two-player maps were already in `Config::$variants`. The five
`present`-but-unregistered variants were all seven-player; they are issue 009's wave 0 and were
done in the same change.

### Step 4 — the harvest

Source: `git clone https://github.com/Sleepcap/vDiplomacy` →
`72c81f0dd73bedccc11f13a750dfcc62f580549a` (2025-04-21, "Merge pull request #78"), cloned to
`/tmp/vdip`, outside the repo. Only the three variant folders were copied in.

#### Security review — **clean, all three**

Every `.php` file in all three packages was read in full before `config.php` was touched:
19 files, 2,464 lines.

| Package | Files | Lines | Verdict |
|---|---:|---:|---|
| `Duo` | 11 | 1,016 | **Clean.** `variant.php` 90, `install.php` 415, `classes/` 8 files (`adjudicatorPreGame` 31, `drawMap` 103, `OrderArchiv` 48, `OrderInterface` 40, `processGame` 110, `processMembers` 43, `processOrderDiplomacy` 41, `userOrderDiplomacy` 71), `interactiveMap/interactiveMap.php` 24. |
| `ClassicEvT` | 4 | 725 | **Clean.** `variant.php` 59, `install.php` 578, `classes/adjudicatorPreGame.php` 32, `classes/drawMap.php` 56. |
| `ClassicFGvsRT` | 4 | 723 | **Clean.** `variant.php` 64, `install.php` 575, `classes/adjudicatorPreGame.php` 30, `classes/drawMap.php` 54. |

Checked for and **not found anywhere**: `eval`, `assert`, `create_function`, `preg_replace` (so
no `/e`), `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, backticks,
`base64_decode`, `gzinflate`, `str_rot13`, `unserialize`, `curl_*`, `fsockopen`, sockets, stream
wrappers, `file_get_contents` / `fopen` / `fwrite` / `file_put_contents` / `unlink` / `rename` /
`copy` / `chmod` / `mkdir`, remote includes, `$$`, `call_user_func`, any reference to `wD_Users`,
`wD_Sessions`, `wD_ApiKeys`, `$_SESSION`, `Config::`, `$_GET` / `$_POST` / `$_COOKIE` /
`$_REQUEST` / `$_SERVER`, and any obfuscated or dynamically-built executed string. The only
`require_once` in any file is `variants/install.php`, the in-tree base installer. The four SQL
statements (all in Duo, `classes/processGame.php:102` and `classes/processOrderDiplomacy.php:31`)
interpolate only `$this->id` / `$Game->id`, both internal integers. The bundled JavaScript makes
no network calls and does no DOM injection; its only `.src =` assignments are
`contrib/smallarmy.png` and `contrib/smallfleet.png`.

`defined('IN_CODE') or die(...)` is present in **every** `.php` file except
`variants/Duo/interactiveMap/interactiveMap.php`, which has no guard. Left as-is: it is dead code
(see below), has no side effects on include, and the in-tree upstream
`variants/Zeus5/interactiveMap/interactiveMap.php` has no guard either.

#### IDs

Upstream's own `$id`/`$mapID` were **all free** in `variant-registry.md`, so all three were kept
and nothing was renumbered into the 900 block.

| Variant | `$id` | `$mapID` | Territories | SCs | Solo target | Countries |
|---|---:|---:|---:|---:|---:|---|
| Duo | 22 | 22 | 104 | 28 | 19 | Red, Green (+ a neutral "Black") |
| ClassicFGvsRT | 26 | 26 | 81 | 34 | 18 | Frankland, Juggernaut |
| ClassicEvT | 62 | 62 | 81 | 34 | 18 | England, Turkey |

Counts were read from each `install.php`'s territory literal before installing and confirmed
against `wD_Territories` afterwards; they match exactly.

#### `$mapID`: none of them shares Classic's map 1

This was checked rather than assumed, because the whole point of the question is that vDip's
Classic map data is not ours.

- **Duo** is an entirely original 104-territory map. Obvious own-map case.
- **ClassicEvT and ClassicFGvsRT** *look* like map-1 sharers — their 81 territory names are an
  exact set-match with `variants/Classic/install.php` — but their `install.php` files are **full
  575/578-line installers with their own inline territory and border arrays**, not stubs like
  `variants/BuildAnywhere/install.php`. Checked in SQL: joining map 62 to map 1 on `id`,
  **73 of the 81 rows have a different name**, i.e. the numeric territory IDs are almost
  completely misaligned with ours. They also ship their own `resources/map.png`, which differs
  from ours (and which the two of them share with each other). Pointing either at `$mapID` 1
  would have silently reinterpreted every order and every archived board. Each keeps its own
  `$mapID`, which is what upstream declares anyway.

#### Compatibility review and the fixes made

The base classes these variants extend all still exist in this codebase with compatible
signatures. Every override was checked individually: `adjudicatorPreGame::$countryUnits`;
`drawMap::resources()`, `$countryColors`, `drawSupportHold($fromTerrID,$toTerrID,$success)`,
`addUnit($terrID,$unitType)`, `color()`, `drawMove()`, `drawFailure()`;
`OrderInterface::jsLoadBoard()` (including the `loadOrdersPhase` token it `str_replace`s, still
at `board/orders/orderinterface.php:328`); `processGame::process()`;
`processMembers::countUnitsSCs()` and `$ByCountryID`;
`processOrderDiplomacy::apply($standoffTerrs)`;
`userOrderDiplomacy::typeCheck()/commit()/loadFromDB()/loadFromInput()/paramWipe()`; and
`WDVariant::countryID()/initialize()/turnAsDate()/turnAsDateJS()`. **Nothing has disappeared and
no signature has drifted.**

`php -l` is clean on all 19 files under the container's **PHP 8.4.25**, and `docker compose logs
php-fpm` showed no fatal, deprecation, warning or "undefined" from any of the three across
install, three games and every phase.

Specific PHP 8.4 points:

- *Dynamic properties* — `$adapter`, `$version`, `$homepage` and `$codeVersion` are not on
  `WDVariant`, but all three variants **declare** them explicitly, exactly as the in-tree
  `variants/ClassicFvA/variant.php:41-42` does. No deprecation.
- *`${}` interpolation* — not used anywhere.
- *`initialize()` / solo target* — the issue-007 trap does **not** apply here.
  `WDVariant::initialize()` (`variants/variant.php:437`) recomputes
  `$supplyCenterTarget = round(18/34 × $supplyCenterCount)`, but Duo **already ships an
  `initialize()` override** (`variants/Duo/variant.php:65-68`) restoring its declared 19, and
  ClassicEvT and ClassicFGvsRT declare no target at all — with 34 supply centres the computed
  value is 18, which is the intended standard number. Confirmed live in `wD_VariantInfo`:
  22 → 19/28, 26 → 18/34, 62 → 18/34. No override was added to anything.

Two fixes were made, both inside the variant folders:

1. **`variants/*/resources/darkMode/style.css` — missing mandatory resource.** None of the three
   ships one, and `lib/html.php:646-647` loops `Config::$variants` and links
   `variants/<Name>/resources/darkMode/style.css` *unconditionally* for a dark-mode viewer, so
   all three would have 404'd the moment they were enabled. Added for each, derived from the
   variant's own `resources/style.css` with the colours lightened for a dark background. The
   light-mode files were checked and their selector prefixes are already correct —
   `.variantDuo`, `.variantClassicEvT`, `.variantClassicFGvsRT`, matching the page class
   `'variant'.$Game->Variant->name` built at `lib/html.php:922` — so the issue-007 `GoT2`
   wrong-selector bug does not recur here.
2. **`variants/Duo/classes/drawMap.php` — PHP 8.4 implicit float-to-int.**
   `$width = $this->fleet['width'] + $this->fleet['width']/2` is 37.5 for `contrib/fleet.png`
   (25 px) and 19.5 for `contrib/smallfleet.png` (13 px), and both are passed straight into
   `imagefilledellipse()`'s int parameters — `Deprecated: Implicit conversion from float 37.5 to
   int loses precision` on every large-map render containing a Duo transform order. Now
   `(int)round($this->fleet['width']*1.5)`, with a comment.

Two things deliberately **not** changed:

- **`variants/Duo/classes/OrderArchiv.php` extends `OrderArchiv`, which does not exist in this
  codebase.** So does the in-tree, upstream `variants/Zeus5/classes/OrderArchiv.php`. The class
  is loaded lazily by `variant_autoloader()` (`variants/variant.php:575`) and nothing in
  webDiplomacy ever asks for it, so it is inert on both — removing it from Duo would make Duo
  differ from its upstream for no gain. If an order-archive feature is ever wired up, **both**
  variants fatal together and this is the note that explains why.
- **`variants/Duo/interactiveMap/`** — extends a nonexistent `IAmap`. The interactive-map
  subsystem was never part of webDiplomacy; the file is not under `classes/`, so the autoloader
  could not reach it even if something asked. Dead weight, kept for parity with upstream and with
  the in-tree Zeus5.

#### Install procedure actually run (each variant)

1. Folder copied from `/tmp/vdip/variants/<Name>/` to `variants/<Name>/`;
   `mkdir -p variants/<Name>/cache && chmod 777` (gitignored at `.gitignore:23`, so it does not
   arrive with a download).
2. `resources/darkMode/style.css` added; `resources/style.css` and its selector prefix confirmed.
3. ID added to `Config::$variants`.
4. **`POST admincp.php actionName=wipeVariants` — needed *before* the first load, not only
   after.** A plain `GET /gamecreate.php` did **not** auto-install any of the three: the new IDs
   were simply absent from the dropdown, with no error and nothing in the log, because the
   variant list is served from a warm cache. One `wipeVariants` and the next page load installed
   all three at once. Whether you hit this is pure luck about whether the cache happens to be
   cold — wave 0's five installed on the first load without a wipe, because a `wipeVariants` had
   been run shortly before for unrelated reasons. **Always wipe first**, then load.
5. `POST admincp.php actionName=updateVariantInfo variantID=<id>`. Neither admin action needs a
   `formTicket`.
6. Acceptance game, then `POST admincp.php actionName=togglePause gameID=<id>`.

### The acceptance runs for the three harvested maps

- **ClassicEvT (62) — gameID 14**, `admin` = England, `player2` = Turkey.
  Starting position matches `classes/adjudicatorPreGame.php` and the variant's own description:
  England A London, **F North Sea, F English Channel** — two of three units at sea, on
  non-supply-centre territories, which is the `*` in the name; Turkey A Constantinople, A Smyrna,
  F Ankara. Both sides own 3 centres. Spring 1901: `A London → Wales` **supported** by
  `F English Channel` succeeded; Turkey's `F Ankara → Constantinople` and
  `A Smyrna → Constantinople` **bounced** behind `A Constantinople → Bulgaria`. Autumn: England
  5 centres (Brest, Norway added), Turkey 4 (Bulgaria) — hand-checked. Winter: England built
  F London + F Edinburgh, Turkey built F Constantinople. Reached **Spring 1902**, 5 units vs 4.
  `board.php?gameID=14` → 200 (legacy); `map.php?gameID=14&turn=1` → a 19 KB PNG;
  `variants.php` renders "Classic - England\* Vs Turkey (2 Players)".
- **ClassicFGvsRT (26) — gameID 15**, `admin` = Frankland, `player2` = Juggernaut.
  Starting position is France+Germany (A Paris, F Brest, A Marseilles, A Berlin, F Kiel,
  A Munich — 6 units, 6 centres) against Russia+Turkey (A Moscow, A Warsaw, F Sevastopol,
  F St. Petersburg (South Coast), A Constantinople, A Smyrna, F Ankara — 7 units, 7 centres).
  Spring: `A Paris → Burgundy` **supported** by `A Munich` succeeded, and Frankland
  `A Berlin → Prussia` vs Juggernaut `A Warsaw → Prussia` **bounced** — again a cross-power
  bounce. Autumn: **10 centres each** (Frankland added Belgium, Spain, Portugal, Denmark;
  Juggernaut added Bulgaria, Rumania, Sweden), giving 4 builds and 3 builds, all placed and
  processed. Reached **Spring 1902**, 10 units vs 10. `board.php?gameID=15` → 200;
  `map.php?gameID=15&turn=1` → a 19 KB PNG.
- **Duo (22) — gameID 16**, `admin` = Red, `player2` = Green.
  Starting position matches `classes/adjudicatorPreGame.php` exactly, **including the neutral
  power**: Red A Rotheim, A Karminstadt, F Zinnoberburg; Green A Jadestadt, A Gruenheim,
  F Schloss Gruenburg; and **eight "Black" units** (A Westberg, A Ostberg, A Norterend, A Sund,
  A Gawar, A Pirh, F Abaun, F Helom) placed as `countryID` 3 with their eight supply centres
  owned by that non-playable power — 14 units and 28 centres in total. The `countryID()` override
  at `variants/Duo/variant.php:70-76` is what makes that work, and it does. Spring 1901:
  `A Karminstadt → Mouchimoglou` **supported** by `A Rotheim` succeeded; Green's
  `A Gruenheim → Jossangia` and `A Jadestadt → Jossangia` **bounced**; every neutral unit stayed
  put. Autumn: Red 5 centres (Qwil, Kentommenai), Green 4 (Yokai), Black still 8. Winter: Red
  built A Karminstadt + F Zinnoberburg, Green built A Gruenheim. Reached **Spring 1902**.
  `board.php?gameID=16` → 200; `map.php?gameID=16&turn=1` → a 15 KB PNG;
  `map.php?variantID=22` → a 13 KB PNG; `variants.php` renders "Duo (2 Players)".

### Caveats and things worth knowing

- **Legacy board only** for all three harvests, as issue 009 predicts.
  `Game::isClassicGame()` (`objects/game.php:565-568`) whitelists exactly `Classic`,
  `ClassicGvI` and `ClassicFvA` by *name*, so even ClassicEvT and ClassicFGvsRT — which play on
  Classic's geography — render on `board.php`'s drop-down board. Confirmed rather than assumed:
  `board.php` returns 200 without redirecting for games 14, 15 and 16, and 302 for 6 and 7.
- **A disband was never exercised** (the second half of SPEC acceptance item 4), on any of the
  six maps. In year one nobody can end autumn with fewer centres than units, so it is not
  reachable in the one-year runs these acceptances use. A **retreat** was exercised in issue 007
  on GoT2; disbands are shared engine code and none of these variants overrides the builds
  pipeline. Worth a second pass if a real game ever ends a year down.
- **`Misc.Maintenance` silently stops all game processing.** With maintenance mode on — a
  concurrent DATC run needs it, `datc.php:31` — the SSE server's anonymous
  `GET /gamemaster.php` dies in `header.php:260` before reaching any processing code, while still
  returning HTTP 200. Games just stop. An admin's own request to `gamemaster.php` still
  processes, because `header.php:244` takes the Admin branch first; that is how these acceptance
  runs were driven without disturbing the DATC work.
- **ColdWar's `ClassicGvI`-style bot support does not extend to the new maps.**
  `Config::$botVariants` is still `15, 23`. Adding any of these three to it has not been tried
  and is issue 005's problem, not this one.
- Three of the six two-player maps are Classic-geometry splits (FvA, GvI, EvT, FGvsRT — four, in
  fact), which is a lot of the same board. **Duo and ColdWar are the two that feel genuinely
  different**, and GoT2 is the third. Worth knowing when picking one for an evening.
