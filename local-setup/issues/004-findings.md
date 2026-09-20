---
id: 004-findings
title: Bot feasibility research — findings
label: needs-human
phase: P1
depends-on: [004]
---

# 004 — Findings

Research output for `004-bots-research.md`. That file is left untouched; this is the report it
asks for, plus the decision a human has to make before 005 can start.

Everything below is either a citation into this repository, a live HTTP probe made on
2026-09-20, or a measurement of this host. Nothing was installed, cloned or run.

---

## 1. The bot checkout: **not obtainable**

`docker-compose.yml:241-256` points at a sibling checkout `webdiplomacy_bots_mila` and says to
run `docker compose -f compose.yml -f compose.dev.yml up -d` from its `docker/` directory. That
checkout is **not on this machine** (`find / -maxdepth 5 -iname '*bots_mila*'` → nothing) and
**is not published**:

| Probe | Result |
|---|---|
| `github.com/kestasjk/webdiplomacy_bots_mila` | 404 |
| `api.github.com/users/kestasjk/repos` (16 repos) | no `webdiplomacy_bots_mila` |
| GitHub search `webdiplomacy_bots_mila`, `gunboatbots` | 0 results |
| GitHub user/org `webdiplomacy` | does not exist |
| **`github.com/kestasjk/diplomacy_mila`** | **exists — created 2026-09-20 03:47 UTC, 14 KB, contains only `LICENSE`** |

`kestasjk/diplomacy_mila` ("A repo for the MILA gunboat no-press bots (created by Phillip
Paquette)") is plainly the intended public home of that checkout, created hours ago and **not yet
pushed with content**. There is no README, no `docker/`, no code.

The old prebuilt image the repo README still advertises is also gone:

- `public.ecr.aws/n4k3z7o3/webdiplomacy` (`README.md:41-42`) → ECR `NAME_UNKNOWN`, manifest 404.
- `webdiplomacy/gunboatbots` on Docker Hub → object not found.
- Its base `pcpaquette/tensorflow-serving:20190226` **is still alive** on Docker Hub (~1.04 GB).

So the answer to issue 004 step 1 is: **the checkout cannot be obtained today**, by any route.
Every question step 3 asks about "that repo" is answered below from upstream
(`github.com/diplomacy/research`) and from this repository's own audit of that checkout,
`doc/gamedata/audit/gunboat-bots.md`, which was written against it at HEAD `9473c017` and is
field-level exact.

---

## 2. What the bots are, from this repo's own audit

`doc/gamedata/audit/gunboat-bots.md` (408 lines, audited 2026-09-19) documents the checkout
completely. Summary of what matters here:

- **Model**: Philip Paquette's DipNet supervised-learning model, served by **TensorFlow Serving**
  over local gRPC (port 9503); the bot process is
  `data/diplomacy/diplomacy_research/scripts/launch_bot_webdip.py`. Model `standard` for variant 1,
  `standard_1v1` for variants 15 and 23 (audit §3, "Variants").
- **No search at inference.** One forward pass per power per phase. No messages, no votes, ever
  (audit §6) — which is what makes them gunboat bots.
- **Discovery**: the bot **never joins games**. It learns what it plays purely from list calls on
  each API key: the (gameID, countryID) pairs its own account is `Playing` with no saved orders
  (audit §5). It is seated in a game by the *site*, not by itself.
- **One key = one account = one country per game.** A seven-seat game needs seven user keys
  (`API_KEY_USER_01..07`). **The MILA bots do not multiplex** — they have no multiplex logic and
  never send `multiplexOffset`; multiplexing is transparent because they echo back whatever
  `gameID` the server gave them (audit §5). Multiplexing exists for the *FAIR/Cicero* bots, which
  hold GPU memory (`api.php:110-131`).
- **Cadence**: poll loop every 5 s per key; ~0.4-0.5 s per power end to end in the upstream dev log
  (audit §7). A seven-bot Classic phase is therefore served in roughly **3-5 seconds**, not
  minutes. A game is entirely playable.
- **Stop them** by stopping their compose project (`docker compose down` in *their* directory).
  They are a separate project; the site does not depend on them and keeps running. A game whose
  bots are stopped simply sits until its 3-day phase timer expires.

## 3. The blocker this research actually found: **the API the bots call no longer exists**

This is the decisive finding and it is newer than every other document in the tree.

The MILA bots call exactly four routes (audit §1): `players/cd`, `players/missing_orders`,
`game/status`, `game/orders`. On **2026-09-20** — commit `02ae5780` "Remove the API routes the game
files and game/playercontext replaced" — three of those four were **deleted from `api.php`**:

`api/README.md`, "ROUTES REMOVED ON 2026-09-20":

| Removed | Replacement |
|---|---|
| `game/status` | `game.json` + `history.json`, plus `game/playercontext` for the caller's own state |
| `players/missing_orders` | the list form of `game/playercontext` |
| `players/cd` | **nothing** — API takeover of a country in civil disorder "had not worked since 2022" |
| `players/active_games`, `players/pulse`, `game/pulse`, `game/overview`, `game/data`, `game/members`, `game/getmessages` | the files / `game/playercontext` |

Only `game/orders` survives. `doc/gamedata/02-spec.md:596` states the consequence plainly for the
other bot family ("**The live Cicero and Dora bots ... stop working when this is deployed**").

The upstream checkout was migrated in step with this (`02-spec.md:519`: the gunboat bots "played
two dev games through the new client ... without errors", one to a win in 1913). **That migrated
client — the rewritten `webdiplomacy_net/api.py` — exists only in the unpublished checkout.** The
public upstream, `github.com/diplomacy/research`, still speaks the old, deleted API.

So there are exactly three ways to get bots here, and none of them is "clone and run":

- **A — wait for `kestasjk/diplomacy_mila` to be populated.** Zero work, unknown date. The repo was
  created today, which suggests days not months, but that is a guess.
- **B — build from upstream `diplomacy/research` and rewrite the client.** The rewrite is small and
  fully specified: replace two list calls with one `game/playercontext` (list form), and replace
  `game/status` with `game.json` + `history.json` fetched by URL and version. `api/README.md` gives
  the new shapes; `doc/gamedata/audit/gunboat-bots.md` §2-§4 gives, field by field, exactly what
  the client reads and what types it demands. An agent can do this. It is maybe a day.
- **C — pin the site to pre-`02ae5780` code** so the old bot client works. **Reject this**: it
  means never merging upstream again, and `/game/` (the current board) is built on the new routes.

## 4. Weights, and whether the sources are still alive

| Artifact | URL | Size | Probed 2026-09-20 |
|---|---|---|---|
| Serving model used by `WebDiplomacyPlayer` (hardcoded) | `f002.backblazeb2.com/file/ppaquette-public/benchmarks/neurips2019-sl_model.zip` | — | **404 — DEAD** (whole path is gone) |
| Same model, current mirror used by `DipNetSLPlayer` in upstream master | `s3-public.billovia.com/diplomacy/benchmarks/neurips2019-sl_model.zip` | **145 MB** (152,130,497 B) | **200 OK**, last-modified 2025-11-16 |
| RL variant | `.../neurips2019-rl_model.zip` | ~147 MB | 200 OK |
| Miniconda + `convoy_paths_cache.pkl` used by the old `deploy-webdip` Dockerfile | `storage.googleapis.com/ppaquette-diplomacy/...` | — | **403 — DEAD**, must be substituted |
| Training archives (not needed for play) | `s3-public.billovia.com/diplomacy/...` | order-based-lstm 5.4 GB, rl-model 11.1 GB, dataset 2.2 GB | 200 OK |

**To play, one 145 MB download is needed and it is alive** — behind a one-line URL swap from the
dead backblaze host to billovia. The two dead Google Storage URLs in the old Dockerfile are the
"base image taken down" problem the issue refers to, and route B has to work around them.

**Licence, and this is a real constraint, not a footnote.** `diplomacy/research`'s README states
the trained weights are for research purposes only and "cannot be used to power any bots on any
website without my prior written consent", and the dataset provider forbids training
website-accessible bots on its data. webdiplomacy.net has that consent. **A private LAN instance
does not.** This is a question for the owner, and it is part of the decision below.

## 5. Hardware: required vs. what this machine has

**GPU is NOT required.** The webDiplomacy bot path *forces CPU*:
`diplomacy_research/players/benchmark_player.py` starts TF Serving with
`kwargs={'force_cpu': True, ...}`, and `force_cpu` sets `CUDA_VISIBLE_DEVICES=''`
(`diplomacy_research/utils/process.py`). The CUDA images in that repo are for **training and RL
self-play only**. (Contrast the FAIR/Cicero bots, which do need a GPU — `02-spec.md:520` — and
which are a different pack entirely.)

| Requirement | Source | This host |
|---|---|---|
| CPU, x86-64, any modern count | TF Serving CPU, `num_batch_threads = cpu_count()` | **32 cores** — ample |
| RAM: ~2-4 GB for TF Serving + model + Redis (estimated; upstream publishes no figure) | audit §7, upstream serving config | **30 GB total, ~17 GB available** — ample |
| GPU / VRAM | **not required** | AMD Strix Halo (Radeon 8060S) iGPU, **no CUDA** — irrelevant, and just as well |
| Disk: ~1.04 GB base image + 145 MB weights + build layers; call it **6-8 GB** | Docker Hub, probe above | docker root `/home/normie/docker-data` on `/home`: **279 GB free** — ample |
| Image build time | Ubuntu 18.04 + TF 1.13.1 + pip deps, no GPU stack | **20-45 min**, one time, network-bound |
| Runtime per move | ~0.4-0.5 s per power; ~3-5 s for a full 7-bot phase | fine |

**Software stack it pins** (upstream `requirements.txt`): `tensorflow==1.13.1`,
`tensorflow-probability==0.6.0-rc1`, `numpy>=1.15,<1.16`, `protobuf==3.6.1`, `grpcio==1.15.0`,
`diplomacy==1.1.0`, Python 3.5-3.7, plus Redis. This is 2019-era and is exactly why the image is
Ubuntu 18.04 — it will not build on a modern base. Everything stays inside the container.

**Verdict on hardware: comfortably sufficient. Hardware was never the risk.**

## 6. Cross-check against this repository

**Accounts and keys** — `install/createBotAccounts.sql`, run automatically by
`install/gamemaster-entrypoint.sh:53-54` on a fresh install:

- Creates `bot1`..`bot7`, `type='Bot'`, password literal `12345678`.
- `INSERT INTO wD_ApiKeys(userID, apiKey) SELECT id, username FROM wD_Users WHERE username LIKE '%bot%'`
  — so **the API key is literally the username**: `bot1`, `bot2`, ... `bot7`. That matches the
  `--env API_KEY_USER_01=bot1 ...` in `README.md:42` and the compose comment.
- Note the `LIKE '%bot%'` is a substring match: any future user with "bot" in their name that this
  script re-runs over would be handed an API key equal to their username. Worth knowing.
- Grants `getStateOfAllGames`, `submitOrdersForUserInCD`, `listGamesWithPlayersInCD` = Yes.
  **Today only `submitOrdersForUserInCD` matters**: it is the permission on `game/orders`
  (`api.php:869-872`). `game/playercontext` is constructed with an **empty** permission field
  (`api.php:747-749`), so any valid key can read. The other two permissions gated routes that no
  longer exist.
- The multiplex SQL at the bottom of the file is **commented out**, and the `UPDATE wD_ApiKeys SET
  apiKey='botN' WHERE userID=...` lines in the compose comment are for grafting these keys onto a
  restored production backup. Neither is needed on a fresh install. **Leave multiplexing off** —
  the MILA bots don't use it (§2).

**Which games the bots play**: `api/responses/player_context.php:394-397` —

```php
if( !$apiEntry->isSessionAuth )
    $filterVariantClause = "AND g.variantID IN (".implode(', ', ...libVariant::botVariantIDs()).")";
```

An API-key caller is listed **only** games in `Config::$botVariantIDs`, which is
`array(1, 15, 23)` in both `config.sample.php:268` and the live `config.php:268` — Classic,
ClassicFvA, ClassicGvI. The list is also `Playing` status and phase in Diplomacy/Retreats/Builds.
Since 1.82 there is **no per-game restriction** (`02-spec.md:603-616`: `Config::$apiConfig` and its
`restrictToGameIDs`/`noPressOnly`/`enabled` keys are gone); and separately `game/join` now refuses
a non-bot variant for API-key callers, so the variant list is enforced at the join, not just by
what is shown. **This variant list is the only lever** for confining bots.

**How a game gets bots — `botgamecreate.php`.** A human visits `/botgamecreate.php`, picks a
variant (only `botVariantIDs` are offered, `botgamecreate.php:417`) and a country (or Random), and
submits. The handler at `botgamecreate.php:258-360` then:

1. validates the variant is in `libVariant::botVariantIDs()` (line 281);
2. creates the game with **fixed settings** (line 315):
   `processGame::create($variantID, $name, '', 5, 'Unranked', $phaseMinutes, -1, $phaseMinutes, -1, 60, 'No', 'Regular', 'Normal', 'draw-votes-public', 0, 4, 'MemberVsBots')`
   — unranked, 5-point bet, **3-day phases** (24 h in play-now mode), 4 excused missed turns,
   `playerTypes = 'MemberVsBots'`;
3. seats the human (`processMember::create($User->id, 5, $countryID)`);
4. **seats the bots itself** (lines 326-353): `SELECT id FROM wD_Users WHERE type LIKE '%bot%'`,
   filtered — `username='FairBot'` for variant 15, `username LIKE 'dipgpt%'` for full-press, and
   otherwise **`NOT username LIKE 'dipgpt%'`**, which is what picks up `bot1`..`bot7` — `LIMIT
   countryCount-1`, one per remaining country;
5. sets `processTime = now()` so the game starts immediately.

The bots then discover their seats on their next 5-second poll. **There is no game ID to configure
anywhere on the bot side.**

Three details that will bite in 005:

- **`botgamecreate.php` creates a `Regular` (full-press) game, not `NoPress`.** The result is still
  effectively gunboat because the MILA bots never read or send a message (audit §6) — but the game
  is labelled as having press and the human *can* type into a void. If a true no-press game is
  wanted, either patch that `'Regular'` argument or create the game another way.
- **Variant 15 (ClassicFvA) will get zero bots** on a fresh install: line 333 demands
  `username='FairBot'`, and `createBotAccounts.sql` creates no such account. Variant 1 and variant
  23 work. FairBot and the `dipgpt*` full-press bots are separate, also-unpublished packs.
- The full-press path is queue-gated (`BotGameQueue`, 20 games) and needs `dipgpt*` accounts that
  do not exist here. **Ignore full press entirely.**

**Gunboat** is a game press type of `NoPress` (`objects/game.php:286, 494`), as the issue says.

---

## 7. Answers to the six questions in step 3

| Question | Answer |
|---|---|
| Model weights: what, how big, from where, still alive? | One TF Serving SavedModel, `neurips2019-sl_model.zip`, **145 MB**. The URL hardcoded in the bot class is **dead**; the upstream-master mirror `s3-public.billovia.com/diplomacy/benchmarks/neurips2019-sl_model.zip` is **alive** (200, 152,130,497 B). Two Google Storage URLs in the old Dockerfile are dead (403) and must be substituted. |
| Hardware: RAM, disk, CPU; GPU required? | ~2-4 GB RAM, ~6-8 GB disk, any modern CPU. **GPU is NOT required** — the webDip path sets `force_cpu=True` / `CUDA_VISIBLE_DEVICES=''`. CUDA images are for training only. |
| Runtime per move | ~0.4-0.5 s per power; ~3-5 s for a whole seven-bot phase. **Playable, not glacial.** |
| Setup steps | See §8. They are *not* "clone and run" — see §3. |
| How bots find games | They never join. `botgamecreate.php` seats them; they poll `game/playercontext` (list form) per key and take any `Playing` seat, in a `botVariantIDs` variant, with no orders saved. Per-game restriction is gone; **the variant list is the only control**. |
| How to stop them without stopping the site | `docker compose down` in the bots' own project. Separate compose project, separate images; the site is unaffected. Games then idle until their 3-day phase timer fires. |

---

## 8. Step list for issue 005 (route B — build it ourselves)

If the decision is to wait for `kestasjk/diplomacy_mila`, 005 becomes "poll that repo" and
everything below collapses to steps 6-10. This is the list for actually doing it now.

1. `git clone https://github.com/diplomacy/research /home/normie/Documents/Projects/webdiplomacy_bots_mila`
   (outside this repo, per the issue). Keep `kestasjk/diplomacy_mila` on a watch — if it is
   populated before this is finished, **throw this away and use it**.
2. Write a Dockerfile on `pcpaquette/tensorflow-serving:20190226` (alive) — do **not** use the old
   `deploy-webdip` one unchanged, its Miniconda and `convoy_paths_cache.pkl` URLs are 403. Install
   Python 3.7 + `requirements.txt` (TF 1.13.1, numpy<1.16, protobuf 3.6.1, grpcio 1.15.0,
   diplomacy 1.1.0) and Redis. Expect a 20-45 minute first build.
3. Fetch the weights from `s3-public.billovia.com/diplomacy/benchmarks/neurips2019-sl_model.zip`
   into the image or a named volume; patch the hardcoded backblaze URL in `WebDiplomacyPlayer`.
4. **Rewrite `diplomacy/integration/webdiplomacy_net/api.py` for the current API.** This is the
   real work. Specification: `api/README.md` ("HOW A CLIENT READS A GAME" and the route sections)
   for the new shapes; `doc/gamedata/audit/gunboat-bots.md` §2-§4 for every field the client reads
   and the exact types it demands. Concretely:
   - `players/missing_orders` + `players/cd` → one `GET api.php?route=game/playercontext` per key.
     A game needs orders when its `orderStatus` contains no `Ready`/`Completed` and
     `processStatus` is `Not-processing`. Drop the CD path entirely — it has been dead since 2022.
   - `game/status` → fetch `files.game.url` and `files.history.url` as
     `<site>/<url>?v=<version>`, cache by version, and assemble the `phases[]`/`standoffs`/
     `occupiedFrom` structure `game.py` expects. Only the last 16 phases are used.
   - `game/orders` is **unchanged** — leave it alone.
   - Watch the type strictness in audit §5/§8: `phases[].turn` and list-row `countryID` must be
     JSON *numbers*; a string is an uncaught crash that restarts the whole bot every 5 s.
5. Write `docker/compose.yml` + `compose.dev.yml`: service running `launch_bot_webdip.py`, env
   `API_WEBDIPLOMACY=http://webserver/api.php` and `API_KEY_USER_01=bot1` .. `API_KEY_USER_07=bot7`,
   **no CD keys**, joined to the site's network as `external: true`. **Check the network name
   first** — `docker network ls` on this host currently shows **no** `webdiplomacy_default`,
   because this stack is not up; bring it up and confirm the real name before writing it in.
   Note the keys are printed to the log in plain text at startup (audit §5) — harmless on a LAN,
   but don't publish the logs.
6. Confirm `bot1`..`bot7` exist with matching keys:
   `SELECT u.username, k.apiKey, p.submitOrdersForUserInCD FROM wD_Users u JOIN wD_ApiKeys k ON k.userID=u.id LEFT JOIN wD_ApiPermissions p ON p.userID=u.id WHERE u.type LIKE '%bot%';`
   If the DB came from a backup rather than a fresh install, apply the `UPDATE wD_ApiKeys` lines in
   the `docker-compose.yml` comment. **Do not** run the commented-out multiplex `UPDATE`.
7. Confirm `Config::$botVariantIDs` in `config.php` is `array(1, 15, 23)` — it is — and narrow it
   to `array(1)` if only Classic is wanted.
8. Bring the bots up: `docker compose -f compose.yml -f compose.dev.yml up -d`.
9. Create a game at `/botgamecreate.php`: Classic, pick a country. The game starts immediately with
   six bots seated. Expect orders within ~10 s of each phase opening. If a true no-press game is
   required, change `'Regular'` at `botgamecreate.php:315` first (§6).
10. Verify: the game advances; `status.php`'s API-key table shows `lastHit` moving for bot1..bot7;
    `docker compose logs` shows no "Invalid turn, expected" and no 5-second restart loop.
    **Stop with `docker compose down` in the bots directory; the site is untouched.**

---

## 9. Verdict

**Hardware: feasible, with room to spare.** 32 cores, 30 GB RAM, 279 GB free on the Docker
volume, against a requirement of ~4 GB RAM, ~8 GB disk and **no GPU**. The absence of an NVIDIA
card, which looked like the likely killer going in, turns out not to matter at all for the MILA
gunboat pack — it forces CPU by design. Per-move cost is sub-second. A seven-player game would be
perfectly playable.

**Software: not feasible today, and not for a hardware reason.** The bot checkout the compose file
points at does not exist publicly, and the public upstream it derives from speaks an API that this
repository **deleted on 2026-09-20**. There is nothing to clone and run.

Overall: **feasible-with-caveats, currently blocked on an artifact that does not exist yet.**

## 10. Decision required from the human

Three questions, none of which an agent should answer:

1. **Wait or build?** Route A (wait for `kestasjk/diplomacy_mila` to be populated — created today,
   so plausibly soon, but no commitment) versus route B (build from upstream and rewrite the API
   client ourselves, roughly a day of agent work, and thrown away if A lands). Route C (pinning the
   site to pre-`02ae5780` code) should be rejected outright.
2. **The weights licence.** Paquette's terms forbid using these weights to power bots on any
   website without his written consent. Does a private LAN instance count, and is the owner
   comfortable either way? If not, bots are off the table regardless of routes A and B.
3. **Full press.** `dipgpt*` and `FairBot` are separate, also-unpublished packs. Confirm that
   gunboat-only is acceptable and that variant 15 (ClassicFvA) getting no bots is acceptable.

Record the answer in `004-bots-research.md` under *Decision*, then set issue 005 to
`ready-for-agent` (route B), or `deferred` (route A or "no").
