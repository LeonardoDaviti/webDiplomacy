---
id: 004
title: Read the bot pack's requirements and decide whether bots are feasible here
label: needs-human
phase: P1
depends-on: []
---

# 004 — Bot feasibility report

**Research and report only. Install nothing.** This issue exists because the hardware the bot
pack needs is genuinely unknown, and issue 005 must not start on a guess.

An agent may do the whole investigation and write the report. The **decision** at the end is the
human's, which is why this is `needs-human`.

## Context

The bots are **not in this stack's compose file**. The compose file's trailing comment points at
a separate checkout, `webdiplomacy_bots_mila`, which builds its own images, joins this stack's
Docker network, and calls the site's API using bot accounts and fixed API keys created by the
bot-account SQL the installer already runs.

Two facts that older documentation gets wrong and this report must not repeat:

- The per-game restriction option **was removed in 1.82**. The supported control is the **bot
  variant ID list** in the site config, which by default covers Classic and the two Classic
  two-player derivatives.
- **Game-ID multiplexing is real**: the API multiplies game IDs and adds a per-key offset so one
  bot process can hold several seats. It is a designed feature, not a misreading.

Gunboat is a game press type of "no press".

## Steps

1. Obtain the `webdiplomacy_bots_mila` checkout (do not place it inside this repository).
2. Read its README and its compose files end to end.
3. Answer, with a citation to where in that repo each answer came from:
   - **Model weights**: what must be downloaded, how large, from where, and is that source still
     alive? (The original bot image's base was taken down once already.)
   - **Hardware**: RAM, disk, CPU. **Is a GPU required or merely faster?** If required, which
     generation and how much VRAM?
   - **Runtime per move**: roughly how long does one bot take to produce orders? This determines
     whether a seven-player game is playable or glacial.
   - **Setup steps**: what actually has to be run, in order, to get bots into a game.
   - **How bots find games**: what makes a bot join one game and not another, given that the
     per-game restriction was removed.
   - **How to stop the bots** without stopping the site.
4. Compare the requirements against the actual host machine's specs.
5. Write the findings into this issue under *Report*.
6. **Human decision:** feasible, feasible-with-caveats, or not feasible. Record the decision and
   the reasoning here, then flip issue 005 from `blocked` to `ready-for-agent` or to `deferred`.

## Done when

- [ ] Every question above is answered with a citation into the bot repo.
- [ ] The host machine's specs are recorded alongside the requirements.
- [ ] The report is written into this file.
- [ ] A human has recorded the feasibility decision.
- [ ] Issue 005's label has been updated to match the decision.

## Verification

Not a runtime change — nothing to execute. Verification is that the *Report* section below is
filled in and that issue 005's front matter no longer says `blocked`.

```
sed -n '1,10p' local-setup/issues/005-bots-run.md
```

## Report

_To be filled in._

## Decision

_To be filled in by a human._
