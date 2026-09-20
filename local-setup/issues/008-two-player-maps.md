---
id: 008
title: Confirm and complete the two-player map roster
label: ready-for-agent
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

- [ ] Each of ClassicFvA, ClassicGvI and ColdWar has passed all five acceptance items and is
      marked `playable` in the registry.
- [ ] Every map on the draft's two-player list is accounted for as already-present,
      harvested, or deferred-with-a-reason.
- [ ] Any already-present-but-disabled two-player map is enabled and passing.
- [ ] The registry reflects the final roster.
- [ ] At least four two-player maps are `playable`.

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
