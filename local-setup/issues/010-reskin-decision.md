---
id: 010
title: Decide how custom map art will be delivered
label: needs-human
phase: P5
depends-on: [001]
---

# 010 — The reskin decision

**A decision, not an implementation.** Custom artwork is one of the four reasons this project
exists, and there are two ways to deliver it that differ by an order of magnitude in effort.
Picking wrong wastes more time than anything else in this plan, so nothing is built until the
owner chooses.

## The constraint that forces the choice

The modern React board **hard-codes Classic map geometry** in its own TypeScript data file, and
the game object's "is this a classic game?" check whitelists exactly three variants: Classic,
Classic France vs Austria, and Classic Germany vs Italy. Everything else renders on the
**legacy board**.

The board therefore draws **vector geometry from its own data file** and never loads the
variant's map image. The consequence is blunt:

> **A PNG-only reskin of Classic is invisible on the React board.** Players on the default modern
> board would see the stock map. The art would only appear to someone playing on the legacy
> board.

## Option A — legacy-board PNG reskin

Make a variant that reuses Classic's rules and geography with new map images, and play it on the
legacy board.

- **Cost:** low. This is exactly how every community variant in the tree already works.
- **Risk:** low. Well-trodden path, no core code touched, survives upstream merges.
- **Result:** the art appears wherever the legacy board is used, and nowhere else. Anyone
  starting an ordinary Classic game on the modern board sees the stock map.

## Option B — fork the React board's Classic map data

Add the custom geometry and art to the React board's own map data so the modern board renders
it.

- **Cost:** high. This is **core front-end work**, not a content job: forking hard-coded
  geometry in the React source, plus whatever the whitelist check needs.
- **Risk:** high and *recurring*. Every upstream change to the board re-opens the merge. This is
  a permanent maintenance commitment, not a one-off.
- **Result:** the art appears on the default board that everyone actually uses.

## The question for the owner

Is seeing your own artwork **on the default board** worth taking on permanent maintenance of a
forked React map-data file — or is it enough for the art to exist on a variant you choose to
play on the legacy board?

A third answer is legitimate: **drop it**. Story 3 is a want, not a requirement, and dropping it
costs nothing already built.

## Done when

- [ ] The owner has chosen A, B, or drop.
- [ ] The choice and its reasoning are recorded in the *Decision* section below.
- [ ] `SPEC.md` → Implementation Decisions is updated to state the chosen route as settled, and
      the Open Questions row for this is marked answered.
- [ ] If B: `SPEC.md` → Out of Scope is amended, since it currently excludes React-board work
      except by this decision.
- [ ] A follow-up implementation issue is opened, or the item is explicitly dropped in writing.

## Verification

No runtime change. Verified by inspection:

```
grep -n 'reskin\|Option A\|Option B' local-setup/SPEC.md
ls local-setup/issues/
```

`SPEC.md` must no longer describe this as deferred, and either a new issue file exists or this
file records the drop.

## Decision

_To be filled in by the owner._
