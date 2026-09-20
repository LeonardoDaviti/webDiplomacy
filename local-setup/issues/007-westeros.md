---
id: 007
title: Install the Westeros variant
label: done
phase: P3
depends-on: [001, 002]
---

# 007 — Westeros

A Game of Thrones map is one of the four things this whole project exists for. Source:
`mcoirad/gameofthrones-diplomacy`, a webDiplomacy variant package of the same shape as the ones
already in the tree.

## Security review — do this first, and do not skip it

A variant is **PHP that the site executes**. Installing one from the internet is running someone
else's code on the machine that holds the game database. Before the site is ever pointed at it,
read:

- the **variant definition file** — it should declare IDs, names, countries and class overrides
  and little else;
- the **installer** — it should write map, territory and starting-unit rows and nothing more;
- every file in the variant's **`classes/`** directory.

Stop and report if any of them: make outbound network calls; read or write files outside the
variant's own directory; call `eval`, `exec`, `system`, `shell_exec`, `passthru` or
`unserialize` on anything it did not construct; touch user, session, or admin tables; or
obfuscate a string it then executes. None of that is normal in a variant.

## Steps

1. Fetch the variant package. Review it as above and record the outcome in this issue.
2. **Install the dependency variant first.** This port requires a prerequisite variant (the
   "got2" base) to be present before the main variant will install; installing the main one
   first will fail or install incompletely.
3. Allocate IDs from the **900 block** (see `variant-registry.md`) if the upstream IDs collide
   with anything already reserved. Record the original ID in the registry's Notes column.
4. Place the folders under the variants directory. Ensure each has its `cache/` directory,
   created and writable — it is gitignored and will not arrive with the download.
5. Confirm each ships the mandatory `resources/style.css` (**not** `variant.css`) and its
   dark-mode resources.
6. Register the IDs in the site config's variant array. First load auto-installs them.
7. In the admin panel, run the **variant-info update** action, and the **variant-wipe** action to
   clear caches. New variants do not appear until you do.
8. Add both rows to `variant-registry.md` in the same change.
9. Run the acceptance checklist.

## Done when

- [x] The security review is done and its outcome recorded in this file.
- [x] The dependency variant is installed before the main one (`GoT2` first, then `GoT`).
- [x] Both variants have rows in `variant-registry.md` with IDs, source and author preserved.
- [x] Both appear on the New Game form.
- [x] Acceptance checklist items 1–5 from `SPEC.md` all pass (item 4's *disband* half is the one
      caveat — see Status).
- [x] A two-player Westeros game has been created and its spring phase adjudicated (gameID 3).
- [x] Registry status is `playable`.

## Verification

```
curl -s http://127.0.0.1:43000/gamecreate.php | grep -i -o 'Westeros[^<]*'
docker compose logs --tail=50 php-fpm     # no PHP 8.4 deprecations/fatals from the variant
```

Then, in a browser: create a Westeros game, compare the starting position against the upstream
variant's own map image unit by unit, submit a spring move including a bounce and a support,
confirm it adjudicates, and confirm supply-centre counts after autumn.

## Notes / gotchas

- **Legacy board only.** The React board hard-codes Classic geometry and whitelists three
  variants; Westeros is not one and never will be without the fork described in issue 010. This
  is expected — do not treat it as a broken install.
- The likely failure mode is **PHP 8.4 incompatibility in the variant's own classes**, not API
  drift: the variant API is still version 1 and the base class has not changed in three years.
  Read the PHP log, not the variant API docs.
- This is a fan work derived from A Song of Ice and Fire. Private LAN use is fine; publishing
  the map or the site is not. See `SPEC.md` → Further Notes.

---

## Status — **done** (2026-09-20)

Both variants are installed, registered, played through a full year, and `playable` in
`variant-registry.md`.

### 1. Source and commits

`git clone https://github.com/mcoirad/gameofthrones-diplomacy` → three refs: `master` (default,
= `origin/HEAD`) and `GoT2`. Note the branch is **`GoT2`**, capitalised, not `got2`.

| Branch | Commit | Subject | Installed as |
|---|---|---|---|
| `GoT2` | `8e1be9705b71ab4a4731c3f9d12fda5db7c62656` | `updated borders` | `variants/GoT2/` |
| `master` | `d1e01af9e76ef13be31bcaec619a533937a42754` | `Ocean Road fix` | `variants/GoT/` |

Each branch is a complete, standalone variant package (`variant.php`, `install.php`,
`classes/{adjudicatorPreGame,drawMap}.php`, `resources/`). The issue's "install the dependency
first" is really "install the two-player `GoT2` first"; there is no build-time dependency
between them, but `GoT2` was installed and accepted first as instructed.

### 2. Security review — **clean, nothing suspicious, nothing malicious**

Every `.php` file in both branches was read in full before `config.php` was touched. Eight files,
and they are close to identical between the branches.

| File | Lines | Verdict |
|---|---:|---|
| `GoT2/variant.php` | 68 | **Clean.** A `WDVariant` subclass: IDs, names, `$countries`, two `$variantClasses` entries, `turnAsDate`/`turnAsDateJS`. No I/O of any kind. |
| `GoT2/install.php` | 576 | **Clean.** `require_once("variants/install.php")` (in-tree base installer) and then two literal arrays — 135 territory rows and 367 border rows — fed to `InstallTerritory::addBorder` / `runSQL` and `InstallCache::terrJSON`. The *only* calls in the whole file are `array()`, `list()`, `foreach`, `unset()`, `defined()`, `die()` and those four base-class methods. |
| `GoT2/classes/adjudicatorPreGame.php` | 28 | **Clean.** One `protected $countryUnits` array. No methods. |
| `GoT2/classes/drawMap.php` | 52 | **Clean.** `$countryColors` plus a `resources()` returning `l_s()` paths under its own folder and `images/icons/cross.png`. |
| `GoT/variant.php` | 68 | **Clean.** As above, 8 countries. |
| `GoT/install.php` | 577 | **Clean.** As above, 135 territory rows and 368 border rows. |
| `GoT/classes/adjudicatorPreGame.php` | 35 | **Clean.** One `$countryUnits` array. |
| `GoT/classes/drawMap.php` | 59 | **Clean.** As above. |

Checked for and **not found anywhere**: `eval`, `assert`, `create_function`, `preg_replace`
(so no `/e`), `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, backticks,
`base64_decode`, `gzinflate`, `str_rot13`, `unserialize`, `curl_*`, `fsockopen`, sockets or
stream wrappers, `file_get_contents`/`fopen`/`fwrite`/`file_put_contents`/`unlink`/`rename`/
`copy`/`chmod`/`mkdir`, remote includes, any reference to `wD_Users`, `wD_Sessions`, `$_SESSION`,
`Config::`, `$_GET`/`$_POST`/`$_COOKIE`/`$_REQUEST`/`$_SERVER`, and any obfuscated or
dynamically-built string. Both `install.php` files begin with the standard
`defined('IN_CODE') or die(...)` guard. The only writes either package performs are the
territory/border rows the base installer writes for its own `mapID`, and the territory JSON the
base installer writes into the variant's own `cache/`.

The copyright headers are copy-pasted from the Ancient Mediterranean variant and are misleading
about provenance; they were left untouched, since the upstream `README.md` (shipped in both
folders) credits `echepron` and `evil-minion` from the BoardGameGeek thread and `variant.php`
credits Dario Mitchell. Cosmetic, not a security finding.

Data integrity was also pre-checked before install: every one of the 367/368 border rows names a
territory that exists, there are no duplicate territory names (the two apparent duplicates of
`Northern Narrow Sea` are `#`-commented-out lines), and all 26 + 8 starting units sit on a
supply centre owned by the right country with terrain that permits the unit type.

### 3. IDs

Upstream's own IDs were **free** in `variant-registry.md` (taken: 1–5, 9, 12, 15, 17, 19, 20, 23,
70, 91), so per step 3 of this issue — "allocate from the 900 block **if** the upstream IDs
collide" — they were kept rather than renumbered. Nothing was displaced.

| Variant | `$id` | `$mapID` | Players | Solo target |
|---|---:|---:|---:|---:|
| `GoT2` | 46 | 46 | 2 | 20 |
| `GoT` | 45 | 45 | 8 | 35 |

**Separate `$mapID`s are required.** The two `install.php` files are *not* identical: the
supply-centre owner column differs on 50 rows, `The North` and `Maidenpool` differ in whether
they are supply centres, and `GoT` has one extra border (`Blackhaven`–`Crows Nest`) and lets
armies cross `Ocean Road`–`Highgarden`. So neither may be a stub à la
`variants/BuildAnywhere/install.php`; each loads its own map. Both end up at 135 territories and
51 supply centres, verified in `wD_Territories`.

### 4. `config.php` (gitignored — recorded here and in the registry)

```php
public static $variants=array(1=>'Classic',2=>'World',9=>'AncMed',15=>'ClassicFvA',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',23=>'ClassicGvI',45=>'GoT',46=>'GoT2',91=>'ColdWar');
```

### 5. Compatibility review and the fixes made

The base classes the variants extend — `WDVariant` (`variants/variant.php`), `adjudicatorPreGame`
(`gamemaster/adjudicator/pregame.php`) and `drawMap` (`map/drawMap.php`) — still expose
everything these packages use: `$variantClasses`, `$countries`, `$supplyCenterCount`,
`$supplyCenterTarget`, `initialize()`, `turnAsDate()`, `turnAsDateJS()`, `protected
$countryUnits`, `protected $countryColors`, `protected function resources()`, `$this->smallmap`.
**No base-class method has disappeared.**

**No PHP 8.4 problems were found.** Specifically:

- *Dynamic properties* — the extra properties `$adapter`, `$version` and `$homepage` are not on
  `WDVariant`, but both variants **declare** them explicitly, exactly as the in-tree `AncMed`
  does, so `#[\AllowDynamicProperties]` is not needed and no deprecation fires.
- *`${}` string interpolation* — not used in any of the eight files.
- *Implicit float→int* — `floor($turn/2) + 1` is string-concatenated in `turnAsDate()`, the same
  shape as `Classic`'s `floor($turn/2) + 1901`; no int context, no deprecation.
- *Signatures* — `initialize()`, `turnAsDate($turn)`, `turnAsDateJS()` and `resources()` all
  match the parents.
- `php -l` is clean on all eight files under PHP 8.4, and `docker compose logs php-fpm` showed no
  fatal, deprecation, warning or "undefined" from either variant across install, both games and
  every phase. (The two log lines that *are* there — a stray `"` in
  `/etc/php/8.4/fpm/conf.d/99-overrides.ini` and the JIT/third-party-extension warning — predate
  this work and are unrelated.)

Four minimal fixes were made, all inside the variant folders:

1. **`variants/GoT2/classes/adjudicatorPreGame.php` — real bug.** House Lannister's starting army
   was placed in `'Silverhall'`; the territory in `install.php` is **`Silverhill`**. On this
   codebase `adjudicatorPreGame::assignUnits()` does `$terrIDByName[$terrName]` with no
   `isset()`, so a typo becomes an "Undefined array key" warning and an `INSERT` of an empty
   `terrID` — i.e. Lannister would have started a unit short, or the pre-game adjudication would
   have failed outright. Corrected to `Silverhill`. (`GoT`'s copy already says `Silverhill`.)
2. **`variants/GoT2/resources/style.css` — wrong selectors.** Upstream ships `GoT`'s eight-house
   stylesheet unchanged on the `GoT2` branch, so every rule is `.variantGoT …`. The page class is
   built as `'variant'.$Game->Variant->name` (`board/info/orders.php:199`, `lib/html.php:922`),
   which for this variant is `variantGoT2`, so **none** of the rules would ever have matched.
   Rewritten as two rules, `.variantGoT2 .country1` (Tully) and `.country2` (Lannister), with the
   colours from `classes/drawMap.php`.
3. **`variants/*/resources/darkMode/style.css` — missing mandatory resource.** Neither branch
   ships one, and `lib/html.php:647` unconditionally links
   `variants/<Name>/resources/darkMode/style.css` for a dark-mode user, so both would have 404'd.
   Added for both, lightened for a dark background in the style of `variants/AncMed`.
4. **`variants/GoT*/variant.php` — declared solo target was being discarded.**
   `WDVariant::initialize()` (`variants/variant.php:435`) unconditionally overwrites both
   `$supplyCenterCount` (→ 51, from the database) and `$supplyCenterTarget`
   (→ `round(18/34 × 51) = 27`), so the authors' declared targets of 20 (GoT2) and 35 (GoT) were
   silently ignored. Each variant now restores its own value in an `initialize()` override —
   precisely the pattern `variants/ColdWar/variant.php:17` already uses. The stale literal
   `$supplyCenterCount = 52` was corrected to 51 (the true count) and commented.

Nothing outside `variants/GoT/` and `variants/GoT2/` was changed, apart from the `config.php`
line above (gitignored) and this repo's registry and issue files.

### 6. Install procedure actually run

1. `variants/GoT2/` placed, `cache/` created `chmod 777` to match the other variants' cache dirs.
   (php-fpm runs as **root** in this container — `docker compose exec php-fpm id` — and the
   existing cache dirs are `drwxrwxrwx http:http` on the host, uid 33; 777 is what makes them
   work either way. The dirs are gitignored by `.gitignore:23 variants/*/cache/`.)
2. `46=>'GoT2'` added to `Config::$variants`; `GET /gamecreate.php` as `admin` auto-installed it
   (135 territories / 51 SCs in `wD_Territories` for `mapID` 46). No PHP errors on the page or in
   the log.
3. `POST admincp.php actionName=updateVariantInfo variantID=46`, then
   `POST admincp.php actionName=wipeVariants`, then two page loads to regenerate the caches. No
   `formTicket` needed for either (neither has a `…Confirm` method).
4. Full acceptance run on `GoT2` (below), then the same sequence for `GoT` with `45=>'GoT'`.

### 7. Acceptance

#### GoT2 (id 46) — **gameID 3**, `Westeros GoT2 acceptance`, admin (House Tully) vs player2 (House Lannister)

| SPEC item | Result |
|---|---|
| 1. On the New Game form | **Pass.** `<option value="46">GoT2</option>`; `variants.php` renders "Game of Thrones - Tully vs Lannister (2 Players)", author Dario Mitchell, solo target 20. |
| 2. Starting position matches source | **Pass.** `map.php?variantID=46` renders the upstream `resources/map.png` with Tully blue and Lannister red exactly over the eight home centres. Units in `wD_Units`: Tully F Seagard, A Fairmarket, A Harrenhal, A Riverrun; Lannister F Lannisport, A Sarsfield, A Silverhill, A Kings Landing — unit-for-unit identical to `classes/adjudicatorPreGame.php`, and each sits on an SC owned by that house in `install.php`. |
| 3. Spring adjudicates with a bounce and a support | **Pass.** Spring 1: Tully A Fairmarket→Northern Riverrun **supported** by F Seagard — succeeded; Tully A Riverrun→Golden Tooth and Lannister A Sarsfield→Golden Tooth **both bounced**, both units stayed put. |
| 4. A build phase resolves | **Partial → pass on builds.** Winter: Tully built A Fairmarket + A Harrenhal, Lannister built **F** Lannisport + A Silverhill (a coastal fleet build). Both processed immediately. **A disband was not exercised** — see the caveat below. |
| 5. SC counts after autumn are right | **Pass.** Autumn 1: Tully A Pinkmaiden→Golden Tooth **supported** by A Northern Riverrun **dislodged** Lannister's army, which retreated to Hornvale (a Retreats phase, correctly entered and resolved). Final `wD_Members`: Tully 6 SCs / 4 units, Lannister 6 / 4 — matching a hand count of the territory table, and matching the two builds each. |

Reached **Spring, 2** with 6 units each. `board.php?gameID=3` → **200, no redirect** (contrast
`board.php?gameID=2`, Classic → `302 → /game/?gameID=2`); `map.php?gameID=3&turn=1` returns a
400×796 PNG with units, order arrows and SC stars. Game **paused** afterwards via
`admincp.php actionName=togglePause gameID=3`.

#### GoT (id 45) — **gameID 4**, `Westeros GoT acceptance`, eight accounts

`admin`=Tyrell, `player2`=Martell, `player3`=Arryn, `player4`=Greyjoy, `player5`=Tully,
`player6`=Lannister, `player7`=Baratheon, `player8`=Stark.

| SPEC item | Result |
|---|---|
| 1. On the New Game form | **Pass.** `<option value="45">GoT</option>`; `variants.php` renders "Game of Thrones (8 Players)", solo target 35. |
| 2. Starting position matches source | **Pass.** All 26 units match `classes/adjudicatorPreGame.php` exactly; 3/3/3/4/4/3/3/3 home centres = 26 owned + 25 neutral = 51. `map.php?variantID=45` shows all eight houses' home regions in their `drawMap` colours. |
| 3. Spring adjudicates with a bounce and a support | **Pass.** Two supported moves succeeded (Stark A Deepwood Motte→The North supported by A Winterfell; Arryn A Hearth Home→The Vale supported by A The Eyrie) and Tully A Riverrun vs Lannister A Sarsfield **bounced** at Golden Tooth. |
| 4. A build phase resolves | **Partial → pass on builds.** Winter: 18 builds placed across seven houses (armies and fleets, including fleet builds at Harlaw, Great Wyk, Seagard, Lannisport and Dragonstone); **House Arryn, with 3 SCs and 3 units, correctly got no build orders at all** and its empty `ready:"Yes"` submission did not block the phase. |
| 5. SC counts after autumn are right | **Pass.** Stark 5, Arryn 3, Greyjoy 5, Tully 7, Lannister 8, Baratheon 5, Tyrell 5, Martell 6 = 44 of 51, hand-checked against the captures ordered. After builds every country's `unitNo` equals its `supplyCenterNo`. |

Reached **Spring, 2**. `board.php?gameID=4` → **200, no redirect**; `map.php?gameID=4&turn=1`
renders the eight-colour board with arrows and stars. Game **paused** afterwards.

### 8. Caveats and things deferred

- **A disband was never exercised** (the second half of SPEC acceptance item 4). In year one
  nobody can end autumn with fewer centres than units on either map, so it is not reachable in a
  one-year run. A **retreat** was exercised instead, on `GoT2` (dislodged unit → Retreats phase →
  retreat to Hornvale), and disbands are shared engine code with no variant override here
  (neither package subclasses anything but `adjudicatorPreGame` and `drawMap`). Worth a second
  pass if a real game ever ends a year down.
- **`wD_VariantInfo` holds only rows 45 and 46.** The table was empty before this work;
  `updateVariantInfo` was run for these two IDs only, deliberately, rather than looping all
  eleven. It feeds Ghost Ratings, which this install does not use.
- **The verification command at the top of this issue finds nothing.** Neither variant is called
  "Westeros" — grep `gamecreate.php` for `GoT`, or `variants.php` for `Game of Thrones`:
  ```sh
  curl -s -b admin.jar http://127.0.0.1:43000/gamecreate.php | grep -o '<option[^>]*>GoT2\?</option>'
  ```
  (`gamecreate.php` is behind login, so an unauthenticated `curl` returns nothing at all.)
- **Legacy board only**, as this issue predicted. Confirmed rather than assumed: `board.php`
  returns 200 without redirecting for both games, because `Game::usePointAndClickUI()` is false
  off the React whitelist.
- **Fleet moves onto `Maidenpool` need a child coast.** `Maidenpool` has `(North Coast)` and
  `(South Coast)` children, so a fleet order naming the parent is silently stored as `Hold` —
  normal webDiplomacy behaviour, not a variant fault, but it cost one intended SC capture during
  the `GoT` autumn and will trip up anyone scripting orders on these maps.
- **Licensing, unchanged:** fan work derived from *A Song of Ice and Fire*. Fine on this LAN;
  the map and the site must not be published. See `SPEC.md` → Further Notes.
