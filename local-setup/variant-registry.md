# Variant registry

The authoritative list of which numeric ID belongs to which variant on **this** install.
Nothing is installed before its row exists here, and the row is added in the same change that
installs it.

See `SPEC.md` → Implementation Decisions → *Variants* for the rules. In short:

- Every variant has an **`$id`** (its own identity, and the key it is registered under in the
  site config's variant array) and a **`$mapID`** (the geography it installs and draws).
  Derivative variants deliberately share a parent's map ID — four Classic derivatives below
  share map ID 1. That is correct; do not "fix" it.
- Every ID below is **reserved permanently**, enabled or not.
- **IDs 900 and above are reserved for renumbered ports.** When a harvested variant's upstream
  ID collides with a row here, renumber the newcomer into the 900 block and record its original
  ID in Notes. Never displace an incumbent.
- Author attribution is copied from the variant's own definition and must be preserved.

## Status vocabulary

| Status | Meaning |
| --- | --- |
| `enabled` | Registered in the site config and installed. |
| `present` | Folder is in the tree but the ID is not in the config array, so the site never loads it. |
| `porting` | Being worked on; not yet passing the acceptance checklist. |
| `playable` | Installed and has passed all five acceptance-checklist items in `SPEC.md`. |
| `deferred` | Failed triage. Not installed. Revisit only on demand. |

`enabled` means "the config knows about it"; `playable` means "a human has verified it". A
variant can be `enabled` and not yet `playable` — that gap is the work.

## In-tree variants

All fourteen ship with this checkout at `master @ 7be9bb05`. `$id` and `$mapID` were read from
each variant's own definition file. The nine marked `enabled` are the ones in the sample
config's variant array.

| Variant | `$id` | `$mapID` | Players | Source | Status | Notes |
| --- | ---: | ---: | ---: | --- | --- | --- |
| Classic | 1 | 1 | 7 | upstream 1.83 (Avalon Hill) | enabled | The reference map. One of only three variants the React board renders. |
| World | 2 | 2 | 17 | upstream 1.83 (David Norman) | enabled | World Diplomacy IX. Seventeen powers — `$countries` in `variants/World/variant.php` lists 17. Legacy board only. |
| FleetRome | 3 | 1 | 7 | upstream 1.83 (Avalon Hill) | playable | Classic with a fleet in Rome — Italy starts `F Rome` instead of `A Rome`. Shares Classic's map; `install.php` is a one-line `require_once` of Classic's. Wave 0, issue 009; gameID 9. Legacy board only. |
| CustomStart | 4 | 1 | 7 | upstream 1.83 (Avalon Hill) | playable | Classic, but the game **opens in a Builds phase with no units** and each country places its own start. Shares Classic's map (stub `install.php`). Wave 0, issue 009; gameID 10. Legacy board only. **Zeus5 subclasses this variant's `adjudicatorPreGame`, so it must stay in the tree.** |
| BuildAnywhere | 5 | 1 | 7 | upstream 1.83 (Avalon Hill) | playable | Classic, build in any owned SC. Shares Classic's map (stub `install.php`). Wave 0, issue 009; gameID 11 — verified by building `A Rumania`, a conquered non-home centre, in Winter 1902. Legacy board only. |
| AncMed | 9 | 9 | 5 | upstream 1.83 (Don Hessong) | enabled | The Ancient Mediterranean. Legacy board only. |
| Colonial | 12 | 12 | 7 | upstream 1.83 (Peter Hawes) | playable | Colonial Diplomacy. 125 territories / 62 SCs, solo target 30. Wave 0, issue 009; gameID 12. Already overrides `initialize()` to keep its solo target. Legacy board only. |
| ClassicFvA | 15 | 15 | 2 | upstream 1.83 | playable | France vs Austria. **Two-player.** React-board whitelisted. Ships an interactive-map thumbnail. Issue 008 acceptance: gameID 6. |
| ClassicChaos | 17 | 17 | 34 | upstream 1.83 | enabled | Chaos: one power per supply centre — `$countries` in `variants/ClassicChaos/variant.php` lists 34, and `$description` reads "The classic map for 34 players." Ships an interactive-map thumbnail. |
| Modern2 | 19 | 19 | 10 | upstream 1.83 (Vincent Mous) | enabled | Modern Diplomacy II. Legacy board only. |
| Empire4 | 20 | 20 | 10 | upstream 1.83 (Vincent Mous) | enabled | Fall of the American Empire IV. Legacy board only. |
| ClassicGvI | 23 | 23 | 2 | upstream 1.83 | playable | Germany vs Italy. **Two-player.** React-board whitelisted. Ships an interactive-map thumbnail. Also in the default bot variant list. Issue 008 acceptance: gameID 7. |
| Zeus5 | 70 | 70 | 7 | upstream 1.83 (Northcott / Davis / Reinecker) | playable | Zeus 5, WWII from Olympus. 113 territories / 41 SCs, solo target 21, **custom start** (opens in Builds with no units). Ships an interactive-map thumbnail. Wave 0, issue 009; gameID 13. Already overrides `initialize()`. Its `adjudicatorPreGame` extends **CustomStart's**, so variant 4 must stay in the tree. Legacy board only. |
| ColdWar | 91 | 91 | 2 | upstream 1.83 | playable | **Two-player**, USSR vs USA, 104 territories / 27 SCs, solo target 17 (its own `initialize()` override). Legacy board only — not React-whitelisted despite being a headline two-player map. Issue 008 acceptance: gameID 8. |

### What this table already settles

- **Wave 0 was five rows:** FleetRome (3), CustomStart (4), BuildAnywhere (5), Colonial (12),
  Zeus 5 (70). All five are now registered, installed and `playable` — see issue 009's Wave 0
  status. Each kept its upstream `$id` and `$mapID`; nothing was renumbered.
- **Three two-player maps already exist:** ClassicFvA (15), ClassicGvI (23), ColdWar (91). Issue
  008 starts from these rather than from nothing.
- **The draft spec's harvest list was wrong.** Chaos, Build Anywhere, Cold War, France vs
  Austria and Germany vs Italy were all listed as things to fetch. They are all here.
- **Taken after issues 007, 008 and 009:** variant and map IDs 1–5, 9, 12, 15, 17, 19, 20, 22,
  23, 26, 45, 46, 62, 70, 91 (variant 3, 4 and 5 share map 1). Still free: 6, 7, 8, 10, 11, 13,
  14, 16, 18, 21, 24, 25, 27–44, 47–61, 63–69, 71–90, 92–99 — but treat them as *upstream's to
  allocate*, and prefer the 900 block for anything ported in here whose own ID collides.

## Ported and harvested variants

Rows are added by issues 007, 008 and 009 as work lands. Issues 007 (Westeros) and 008
(two-player maps) have landed; issue 009's wave 0 needed no rows here, only status changes above.

| Variant | `$id` | `$mapID` | Players | Source | Status | Notes |
| --- | ---: | ---: | ---: | --- | --- | --- |
| GoT2 | 46 | 46 | 2 | mcoirad/gameofthrones-diplomacy `GoT2` @ `8e1be97` (Dario Mitchell, after echepron / evil-minion) | playable | Issue 007. "Game of Thrones - Tully vs Lannister". Upstream `$id`/`$mapID` 46 were both free, so they were kept — no 900-block renumber. Own map data (ownership and two borders differ from `GoT`), so its own `$mapID`. Legacy board only. Solo target 20 (see Notes below). |
| GoT | 45 | 45 | 8 | mcoirad/gameofthrones-diplomacy `master` @ `d1e01af` (Dario Mitchell, after echepron / evil-minion) | playable | Issue 007. "Game of Thrones", the full eight-house map. Upstream `$id`/`$mapID` 45 were both free, so they were kept. Legacy board only. Solo target 35 (see Notes below). |
| Duo | 22 | 22 | 2 | Sleepcap/vDiplomacy @ `72c81f0` (Frank Hegermann; webDiplomacy adapter Oliver Auth; variant version 1.0, code version 0.20; <http://www.dipwiki.com/?title=Duo>) | playable | Issue 008. **Two-player**, and the only genuinely new geography of the three: 104 territories / 28 SCs on an original point-symmetric map, solo target 19. Upstream `$id`/`$mapID` 22 were free, so they were kept. Eight **neutral "Black" units** are placed at the start and eight SCs belong to that non-playable power — `countryID()` is overridden to resolve it. Already overrides `initialize()` to keep its target. Legacy board only. |
| ClassicFGvsRT | 26 | 26 | 2 | Sleepcap/vDiplomacy @ `72c81f0` (adapter Orathaic, version 1.0.4, after ClassicFvA 1.0.1) | playable | Issue 008. "Classic - Frankland Vs Juggernaut" — **two-player**, France+Germany (6 units) against Russia+Turkey (7). Upstream `$id`/`$mapID` 26 were free. Classic's 81 territories / 34 SCs and an exact name-for-name match with our Classic, **but its own `$mapID` and its own full 575-line installer**, not a stub: territory *IDs* and pixel coordinates differ from our map 1, so sharing map 1 would have been wrong. Legacy board only. |
| ClassicEvT | 62 | 62 | 2 | Sleepcap/vDiplomacy @ `72c81f0` (adapter Orathaic, version 1.1) | playable | Issue 008. "Classic - England* Vs Turkey" — **two-player**. Upstream `$id`/`$mapID` 62 were free. Same shape as ClassicFGvsRT: Classic geometry, own `$mapID`, own 578-line installer. The `*` is deliberate — England's two fleets start in **open sea** (North Sea, English Channel) to offset its opening, so two of its three starting units are not on supply centres. Legacy board only. |
| ClassicGvR | 25 | 25 | 2 | Sleepcap/vDiplomacy @ `72c81f0` (adapter Orathaic, version 1.0.4, after ClassicFvA) | playable | Issue 009 wave 1. "Classic - Germany vs Russia" — **two-player**. Classic's 81 territories / 34 SCs, solo target 18, but its **own full installer and `$mapID`**, not a stub, so it must not share map 1. gameID 18. Legacy board only. |
| ClassicFGA | 48 | 48 | 3 | Sleepcap/vDiplomacy @ `72c81f0` (adapter Orathaic, version 1, code 1.0.2) | playable | Issue 009 wave 1. "Classic - France vs Germany vs Austria" — the tree's **first three-player variant**. 81 / 34, target 18, own installer and `$mapID`. gameID 19. Legacy board only. |
| ClassicIER | 49 | 49 | 3 | Sleepcap/vDiplomacy @ `72c81f0` (adapter Orathaic, code 1.0.2) | playable | Issue 009 wave 1. "Classic - Italy+ Vs England+ Vs Russia" — three-player; England gains Holland and Italy gains Trieste as home centres to offset Russia's four builds, so all three start with four units. 81 / 34, target 18, own installer and `$mapID`. gameID 20. **One local fix**: its `$description` contained an unescaped apostrophe that broke `admincp`'s `updateVariantInfo` (see issue 009). Legacy board only. |
| ClassicBritain | 122 | 122 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Bruce McIntyre & Danny Loeb; adapter Enriador & Oliver Auth, code 1.1) | playable | Issue 009 wave 1. "Classic - Britain" — England starts with **six armies** (Clyde, Edinburgh, Liverpool, London, Wales, Yorkshire), which are also supply centres, so the board has 81 territories but **37 SCs** and a solo target of 20. Own installer and `$mapID`. gameID 21. Legacy board only. |
| ClassicCrowded | 14 | 14 | 11 | Sleepcap/vDiplomacy @ `72c81f0` (Carey Jensen / Oliver Auth, version 1.5.3; <http://www.variantbank.org/results/rules/c/crowded.htm>) | playable (render-only) | Issue 009 wave 1. "Classic - Crowded" — the Classic board for **eleven** powers: the seven plus **Balkan, Lowland, Norway and Spain**, built out of what are normally the neutrals. 81 territories / **35 SCs, all of them home centres — no neutrals at all**; solo target 18 (its own `initialize()` override). Own installer and `$mapID`. **Acceptance is render-only**: this install has ten accounts, one short of a game. Verified statically instead — all 35 starting units land on a supply centre owned by the right one of the 11 powers, with terrain that permits the unit type. Legacy board only. |
| ClassicNoNeutrals | 38 | 38 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (adapter Carey Jensen / Oliver Auth / Orathaic, code 1.0.1) | playable | Issue 009 wave 1. "Classic - NoNeutrals" — the Classic start with **every neutral supply centre demoted to a plain territory**: 81 territories / **22 SCs**, all home centres, solo target **12** (its own `initialize()` override). Own installer and `$mapID`. gameID 23. **A builds phase was never reached** — with no neutrals, nobody gains a centre without dislodging a rival, so year one has no builds by construction; see issue 009. Legacy board only. |
| ClassicGreyPress | 50 | **1** | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Oliver Auth, version 1.1) | playable | Issue 009 wave 1. "Classic - GreyPress" — Classic plus **anonymous press**. The **only wave-1 variant that shares Classic's `$mapID` 1**, and legitimately so: its `install.php` is a one-line `require_once('variants/Classic/install.php')` stub with no territory data, exactly like FleetRome/CustomStart/BuildAnywhere, and its single class is a `Chatbox` subclass that never names a territory. Its variant class extends **`ClassicVariant`**, so variant 1 must stay in the tree. gameID 24; the grey press itself was exercised — a message sent to the extra "Grey Press" tab arrived at its target from country 8, not from the sender. Legacy board only. |
| ClassicBrazilian | 123 | 123 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (GROW; adapter Enriador & Oliver Auth, code 1.0; <http://uk.diplom.org/?page=aboutbrazilian>) | playable | Issue 009 wave 1. "Classic - Brazilian", the unofficial Brazilian edition: same 81 territories but **35 SCs** and a different Italian/English start (England F London + F Edinburgh + A Liverpool, Italy A Venice + **F Rome** + F Naples). Solo target 19. Own installer and `$mapID`. gameID 22. Legacy board only. |
| Classic1897 | 28 | 28 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Mark Nelson, Josh Smith & Rick Westerman; adapter Oliver Auth / Carey Jensen, version 1.0.4; <http://www.variantbank.org/results/rules/1/1897.htm>) | playable | Issue 009 wave 1. "Classic - 1897" — a **custom-start** variant: `assignUnits()` and `assignUnitOccupations()` are disabled, so the game opens with **zero units** in a Builds phase that its `processGame::changePhase()` override inserts before Spring 1901 and its `panelGameBoard::datetxt()` override labels *"Autumn, 1897"*. Each power builds one unit to declare its initial home centre. 81 / 34, target 18, own installer and `$mapID`. gameID 27. Legacy board only. |
| ClassicOctopus | 40 | 40 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Emmanuele Ravaioli "Tadar Es Darden" / Oliver Auth, code 1.0.2) | playable | Issue 009 wave 1. "Classic - Octopus" — every unit has **double movement**: it may move to or support any territory adjacent to an adjacent territory, ignoring intervening units. Implemented purely as extra border rows — **1,205 borders against Classic's 431** — on the same 81 territories / 34 SCs. Target 18. Own installer and `$mapID`. gameID 25, where `A Moscow → Norway` in Spring 1901 shows the rule working. Legacy board only. |
| ClassicAnkaraCrescent | 90 | 90 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Captainmeme, version 1, code 1.0.1) | playable | Issue 009 wave 1. "Classic - Ankara Crescent" — all sea provinces border each other and every non-SC coastal province, and all non-SC land provinces border each other, so everyone is everyone's neighbour and stalemate lines are impossible. Again pure border data: **1,691 borders against Classic's 431**, same 81 / 34, target 18. Own installer and `$mapID`. gameID 26. Legacy board only. |
| Classic1898 | 133 | 133 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Randy Davis; adapter Yuriy Hryniv aka Flame, version 1.0) | playable | Issue 009 wave 1. "Classic - 1898" — each power starts with **one unit** (England F Edinburgh, France A Brest, Italy A Naples, Germany A Kiel, Austria A Trieste, Turkey A Smyrna, Russia A St. Petersburg) and **exactly one home supply centre**; the other 74 territories are neutral. Because there are almost no home centres, it also applies the **build-anywhere** rule through its own `userOrderBuilds::toTerrIDCheck()` and `processOrderBuilds`. 81 / 34, target 18, own installer and `$mapID`. gameID 28 — verified by building `A Sweden`, `A Venice` and `A Bulgaria`, none of them home centres. Legacy board only. |
| ClassicVS | 42 | 42 | 2–7 | Sleepcap/vDiplomacy @ `72c81f0` (Oliver Auth, version 1, code 1.1.1) | playable | Issue 009 wave 1. "Classic - Pick your countries" — the **powers are chosen from the game's name**: a name containing `(EFG)` makes it a three-player England/France/Germany game, `?` adds a random power, and a name with no parenthesised code falls back to all seven. Implemented with a `__call()` override on the variant that rewrites `$countries` before `Members`, `processMembers`, `panelMembers` and `panelMembersHome` are built. Map 42 has **82 territories** — Classic's 81 plus a dummy `PreGameCheck` — and 34 SCs, target 18. Own installer and `$mapID`. gameIDs 29 (all seven) and 30 (three, from the name `ClassicVS pick (EFG) 009`). Legacy board only. |
| ClassicChaoctopi | 54 | 54 | 34 | Sleepcap/vDiplomacy @ `72c81f0` (kaner406; adapter Emmanuele Ravaioli / Carey Jensen / Oliver Auth, version 1.0.1, code 1.0.2) | playable (render-only) | Issue 009 wave 1. "Classic - Chaoctopi" — **Chaos** (one power per supply centre, 34 of them) crossed with **Octopus** (double movement: 1,205 borders against Classic's 431). Like Classic1897 it disables `assignUnits()` and opens in a turn-0 Builds phase. 81 territories / 34 SCs, each owned by its own power, target 18. Own installer and `$mapID`. **Acceptance is render-only** — 34 players against this install's ten accounts. Legacy board only. |

**Config line** (`config.php` is gitignored, so this is the only versioned record):

```php
public static $variants=array(1=>'Classic',2=>'World',3=>'FleetRome',4=>'CustomStart',5=>'BuildAnywhere',9=>'AncMed',12=>'Colonial',14=>'ClassicCrowded',15=>'ClassicFvA',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',22=>'Duo',23=>'ClassicGvI',25=>'ClassicGvR',26=>'ClassicFGvsRT',28=>'Classic1897',38=>'ClassicNoNeutrals',40=>'ClassicOctopus',42=>'ClassicVS',45=>'GoT',46=>'GoT2',48=>'ClassicFGA',49=>'ClassicIER',50=>'ClassicGreyPress',54=>'ClassicChaoctopi',62=>'ClassicEvT',70=>'Zeus5',90=>'ClassicAnkaraCrescent',91=>'ColdWar',122=>'ClassicBritain',123=>'ClassicBrazilian',133=>'Classic1898');
```

Thirty-three variants, every key unique:

```sh
sed -n '163p' config.php | grep -o "[0-9]\+=>'" | sort | uniq -d    # prints nothing
```

(Do **not** use `grep -o '[0-9]\+=>' config.php` on the whole file, as issue 009's verification
block suggests — it also matches `Config::$serverMessages` and the bot/variant-mod arrays and
reports duplicates that are not duplicate variant IDs.)

**Notes on both Westeros variants**

- Both maps are the same 135 territories / 51 supply centres; only supply-centre *ownership*
  (and `The North`, `Maidenpool` and two border rows) differs, so they must not share a
  `$mapID`.
- `WDVariant::initialize()` unconditionally overwrites `$supplyCenterCount` and
  `$supplyCenterTarget` from the database, computing the target as
  `round(18/34 * 51) = 27`. Both variants declare their own solo target (20 for GoT2, 35 for
  GoT), so each now restores it in an `initialize()` override, exactly as `ColdWar` does.
- They are named `GoT` / `GoT2` in the New Game dropdown, **not** "Westeros" — grep for `GoT`,
  not `Westeros`.
- **IDs 45 and 46 (variant and map) are now reserved permanently.**

**Notes on the three variants harvested from vDiplomacy (issue 008)**

- Source: `git clone https://github.com/Sleepcap/vDiplomacy` at
  `72c81f0dd73bedccc11f13a750dfcc62f580549a` (2025-04-21). Only the three folders below were
  copied in; nothing else from that tree is present.
- **Security review: clean.** Every `.php` file in all three packages was read in full. Zero hits
  across all of them for `eval`, `assert`, `create_function`, `preg_replace`, `exec`,
  `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, backticks, `base64_decode`,
  `gzinflate`, `str_rot13`, `unserialize`, `curl_*`, `fsockopen`, sockets, stream wrappers, any
  file read or write, remote includes, `wD_Users` / `wD_Sessions` / `wD_ApiKeys`, `$_SESSION`,
  `$_GET` / `$_POST` / `$_COOKIE` / `$_REQUEST` / `$_SERVER`, `Config::`, `$$`,
  `call_user_func`, or any obfuscated or dynamically-built executed string. The only
  `require_once` in any of them is `variants/install.php`, the in-tree base installer. The four
  SQL statements in Duo interpolate only internal integer IDs.
- **Every one kept its upstream `$id` and `$mapID`** — 22, 26 and 62 were all free, so nothing
  was renumbered into the 900 block and no incumbent was displaced.
- **ClassicEvT and ClassicFGvsRT must not share Classic's `$mapID` 1.** Their territory *names*
  match ours exactly, but their `install.php` files are full installers with their own IDs and
  coordinates: 73 of the 81 rows land on a different numeric `id` than our map 1. They also ship
  their own `resources/map.png`, which differs from ours.
- **Missing dark-mode stylesheets were added.** None of the three shipped
  `resources/darkMode/style.css`, which `lib/html.php:646-647` links unconditionally for every
  enabled variant when the viewer has dark mode on. Each now has one, derived from its own
  `resources/style.css` with the colours lightened. The light-mode selector prefixes were checked
  and are already correct (`.variantDuo`, `.variantClassicEvT`, `.variantClassicFGvsRT`).
- **One PHP 8.4 fix**, in `variants/Duo/classes/drawMap.php`: `$width` was
  `fleet_width + fleet_width/2`, i.e. 37.5 (or 19.5 on the small map), passed straight to
  `imagefilledellipse()`'s int parameters — an implicit-float-to-int deprecation on every render
  of a Duo transform order. Now `(int)round($this->fleet['width']*1.5)`.
- **Duo registers `$variantClasses['OrderArchiv']`, and no `OrderArchiv` base class exists in
  this codebase.** Left alone deliberately: the in-tree, upstream `variants/Zeus5` does exactly
  the same thing, the class is loaded lazily by `variant_autoloader()`, and nothing ever asks for
  it — so it is inert on both. The same goes for `variants/Duo/interactiveMap/interactiveMap.php`
  (extends a nonexistent `IAmap`; the interactive-map subsystem was never part of webDiplomacy,
  and the file is not under `classes/` so the autoloader could not reach it anyway).
- **IDs 22, 26 and 62 (variant and map) are now reserved permanently.**

## Deferred

Variants that failed the twenty-minute triage budget. Each row records what broke, so that a
later attempt starts from the failure rather than repeating it.

| Variant | Source | Attempted | Failure | Notes |
| --- | --- | --- | --- | --- |
| ClassicFog | Sleepcap/vDiplomacy @ `72c81f0`, `$id`/`$mapID` **30** | 2026-09-20, issue 009 wave 1 | **`Undefined constant "STATICSRV"`** — `variants/ClassicFog/classes/OrderInterface.php:22` uses a vDiplomacy-only constant that does not exist in webDiplomacy. Every board load by a member of a fog game dies with it, so the game is unplayable. | It installed cleanly (91 territories — Classic's 81 plus ten fog pseudo-territories — and 34 SCs) and appeared in the dropdown; the failure is at the board. Two further blockers behind it: `classes/Maps.php` extends **`Maps`**, a vDiplomacy-only base class absent here, and `classes/OrderArchiv.php` extends **`OrderArchiv`**, also absent — and in vDip that class is what hides other players' orders, so even with `STATICSRV` defined the fog would be incomplete. **The folder was removed rather than left in place** (a deliberate departure from the usual "leave the folder"): it ships `resources/{fogmap,orders,jsonBoardData}.php`, front controllers that `require_once('header.php')`, and they were confirmed **web-reachable and executing** at `/variants/ClassicFog/resources/fogmap.php` while the folder was present. Orphan rows for map 30 were deleted from `wD_Territories`/`wD_Borders`/`wD_CoastalBorders`. **ID 30 stays reserved.** |
| Classic1898Fog | Sleepcap/vDiplomacy @ `72c81f0`, `$id`/`$mapID` **134** | 2026-09-20, issue 009 wave 1 | Same as ClassicFog, **not installed at all**: its `classes/OrderInterface.php:22` is the same line with the same `STATICSRV`, and it ships the same `Maps`/`OrderArchiv` subclasses and the same `resources/fogmap.php` front controller. | Classic 1898's one-unit start combined with fog. Revisit only together with ClassicFog — one fix (define `STATICSRV`, port `Maps` and `OrderArchiv`) unblocks both. **ID 134 stays reserved.** |

Both deferrals are the same root cause: **vDiplomacy's fog-of-war subsystem depends on core
classes and constants that webDiplomacy does not have.** Everything else attempted in issues 008
and 009 came in under the twenty-minute budget.
