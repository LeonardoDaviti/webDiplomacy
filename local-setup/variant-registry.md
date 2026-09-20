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
| World | 2 | 2 | 6 | upstream 1.83 (David Norman) | enabled | World Diplomacy IX. Legacy board only. |
| FleetRome | 3 | 1 | 7 | upstream 1.83 (Avalon Hill) | present | Classic with a fleet in Rome. Shares Classic's map. Wave 0. |
| CustomStart | 4 | 1 | 7 | upstream 1.83 (Avalon Hill) | present | Classic with a configurable start. Shares Classic's map. Wave 0. |
| BuildAnywhere | 5 | 1 | 7 | upstream 1.83 (Avalon Hill) | present | Classic, build in any owned SC. Shares Classic's map. Wave 0. |
| AncMed | 9 | 9 | 5 | upstream 1.83 (Don Hessong) | enabled | The Ancient Mediterranean. Legacy board only. |
| Colonial | 12 | 12 | 7 | upstream 1.83 (Peter Hawes) | present | Colonial Diplomacy. Wave 0. |
| ClassicFvA | 15 | 15 | 2 | upstream 1.83 | enabled | France vs Austria. **Two-player.** React-board whitelisted. Ships an interactive-map thumbnail. |
| ClassicChaos | 17 | 17 | 1 | upstream 1.83 | enabled | Chaos: one power per SC. Player count of 1 in the definition is the variant's own convention. Ships an interactive-map thumbnail. |
| Modern2 | 19 | 19 | 10 | upstream 1.83 (Vincent Mous) | enabled | Modern Diplomacy II. Legacy board only. |
| Empire4 | 20 | 20 | 10 | upstream 1.83 (Vincent Mous) | enabled | Fall of the American Empire IV. Legacy board only. |
| ClassicGvI | 23 | 23 | 2 | upstream 1.83 | enabled | Germany vs Italy. **Two-player.** React-board whitelisted. Ships an interactive-map thumbnail. Also in the default bot variant list. |
| Zeus5 | 70 | 70 | 7 | upstream 1.83 (Northcott / Davis / Reinecker) | present | Zeus 5. Ships an interactive-map thumbnail. Wave 0. |
| ColdWar | 91 | 91 | 2 | upstream 1.83 | enabled | **Two-player.** Legacy board only — not React-whitelisted despite being a headline two-player map. |

### What this table already settles

- **Wave 0 is five rows:** FleetRome (3), CustomStart (4), BuildAnywhere (5), Colonial (12),
  Zeus 5 (70). All are `present` — the code is in the tree and only the config array entry and a
  variant-info refresh are missing. No harvest, no porting, no risk.
- **Three two-player maps already exist:** ClassicFvA (15), ClassicGvI (23), ColdWar (91). Issue
  008 starts from these rather than from nothing.
- **The draft spec's harvest list was wrong.** Chaos, Build Anywhere, Cold War, France vs
  Austria and Germany vs Italy were all listed as things to fetch. They are all here.
- **Map IDs 1, 9, 12, 15, 17, 19, 20, 23, 70, 91 and variant IDs 1–5, 9, 12, 15, 17, 19, 20, 23,
  70, 91 are taken.** IDs 6, 7, 8, 10, 11, 13, 14, 16, 18, 21, 22, 24–69, 71–90 and 92–99 are
  free but should be treated as *upstream's to allocate*; prefer the 900 block for anything
  ported in here.

## Ported and harvested variants

Empty. Rows are added by issues 007, 008 and 009 as work lands.

| Variant | `$id` | `$mapID` | Players | Source | Status | Notes |
| --- | ---: | ---: | ---: | --- | --- | --- |
| _(Westeros)_ | _TBD, 900 block_ | _TBD_ | _TBD_ | mcoirad/gameofthrones-diplomacy | not started | Issue 007. Requires a dependency variant installed first. Legacy board only. Security-review the PHP before loading it. |

## Deferred

Variants that failed the twenty-minute triage budget. Each row records what broke, so that a
later attempt starts from the failure rather than repeating it.

| Variant | Source | Attempted | Failure | Notes |
| --- | --- | --- | --- | --- |
| _(none yet)_ | | | | |
