---
id: 001-smoke-test
title: End-to-end smoke test of a Classic game on the local instance
label: done
phase: P1
depends-on: [001, 002, 003]
---

# 001-smoke-test — A Classic game played end to end

Proof that a human-playable Classic game works on this instance: created, filled with seven
accounts, started, and played through **Spring 1901 → Autumn 1901 → Winter 1901 builds → Spring
1902**, with orders submitted through the same API the React board uses, the public JSON files
updating, the SSE stream delivering, and no errors anywhere.

Run 2026-09-20. Nothing in the repo was changed by this test — only site data.

## The game

| | |
|---|---|
| gameID | **2** |
| Name | `Smoke test` |
| Variant | Classic (variantID 1) |
| Phase length | 60 minutes (retreats/builds same) |
| Pot type | Sum-of-squares, minimum bet 5, pot 35 |
| Press | Regular (full press), **not** anonymous |
| Draw votes | public · missing-player policy Normal · excused missed turns 2 |
| State when left | Spring 1902 Diplomacy, deadline on the hour |

Open it at:

- Classic/legacy URL — **<http://localhost:43000/board.php?gameID=2>** (302-redirects to the React
  board, which is correct: `board.php` redirects when `Game::usePointAndClickUI()` is true)
- React board — **<http://localhost:43000/game/?gameID=2>** (returns 200)

Country assignment (`wD_Members`, countryID → account):

| countryID | Country | Account | userID |
|---|---|---|---|
| 1 | England | `admin` | 12 |
| 2 | France | `player2` | 13 |
| 3 | Italy | `player3` | 14 |
| 4 | Germany | `player4` | 15 |
| 5 | Austria | `player5` | 16 |
| 6 | Turkey | `player6` | 17 |
| 7 | Russia | `player7` | 18 |

Supply centres after Winter 1901: England 4, France 6, Italy 3, Germany 5, Austria 4, Turkey 4,
Russia 6 — 32 of 34 accounted for; Tunis and Serbia were left neutral (Austria passed through
Serbia in Spring and moved on to Greece in Autumn, so it owned neither at the count).

## What was verified, phase by phase

**Pre-game → Spring 1901.** Created by `admin` through `gamecreate.php` with a real form token;
joined by `player2`…`player7` through `board.php?gameID=N&join=Join` with a real form ticket. As
soon as the seventh member joined, the gamemaster (called once a second by the SSE container)
dealt countries and moved the game to `Diplomacy` turn 0 within about five seconds. 22 units
created, pot 35.

**Spring 1901.** Seven order sets submitted through `api.php?route=game/orders` with
`ready: "Yes"`. All seven returned 200 with the stored orders echoed back. The game processed
within ~5 seconds of the last Ready — i.e. the Ready/`processHint` path works, no waiting for the
deadline. Adjudication was correct on the interesting cases:

- `Support move` (A Marseilles S A Paris → Burgundy) — succeeded.
- `Support hold` (F Naples S A Rome) — succeeded.
- Galicia bounce: A Vienna → Galicia and A Warsaw → Galicia **both failed**.
- Black Sea bounce: F Ankara → Black Sea and F Sevastopol → Black Sea **both failed**.
- Every uncontested move succeeded; no spurious dislodgements.

**Autumn 1901.** Seven order sets, all Ready, processed immediately. Centres captured as
intended (Norway, Belgium, Portugal, Spain, Holland, Denmark, Greece, Bulgaria, Rumania, Sweden).

**Winter 1901 builds.** The game entered `Builds` on turn 1 and issued exactly the right number
of placeholder build orders per country: England 1, France 3, Germany 2, Austria 1, Turkey 1,
Russia 2, **Italy 0**. Italy's empty-orders + `ready: "Yes"` submission was accepted and did not
block the phase — i.e. "no builds" is handled correctly rather than being skipped or hanging.
All builds were placed (including `Build Fleet` on a child coast, St. Petersburg North Coast,
terrID 78) and the phase processed immediately.

**Spring 1902.** Reached with the expected unit counts (4/6/3/5/4/4/6), matching the builds.

**Public files.** All four exist under `cache/games/0/2/` and are served by nginx without PHP:

```
game.json      200  11394 bytes
status.json    200    438 bytes
history.json   200  21264 bytes
messages.json  200    118 bytes
variants/Classic/cache/variant.json  200
```

`history.json` holds all four phases in order — `Spring, 1901` (22 orders with
`success`/`dislodged`), `Autumn, 1901` (22 orders), `Autumn, 1901` Builds (10 orders), and
`Spring, 1902` (the phase in progress, 0 orders) — exactly as `doc/gamedata/02-spec.md` §2.4
specifies. `game.json` carries `turnText: "Spring, 1902"`, the members with usernames (the game is
not anonymous), the units and territory ownership. Every file's `version` changed on each process.

**Press.** A global message from England through `api.php?route=game/sendmessage` appeared in
`messages.json` within two seconds with the right turn and `toCountryID: 0`.

**SSE.** `game/playercontext` hands out an `sseAuth` token; opening
`/events?channelList=private-game2,private-game2-files,private-game2-country1&auth=<token>&have=&since=0`
returned 200, the `connected to channels:` banner, a `files` event with the current file versions,
and the backlog of the player's messages. `status.php` flipped **SSE Server Last Client Connect**
to green for the first time on this install.

**Listings UI.** After the cached-count refresh (see "Gotchas") the game shows up for a
non-member: the **Active** tab reads `Active (1)` and lists `Smoke test`.

## Errors found

**None during play.** `status.php` is green on everything except the one permanent warning issue
001 already recorded (`Game Backup Archived`, nothing ships backups offsite here).
`Error Logs: 0 entries`, `Games Crashed: 0`, `Maintenance Mode: Off`, `Game Processing: 1 second
since last process`.

`docker compose logs php-fpm` shows only `200`s — the gamemaster once a second plus the test's own
`api.php` calls. `docker compose logs sse` shows the normal minute-by-minute stats lines
(`59–60 gamemaster runs, 0 failed`) with two transient blips, both self-healed and neither during a
game process:

1. `Gamemaster call failed: fetch failed` — one-off, immediately followed by `Gamemaster call
   succeeded again`. This is the nginx-starts-before-sse race issue 001 documents.
2. One `getaddrinfo for webdiplomacy-db failed: Name or service not known` — a single Docker DNS
   blip resolving the MariaDB container name (`Config::$database_socket = 'webdiplomacy-db'`,
   which is the compose `container_name`). Recovered on the next second's call.

**No error-log files were written, and none can be:** `Config::errorlogDirectory()` in `config.php`
returns `false` before it returns `'../errorlogs'` (that is how the sample config ships — the
`return false;` line is above the real one, deliberately). The same is true of
`Config::orderlogDirectory()`. So `errorlogs/` and `orderlogs/` stay empty and `status.php`'s
"Error Logs" count is structurally 0. With `Config::$debug = true` a PHP error prints on the page
instead, which is how the gotchas below were found. **If the owner ever wants errors kept on disk,
delete the first `return false;` in each of those two functions** — but that is a config change and
was not made here.

## Gotchas found (each will recur)

### A phase shorter than 60 minutes makes the game "live", and a live game does not start when full

`Game::isLiveGame()` is `phaseMinutes < 60`. In `Game::needsProcess()`
(`objects/game.php:876`) the pre-game branch is
`$this->phase=='Pre-game' && count(members)==count(countries) && !$this->isLiveGame()`. So a
5-minute game **ignores the seventh player joining** and sits in Pre-game until its scheduled
start, which is `created + joinPeriod`.

The first attempt at this test used `phaseMinutes=5, joinPeriod=1440` and would have sat in
Pre-game for 24 hours with all seven players in it. It was erased (see below) and recreated with
`phaseMinutes=60`, which is *not* live and therefore starts the moment it fills.

So: **for a game that starts as soon as everyone joins, use `phaseMinutes >= 60`.** For a genuinely
live short-phase game, set `joinPeriod` to its minimum (5) and expect to wait that long.

The minimums `gamecreate.php` enforces, for reference: `phaseMinutes` 5…14400, `joinPeriod`
5…20160, `bet` ≥ 5 and ≤ your points, `excusedMissedTurns` 0…4, `minimumReliabilityRating` 0…100
and not above your own RR. `phaseMinutesRB` must be `-1` or within 10–100% of `phaseMinutes`.

### New games take up to 7 minutes to appear in the game listings

`gamelistings.php` prints "There are currently no new games" from `$Misc->GamesNew`, a cached
count, not from its own query — the query runs but the page decides on the count. That count is
recomputed by `miscUpdate::game()`, which `gamemaster/backgroundTasks.php:111` only runs when
`$Misc->LastStatsUpdate < time() - 60*7`.

Measured here: the game was invisible in both the **New** and **Active** tabs to a non-member for
several minutes, then appeared as `Active (1)` after the refresh. **This is not a bug to fix before
playing — just wait, or hand out the direct `board.php?gameID=N` link, which works instantly.**

Related: the **New** tab excludes games you are already in (`AND g.id NOT IN (SELECT ... WHERE
m1.userID = <you>)`), so the creator never sees their own game there — it is under **My games**.
That is intended behaviour, not a fault.

### `api.php?route=game/join` cannot be used to join a game you are not in

`JoinGame` (api.php:776) does not override `ApiEntry::requiresMembership()`, which defaults to
"this route takes a gameID, so the caller must be a member". With a session cookie the permission
check therefore rejects every would-be joiner with
`403 Access denied. User N is not member of associated game.` — the route is only usable by an API
key holding an explicit permission.

The React board never calls it (`grep -rn "game/join" game-src/src` is empty), so nothing user-facing
is broken; the human path is the `board.php` POST below, which is what the Join button submits.
Worth knowing if any automation is written against the API.

### `board.php` join/leave needs a `formTicket`, and it is not on every page

`libHTML::checkTicket()` matches `$_REQUEST['formTicket']` against `$_SESSION['formTickets']`, so
the ticket must come from a page *that session* rendered. The join button's page is
`gamelistings.php` (blocked for 7 minutes, above) — the easiest ticket source for scripting is
**another user's profile page**, `profile.php?userID=<someone else>`, whose private-message form
carries one. Your own profile does not render that form.

### Erasing a mistaken pre-game game

Leaving as the last remaining member erases it outright (`gamemaster/member.php:58` →
`processGame::eraseGame`). That is how the first `Smoke test` (gameID 1) was removed; the database
went back to zero games and zero members before gameID 2 was created. No admin action needed.

## The curl recipes

Everything below is plain `curl` against <http://localhost:43000/> with a per-account cookie jar.
Passwords are in `local-setup/credentials/accounts.md` (gitignored).

### Log in (cookie jar per account)

Sets `wD_Code`, `wD-Key` and `wD_Sess_User-<id>` in the jar.

```sh
login() {  # login <username> <password>
  local J=/tmp/smoke/$1.jar; rm -f "$J"
  curl -s -c "$J" -b "$J" -o /dev/null -X POST http://localhost:43000/logon.php \
    --data-urlencode "loginuser=$1" --data-urlencode "loginpass=$2"
  curl -s -b "$J" http://localhost:43000/index.php | grep -o 'profile.php?userID=[0-9]*' | head -1
}
login admin '<admin password>'
for n in 2 3 4 5 6 7; do login "player$n" "player${n}dip"; done
```

The echoed `profile.php?userID=N` confirms who the jar is.

### Create a game

`gamecreate.php` needs a `formToken` (`md5(time . Config::$secret)`); scrape it from the form.
`newGame[password]` empty means a public game. Use `phaseMinutes=60` so it starts on full.

```sh
J=/tmp/smoke/admin.jar
TOK=$(curl -s -b $J -c $J http://localhost:43000/gamecreate.php \
      | grep -o 'name="formToken" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b $J -c $J -o /tmp/smoke/create.html -X POST http://localhost:43000/gamecreate.php \
 --data-urlencode "formToken=$TOK" \
 --data-urlencode 'newGame[variantID]=1'            --data-urlencode 'newGame[name]=Smoke test' \
 --data-urlencode 'newGame[password]='              --data-urlencode 'newGame[passwordcheck]=' \
 --data-urlencode 'newGame[bet]=5'                  --data-urlencode 'newGame[potType]=Sum-of-squares' \
 --data-urlencode 'newGame[phaseMinutes]=60'        --data-urlencode 'newGame[phaseMinutesRB]=-1' \
 --data-urlencode 'newGame[nextPhaseMinutes]=60'    --data-urlencode 'newGame[phaseSwitchPeriod]=-1' \
 --data-urlencode 'newGame[joinPeriod]=60'          --data-urlencode 'newGame[anon]=No' \
 --data-urlencode 'newGame[pressType]=Regular'      --data-urlencode 'newGame[missingPlayerPolicy]=Normal' \
 --data-urlencode 'newGame[drawType]=draw-votes-public' \
 --data-urlencode 'newGame[minimumReliabilityRating]=0' \
 --data-urlencode 'newGame[excusedMissedTurns]=2'
grep -o 'gameID=[0-9]*' /tmp/smoke/create.html | head -1   # the new game's ID
```

`pressType` is one of `Regular`, `PublicPressOnly`, `RulebookPress`, `NoPress` (NoPress forces
`anon=Yes`). `potType` is one of `Winner-takes-all`, `Points-per-supply-center`, `Sum-of-squares`,
`Unranked`.

### Join a game (the human path — what the Join button posts)

```sh
join() {  # join <username> <theirUserID-is-not-needed> <gameID>
  local J=/tmp/smoke/$1.jar G=$2
  # any *other* user's profile carries a usable formTicket; userID 12 is admin
  local T=$(curl -s -b $J -c $J "http://localhost:43000/profile.php?userID=12" \
            | grep -o 'name="formTicket" value="[0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b $J -c $J -X POST "http://localhost:43000/board.php?gameID=$G" \
       -d "formTicket=$T" -d "join=Join" -o /dev/null
}
for n in 2 3 4 5 6 7; do join "player$n" 2; done
```

To leave instead, post `leave=Leave+game` with a fresh ticket.

### Read a player's own context (orders, order status, file URLs, SSE token)

```sh
curl -s -b /tmp/smoke/admin.jar \
  "http://localhost:43000/api.php?route=game/playercontext&gameID=2&orders=1&messages=1" | jq .
```

Session cookies authenticate `api.php` (`ApiSession`, api.php:1387) — **no API key is needed** for a
logged-in user acting on their own game.

### Submit orders and mark Ready

`POST api.php?route=game/orders`, body JSON. Every order object must carry all five of `type`,
`terrID`, `toTerrID`, `fromTerrID`, `viaConvoy` (nulls are fine where unused) — `array_key_exists`
is checked, not `isset`. `ready: "Yes"` is what makes the phase process early: it sets
`orderStatus.Ready` and appends the gameID to Redis `processHint`, and the gamemaster picks it up
on its next one-second pass once every country is Ready.

```sh
curl -s -b /tmp/smoke/player2.jar -H 'Content-Type: application/json' \
  -X POST 'http://localhost:43000/api.php?route=game/orders' -d '{
    "gameID": 2, "turn": 0, "phase": "Diplomacy", "countryID": 2, "ready": "Yes",
    "orders": [
      {"type":"Move",        "terrID":47,"toTerrID":48,"fromTerrID":null,"viaConvoy":"No"},
      {"type":"Support move","terrID":49,"toTerrID":48,"fromTerrID":47, "viaConvoy":"No"},
      {"type":"Move",        "terrID":46,"toTerrID":61,"fromTerrID":null,"viaConvoy":"No"}
    ]}'
```

`turn` and `phase` must match the game's current values exactly or the call 400s. Order types and
their required fields are in `api/README.md`; a build is
`{"type":"Build Army","terrID":<homeSC>,"toTerrID":<same>,"fromTerrID":null,"viaConvoy":null}`, and
a country with no builds submits `"orders": []` with `ready: "Yes"`.

Territory IDs come from `variants/Classic/cache/variant.json`:

```sh
curl -s http://localhost:43000/variants/Classic/cache/variant.json \
 | jq -r '.territories[] | "\(.id)\t\(.name)"'
```

### Send press, read the public files, watch the SSE stream

```sh
curl -s -b /tmp/smoke/admin.jar -H 'Content-Type: application/json' \
  -X POST 'http://localhost:43000/api.php?route=game/sendmessage' \
  -d '{"gameID":2,"countryID":1,"toCountryID":0,"message":"hello"}'

for f in game status history messages; do
  curl -s -o /dev/null -w "$f %{http_code}\n" "http://localhost:43000/cache/games/0/2/$f.json"
done

AUTH=$(curl -s -b /tmp/smoke/admin.jar \
  "http://localhost:43000/api.php?route=game/playercontext&gameID=2" | jq -r .sseAuth)
curl -s --max-time 5 \
  "http://localhost:43000/events?channelList=private-game2,private-game2-files,private-game2-country1&auth=$AUTH&have=&since=0"
```

`cache/games/<floor(id/100)>/<id>/` is the per-game directory, so game 2 is `cache/games/0/2/`.

### Watch a game's state from the database

```sh
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy -e "
  SELECT id,name,phase,turn,FROM_UNIXTIME(processTime) deadline FROM wD_Games;
  SELECT userID,countryID,status,orderStatus FROM wD_Members WHERE gameID=2 ORDER BY countryID;"
```

## For the owner

### Creating a game from the UI

Log in, then **Games ▸ Create a game** (<http://localhost:43000/gamecreate.php>). Fill the form and
submit; you are redirected straight to your new game's board and are automatically its first
member with your bet placed.

Two things to know, both explained above:

- **Set the phase length to 1 hour or more** if you want the game to start the instant the last
  player joins. Anything under an hour is a "live" game, which only starts at its scheduled start
  time (creation + the joining period), however fast it fills.
- **Your game will not appear in the Games listings for up to 7 minutes.** Do not re-create it.
  Send the other players the direct link — `http://localhost:43000/board.php?gameID=<N>` — which
  works the moment the game exists.

### Joining as a second player, on a second browser

Use a private/incognito window, or a different browser entirely — the session cookie is per
browser profile, so two tabs in the same window cannot be two players.

Log in at <http://localhost:43000/logon.php> as any of `player2` … `player10` (passwords in
`local-setup/credentials/accounts.md`; the rule is `<username>dip`, e.g. `player2` / `player2dip`).
`admin` keeps its own random password and is the only moderator — play as a `playerN` account when
you want the ordinary player's view, since a moderator sees more than a player does.

Then either open the direct board link, or wait for the game to appear under **Games ▸ New** and
press **Join**.

### The `Smoke test` game has been left running

It sits at Spring 1902 with a **60-minute deadline** and it will keep processing on the hour with
whatever orders are in, putting the seven accounts into civil disorder after their 2 excused missed
turns. That is fine if you just want to look at it, but if you want it frozen exactly as it is,
pause it from <http://localhost:43000/admincp.php> as `admin` — the **togglePause** action
(`admin/adminActions.php:370`), which takes a gameID — rather than letting it grind on. To throw it
away instead, use the panel's **cancelGame** action (`admin/adminActionsSeniorMod.php:261`).

Its history is real and worth a look on the React board: use the phase slider to replay Spring
1901's two bounces (Galicia and the Black Sea) and the French supported move into Burgundy.

## Status

**Game 2 is paused** (2026-09-20). `wD_Games.processStatus = 'Paused'` and its `processTime` is
`NULL`, so it will no longer process on the hour and nobody goes into civil disorder. It was paused
with the admin CP's **togglePause** action, posted as `admin`:

```sh
curl -s -b /tmp/smoke/admin.jar -c /tmp/smoke/admin.jar -X POST http://localhost:43000/admincp.php \
  -d 'actionName=togglePause' -d 'gameID=2'
# -> "This game is now paused."
```

No `formTicket` is needed: `adminActionsForms::isActionDangerous()` returns true for any action
without a `<action>Confirm` method, and `togglePause` has none, so it runs on the first POST.

**To unpause**, run exactly the same action again — it toggles — or use the **togglePause** entry in
the game-admin panel on the game's own page in the UI (or <http://localhost:43000/admincp.php> as
`admin`, gameID 2). On unpause the deadline comes back as the time that was left when it was paused
(`gamemaster/game.php:1364` restores `processTime = pauseTimeRemaining + time()`), and the members
are notified.
