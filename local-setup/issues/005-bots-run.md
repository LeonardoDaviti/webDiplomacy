---
id: 005
title: Run a Classic gunboat game with one human and five bots
label: blocked
phase: P1
depends-on: [004]
---

# 005 — Bots in a real game

**Blocked on issue 004.** Do not start until 004's feasibility decision is recorded and this
file's label has been changed. If you are reading this and 004 is unfinished, go do 004.

## Goal

One human plus five bots complete **five game-years** of a Classic gunboat game without manual
intervention.

## Steps (after 004 unblocks this)

1. Bring up the bot stack from its own checkout, following the procedure 004's report
   established. It joins this stack's Docker network and talks to the site's API.
2. Confirm the bot accounts and their API keys exist — the installer's bot-account SQL creates
   seven of them.
3. Confirm the site config's **bot variant ID list** permits Classic and nothing this project
   does not want bots playing. Bots are restricted to maps they were trained on; do not widen
   this list.
4. Create a Classic game with press type "no press" (gunboat) and a short phase timer.
5. Let the bots take five of the seven seats; a human takes one. Decide up front what happens to
   the seventh — a second bot key, or a smaller game.
6. Play five game-years.

## Done when

- [ ] Bots join the intended game and no other.
- [ ] Every bot submits orders in every movement phase for five game-years.
- [ ] Build and retreat phases resolve with bots present.
- [ ] No phase required a human to intervene or force-process.
- [ ] The bots can be stopped without stopping the site, and the site is unaffected when they
      are down.
- [ ] Per-move latency is recorded and judged acceptable.

## Verification

```
docker compose logs --tail=100 sse          # phases processing
curl -s http://127.0.0.1:43000/status.php | grep -i -A2 gamemaster
```

Then open the game in a browser and confirm the order log shows bot orders in every movement
phase across five game-years, and that supply-centre counts moved.

To prove independence, stop the bot stack and confirm the site still serves pages and still
processes a human-only game.

## Notes / gotchas

- Bots play **Classic gunboat only**. Do not try them on ported variants; the models are trained
  on Classic geometry and the config restricts them for good reason.
- If bots join games you did not intend, the lever is the variant ID list, not a per-game
  setting — the per-game option was removed in 1.82.
