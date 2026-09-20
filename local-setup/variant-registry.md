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
- **Renumbered ports take an ID from the 250-down band, not the 900 block.** When a harvested
  variant's upstream ID collides with a row here, renumber the newcomer and record its original
  ID in Notes. Never displace an incumbent. **Correction, issue 009 wave 3: the 900 block cannot
  be used on this schema.** `wD_Games.variantID` and `wD_Territories.mapID` are both
  `tinyint(3) unsigned`, so any ID above 255 is silently clamped to 255 — a variant registered as
  900 installs its map as 255 and its games are created against variant 255. The first renumber
  here (BalkanWarsVI) was allocated **254**, and later ones count downwards from there, skipping
  anything an incumbent already holds. `wD_VariantInfo.mapID` is a `smallint`, which is why the
  mismatch does not announce itself: the variant info row says 900 while the map says 255.
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
- **Taken after issues 007, 008 and 009 (waves 0, 1 and 3):** variant and map IDs 1–9, 11, 12,
  14–17, 19, 20, 22, 23, 25, 26, 28, 30, 31, 38, 40, 42, 43, 45, 46, 48–50, 54, 55, 58, 61, 62,
  70, 73, 79, 90, 91, 93, 118, 122, 123, 132, 133, 134, 149, 208, 254 (variants 3, 4, 5 and 50
  share map 1; 30, 55, 134 and 208 are reserved-but-deferred). Everything else up to 255 is still
  free — but treat those numbers as *upstream's to allocate*, and take a renumbered port from
  254 downwards as described above.

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

| Pure | 11 | 11 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Danny Loeb, version 1.7.4) | playable | Issue 009 wave 3. "Pure" — the smallest board in the tree: **7 territories, 7 supply centres**, one per power, all land, no sea and no neutrals. Solo target 4. Named in the original draft as a Classic variant and excluded from wave 1 because it is not the Classic board; it is its own 49-line installer. gameID 35 — two Diplomacy phases; **a Builds phase is unreachable in year one by construction**, as with ClassicNoNeutrals, because every centre is someone's home centre. Legacy board only. |
| SouthAmerica5 | 6 | 6 | 5 | Sleepcap/vDiplomacy @ `72c81f0` (Joe Janbu, version 5.1, code 1.6.3) | playable | Issue 009 wave 3. "South America (5 players)" — 56 territories / 24 SCs, solo target 13. gameID 43. Legacy board only. |
| SouthAmerica4 | 7 | 7 | 4 | Sleepcap/vDiplomacy @ `72c81f0` (Joe Janbu, version 1.6.3) | playable | Issue 009 wave 3. "South America (4 players)" — the smaller cut of the same map: 48 territories / 24 SCs, solo target 13. **The tree's first four-player variant.** gameID 41. Legacy board only. |
| Hundred | 8 | 8 | 3 | Sleepcap/vDiplomacy @ `72c81f0` (Andy Schwarz, version 1, code 1.8) | playable | Issue 009 wave 3. "Hundred" — the Hundred Years' War, three powers, 45 territories / 17 SCs, solo target 9. gameID 40. Legacy board only. |
| SailHo2 | 16 | 16 | 4 | Sleepcap/vDiplomacy @ `72c81f0` (Michael "Tarzan" Golbe, version 1.3) | playable | Issue 009 wave 3. "Sail Ho II" — a four-power mythological-Greece map, 64 territories / 16 SCs, solo target 9. Named in the draft as a Classic variant and excluded from wave 1 because it is its own board. gameID 47. Legacy board only. |
| Alacavre | 31 | 31 | 7 | Sleepcap/vDiplomacy @ `72c81f0` (Figlesquidge, assisted by Ghostmaker; code 1.0.1) | playable | Issue 009 wave 3. "Alacavre" — an invented seven-power world, **81 territories / 34 SCs like Classic but an entirely different map** (Oz, Quiom, Payashk…), solo target 18. Its `install.php` uses an eight-column row format whose last column is the owning country's *name*, not an ID. gameID 50. Legacy board only. |
| WhoControlsAmerica | 43 | 43 | 8 | Sleepcap/vDiplomacy @ `72c81f0` (Gavin Atkinson, version 1.0.2) | playable | Issue 009 wave 3. "Who controls America" — eight powers on North America, 50 territories / 26 SCs, solo target 14. gameID 42. Legacy board only. |
| TreatyOfVerdun | 58 | 58 | 3 | Sleepcap/vDiplomacy @ `72c81f0` (Milan Mach, version 1.0, code 1.0) | playable | Issue 009 wave 3. "843: Treaty of Verdun" — the three Carolingian kingdoms, 38 territories / 15 SCs, solo target 8. gameID 37, which also exercised **declining a build**: country 1 had a build due with every home centre occupied and submitted `Wait`. Legacy board only. |
| War2020 | 61 | 61 | 10 | Sleepcap/vDiplomacy @ `72c81f0` (Jason B., version 1, code 1.1) | playable | Issue 009 wave 3. "War in 2020" — **ten powers on 43 territories / 17 SCs**, the densest map here, solo target 9. A **custom-start variant**: the game opens at turn 0 in a Builds phase with no units, like CustomStart and Classic1897. Uses all ten accounts. gameID 39. Legacy board only. |
| NorthSeaWars | 73 | 73 | 4 | Sleepcap/vDiplomacy @ `72c81f0` (sqrg, version 1, code 1.0.1) | playable | Issue 009 wave 3. "NorthSea Wars" — Britons, Romans, Frisians and Norse, 34 territories / 15 SCs, solo target 8; three of the supply centres are trade goods (`wood`, `iron`, `grains`). gameID 36. Legacy board only. |
| AnarchyInTheUK | 79 | 79 | 6 | Sleepcap/vDiplomacy @ `72c81f0` (amisond and Evansevern, code 1.1.2) | playable | Issue 009 wave 3. "Anarchy in the UK" — six powers on the British Isles, 78 territories / 34 SCs, solo target 18. gameID 51. Legacy board only. |
| Chromatic | 93 | 93 | 5 | Sleepcap/vDiplomacy @ `72c81f0` (Jimmy Millington, Robs Schone and Lynsey Smith; version 1, code 1.1) | playable | Issue 009 wave 3. "Chromatic" — an abstract five-power map of gemstone territories, 56 territories / 21 SCs, solo target 11. gameID 44, played to **Winter 1903** because its neutrals are far from the starting positions and year one produced no ownership change; the builds phase there placed `A Sapphire`, `A Royal` and `A Topaz` and destroyed `A Alabaster`. Legacy board only. |
| Caucasia | 118 | 118 | 5 | Sleepcap/vDiplomacy @ `72c81f0` (Christian Dreyer, version 1, code 1.0) | playable | Issue 009 wave 3. "Caucasia" — 37 territories / 23 SCs, solo target 12. The only wave-3 package with **four PHP files and nothing under `resources/` but images and CSS**. gameID 38. Legacy board only. |
| Chesspolitik | 132 | 132 | 4 | Sleepcap/vDiplomacy @ `72c81f0` (Alex Ronke, version 1.0, code 0.9) | playable | Issue 009 wave 3. "Chesspolitik" — a chessboard: 64 territories, **32 of them supply centres**, 756 borders, four powers, solo target 17. gameID 49. Its `classes/OrderArchiv.php` extends the absent `OrderArchiv` base class — the same inert dangling reference the in-tree Zeus5 and Duo carry, left alone for the same reason. Legacy board only. |
| SouthSahara | 149 | 149 | 5 | Sleepcap/vDiplomacy @ `72c81f0` (David E. Cohen, version 1.0, code 1.0) | playable | Issue 009 wave 3. "South of Sahara" — 60 territories / 25 SCs, solo target 13. **The only wave-3 variant with a package dependency**: its variant class and two of its classes extend `RuleExtensionsVariant*`, so `variants/RuleExtensions/` had to be placed as well. gameID 48. Legacy board only. |
| BalkanWarsVI | **254** | **254** | 6 | Sleepcap/vDiplomacy @ `72c81f0` (Brad Wilson, after Fred Davis and others; version 6, code 1.1) | playable | Issue 009 wave 3. "Balkan Wars VI" — 52 territories / 26 SCs, solo target 14. **Renumbered**: its upstream `$id`/`$mapID` are both **46**, which GoT2 holds. Renumbered to **254, not into the 900 block** — see the note below; `$id` and `$mapID` were edited in its own `variant.php` and nowhere else. gameID 46. Legacy board only. |
| RuleExtensions | — | — | — | Sleepcap/vDiplomacy @ `72c81f0` | present (dependency) | Issue 009 wave 3. **Not a playable variant and never registered in `Config::$variants`**: it is vDiplomacy's shared rule-extension base package (custom maps, custom icons, build-anywhere, transform orders), with a `variant.php` but **no `install.php` and no `$id`**. `SouthSahara` extends `RuleExtensionsVariant`, so the folder has to be on disk for the autoloader to find it. Nothing else in the tree references it. |

**Config line** (`config.php` is gitignored, so this is the only versioned record):

```php
public static $variants=array(1=>'Classic',2=>'World',3=>'FleetRome',4=>'CustomStart',5=>'BuildAnywhere',6=>'SouthAmerica5',7=>'SouthAmerica4',8=>'Hundred',9=>'AncMed',11=>'Pure',12=>'Colonial',14=>'ClassicCrowded',15=>'ClassicFvA',16=>'SailHo2',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',22=>'Duo',23=>'ClassicGvI',25=>'ClassicGvR',26=>'ClassicFGvsRT',28=>'Classic1897',31=>'Alacavre',38=>'ClassicNoNeutrals',40=>'ClassicOctopus',42=>'ClassicVS',43=>'WhoControlsAmerica',45=>'GoT',46=>'GoT2',48=>'ClassicFGA',49=>'ClassicIER',50=>'ClassicGreyPress',54=>'ClassicChaoctopi',58=>'TreatyOfVerdun',61=>'War2020',62=>'ClassicEvT',70=>'Zeus5',73=>'NorthSeaWars',79=>'AnarchyInTheUK',90=>'ClassicAnkaraCrescent',91=>'ColdWar',93=>'Chromatic',118=>'Caucasia',122=>'ClassicBritain',123=>'ClassicBrazilian',132=>'Chesspolitik',133=>'Classic1898',149=>'SouthSahara',254=>'BalkanWarsVI');
```

**49 variants**, every key unique:

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

| TenSixtySix | Sleepcap/vDiplomacy @ `72c81f0`, `$id`/`$mapID` **55** | 2026-09-20, issue 009 wave 3 | **The fog-of-war family again.** `classes/OrderInterface.php:22` uses `STATICSRV`, `classes/Maps.php` extends the absent `Maps` and `classes/OrderArchiv.php` extends the absent `OrderArchiv` — the identical trio that deferred ClassicFog. | "1066", three powers, 69 territories / 18 SCs. It was placed and its map installed before the security read finished, then **backed out completely**: removed from `Config::$variants`, its `wD_Territories`/`wD_Borders`/`wD_CoastalBorders` rows for map 55 deleted, its `wD_VariantInfo` row deleted and **the folder removed** — it ships `resources/{fogmap,fogmap_old,jsonBoardData}.php`, front controllers of the same kind that got `variants/ClassicFog/` deleted in wave 1. **ID 55 stays reserved.** |
| PunicWars | Sleepcap/vDiplomacy @ `72c81f0`, `$id`/`$mapID` **208** | 2026-09-20, issue 009 wave 3 | Same fog trio: `STATICSRV`, `extends Maps`, `extends OrderArchiv`, plus `resources/{orders,fogmap,jsonBoardData}.php`. **Not installed at all** — the security read runs before the folder is placed now, so nothing was copied and nothing has to be backed out. | Four powers, 60 territories / 17 SCs. Revisit with ClassicFog, Classic1898Fog and TenSixtySix; one fix unblocks all four. **ID 208 stays reserved.** |

All four deferrals are the same root cause: **vDiplomacy's fog-of-war subsystem depends on core
classes and constants that webDiplomacy does not have** — `STATICSRV`, `Maps` and `OrderArchiv`.
Grepping a candidate for `STATICSRV` before anything else is the cheapest possible triage, and is
now the first thing the wave-3 procedure does. Everything else attempted in issues 008 and 009
came in under the twenty-minute budget.
