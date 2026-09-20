# PLAYING — accounts, and hosting a game for friends

The owner's guide to *using* the LAN instance, as opposed to keeping it alive (that is
[`RUNBOOK.md`](RUNBOOK.md)). Everything below was verified against the code in this checkout at
the line references given; nothing is repeated from memory.

Two constants:

| | |
| --- | --- |
| Site | LAN: <http://10.13.101.254:43000/> · on this host: <http://localhost:43000/>. The LAN address is DHCP and can move — RUNBOOK §7 is authoritative |
| Repo / compose dir | `/home/normie/Documents/Projects/webDiplomacy` (every `docker compose` command is run from here) |
| Credentials | `local-setup/credentials/accounts.md` — **gitignored**; no password appears in this file |

Where a shell snippet needs the database root password it uses `"$DB_ROOT_PASSWORD"`. Export it
first (it is the compose default, quoted in RUNBOOK §3):

```sh
read -rs DB_ROOT_PASSWORD && export DB_ROOT_PASSWORD
```

---

## A. Accounts

### A.1 A player changes their own password

The settings page is **`usercp.php`** — <http://localhost:43000/usercp.php>, linked as
*"User account settings"*. There is no `usersettings.php` in this codebase.

The form is rendered from `locales/English/user.php` (included by `usercp.php:215`) and has
exactly two password fields:

| Field | Input name |
| --- | --- |
| **Password** | `userForm[password]` (`maxlength=30`) |
| **Confirm Password** | `userForm[passwordcheck]` (`maxlength=30`) |

Steps: log in → **usercp.php** → type the new password in both boxes → **Submit**.

What happens, from `usercp.php:148-158`:

- The two must match, or you get *"The two passwords do not match"* (`objects/user.php:451-459`).
- On success the row is updated, **`libAuth::keyWipe()` logs you out of every session**, and the
  page sets `refresh: 3; url=logon.php`. You must log in again with the new password. This is
  normal, not a failure.
- The same form also carries **E-mail**, **Comment** and the opt-in/dark-mode options. Changing
  the e-mail does *not* apply immediately: it mails a confirmation link
  (`usercp.php:88-105`) — read it in mailhog, and remember the hard-coded-`https://` gotcha in
  RUNBOOK §8.
- **The username field is not applied here.** `User::processForm` parses `userForm[username]`,
  but `usercp.php:125` only whitelists `email`, `homepage` and `comment`, so a player cannot
  rename themselves. See A.3.

### A.2 Admin resets someone's password

#### Random password — admin CP `resetPass`

<http://localhost:43000/admincp.php>, logged in as `admin`, the **User actions** section, action
**Reset password**, parameter `userID`.

`admin/adminActionsSeniorMod.php:192-212`:

```php
$password = base64_encode(rand(1000000,2000000));
$DB->sql_put("UPDATE wD_Users SET password = UNHEX('".libAuth::pass_Hash($password)."') ...");
return l_t('Users password reset to %s',$password);
```

- **It prints the generated password on the response page and nowhere else.** Copy it before you
  navigate away; there is no second chance and no mail is sent.
- It refuses to reset another Admin's password, a Moderator's password unless you are Admin, or a
  Bot's password unless you are Admin.

#### Self-service link — admin CP `generateResetLink`

Action **Generate Password Reset Link**, parameter `email` (`admin/adminActions.php:830-848`).
It prints a `logon.php?...&forgotPassword=3` link for you to hand to the player, who then picks
their own password. It **refuses Mod/Admin e-mails** by design, so it cannot be used on `admin`.
Hand-edit the `https://` to `http://` (RUNBOOK §8).

#### A **chosen** password — SQL

The hash is `md5(Config::$salt . md5($password))`, stored **binary** in `wD_Users.password`:

- `lib/auth.php:326` — `static public function pass_Hash($password) { return md5(Config::$salt.md5($password)); }`
- used by the settings form at `objects/user.php:455` (`UNHEX('...')`), by `resetPass`, and by
  `libAuth::userPass_Key` at logon. There is no per-user salt and no bcrypt.

Verified on this install: `pass_Hash(<player2's password from `credentials/accounts.md`>)` equals
`LOWER(HEX(password))` for the `player2` row exactly.

```sh
cd /home/normie/Documents/Projects/webDiplomacy

NEWPASS='<the password you want>'          # leading space keeps it out of bash history
TARGET='player2'                           # username to change

HASH=$(docker compose exec -T -w /application php-fpm php -r \
  'define("IN_CODE",1); require "config.php"; echo md5(Config::$salt.md5($argv[1]));' -- "$NEWPASS")

docker compose exec -T mariadb mysql -u root --password="$DB_ROOT_PASSWORD" webdiplomacy \
  -e "UPDATE wD_Users SET password = UNHEX('$HASH') WHERE username = '$TARGET' LIMIT 1;"
```

The `php -r` step is what reads `Config::$salt` out of the gitignored `config.php` — never type
the salt in by hand. Confirm by logging in at <http://localhost:43000/logon.php>, then write the
new password into `local-setup/credentials/accounts.md`.

Existing sessions are **not** invalidated by the SQL route (no `keyWipe`); the old session cookie
keeps working until it expires. Use the settings form or `resetPass` if that matters.

### A.3 Renaming a username

**Yes, an admin action exists.** `changeUsername` in
`admin/adminActionsRestricted.php:717-767`, declared at line 99:

```
'changeUsername' => array('name' => 'Change username',
    'params' => array('userID'=>'User ID', 'username'=>'New Username', 'reason'=>'Reason'))
```

It lives in `adminActionsRestricted`, which `admin/adminActionsForms.php:369-370` instantiates
**only for `$User->type['Admin']`** — so on this install only the `admin` account sees it.
Use <http://localhost:43000/admincp.php> as `admin`. The **reason is mandatory** (it returns
*"Could not change username because no reason was given"* if empty).

What it does:

1. Rejects a name already present in `wD_Users` (line 733).
2. Writes an audit row into **`wD_UsernameHistory`** (`userID, oldUsername, newUsername, date,
   reason, changedBy`).
3. `UPDATE wD_Users SET username = ... WHERE id = ...`.
4. If `Config::$customForumURL` is set, also updates `phpbb_users.username` and
   `username_clean`. Not set here, so this branch does nothing on this install.

**What it misses.** Every column on this install that stores a username as text, confirmed
against the live schema (`information_schema.COLUMNS ... COLUMN_NAME LIKE '%sername%'`) and
`install/FullInstall/fullInstall.sql`:

| Table | Column | Type | Role | Updated by `changeUsername`? |
| --- | --- | --- | --- | --- |
| `wD_Users` | `username` | `varchar(30)`, `UNIQUE KEY uname` | The canonical value | **yes** |
| `wD_ApiKeys` | `username` | `varchar(150)` | Denormalised copy, filled by `UPDATE wD_ApiKeys a INNER JOIN wD_Users u ...` in the installer | **no** |
| `wD_UsernameHistory` | `oldUsername`, `newUsername` | `varchar(30)` | Audit log, append-only | written, not rewritten |
| `phpbb_users` | `username`, `username_clean` | forum | Only if `Config::$customForumURL` is set | conditional; N/A here |

Nothing else denormalises it — games, members, messages and notices all join on `userID`.

So after any rename, resync the one stale copy:

```sh
docker compose exec -T mariadb mysql -u root --password="$DB_ROOT_PASSWORD" webdiplomacy -e \
  "UPDATE wD_ApiKeys a INNER JOIN wD_Users u ON u.id = a.userID SET a.username = u.username;"
```

(No API keys exist on this install yet, so this is a no-op today and a real fix the moment one
does.)

**Renaming purely in SQL**, if you must (skips the audit row and the uniqueness check — prefer
the admin action):

```sh
docker compose exec -T mariadb mysql -u root --password="$DB_ROOT_PASSWORD" webdiplomacy -e "
  UPDATE wD_Users SET username = '<new>' WHERE username = '<old>' LIMIT 1;
  UPDATE wD_ApiKeys a INNER JOIN wD_Users u ON u.id = a.userID SET a.username = u.username;"
docker compose exec -T redis redis-cli FLUSHALL     # rows are cached; see RUNBOOK §8
```

**Constraints on the new name** (`register/processUserForm.php:82-85`, schema line 514):

- **Maximum 30 characters** — `varchar(30)`. Longer is truncated or rejected by the database, not
  by a friendly message.
- **Must be unique** — `UNIQUE KEY uname (username)`; registration checks `User::findUsername`
  first, `changeUsername` does its own `SELECT`.
- **Must not contain `diplonow_`** — reserved for play-now servers.
- No minimum length and no character-class rule is enforced anywhere.
- Usernames are **not** case-folded on this install (no `username_clean` equivalent in
  `wD_Users`), so `Alice` and `alice` are two different, both-allowed accounts. Avoid that.

### A.4 Adding an eleventh account

Three routes, written out in full with exact commands under **"Adding an eleventh account"** in
`local-setup/credentials/accounts.md` (gitignored — read it there, the passwords are not repeated
here):

1. **Mint an e-mail token and POST `register.php`** — the route used for `player2`…`player10`.
   One request; you choose the username, password and e-mail; runs the real registration code so
   the account gets its welcome notice and user-options row. Registration is 404'd at nginx
   (RUNBOOK §6), so the `location = /register.php` block must be commented out for the duration.
2. **Admin CP** — `createUser` (username only; sets `email = username`, no password), then
   `changeEmail`, then `resetPass`. Works with registration still closed. Three round-trips and
   **you cannot choose the password** — `resetPass` generates one and prints it once.
3. **Straight SQL** — `INSERT INTO wD_Users ... UNHEX('<hash from A.2>')`. Last resort; skips the
   welcome notice and the user-options row.

A fourth, if you would rather the new player chose their own password: admin CP
**`generateRegistrationLink`** (`admin/adminActions.php`, parameter `email`) prints a registration
link — but it lands on `register.php`, which is 404'd, so it needs the same nginx block commented
out as route 1.

Whichever route: record the credential in `local-setup/credentials/accounts.md` in the same
sitting, and confirm the account can log in at <http://localhost:43000/logon.php>.

---

## B. Hosting a game for N friends

### B.1 Player count → variant

Player count is the length of `$countries` in `variants/<Name>/variant.php`. "Enabled" means the
ID is in `Config::$variants` in the gitignored `config.php`, which after issues 007, 008 and 009
is all nineteen of `1, 2, 3, 4, 5, 9, 12, 15, 17, 19, 20, 22, 23, 26, 45, 46, 62, 70, 91`. The
React (point-and-click) board is whitelisted to exactly three variants —
`Game::isClassicGame()`, `objects/game.php:565-568`, is
`name == 'Classic' || 'ClassicGvI' || 'ClassicFvA'` — everything else renders on the legacy
`board.php` drop-down board, **including the two-player variants that play on Classic's own
geography**, because the whitelist matches by name, not by map.

| Players | Variant(s) | `$id` | Enabled? | Board |
| ---: | --- | ---: | --- | --- |
| **2** | ClassicFvA (*Classic — France vs Austria*) | 15 | enabled | **React** |
| **2** | ClassicGvI (*Classic — Germany vs Italy*) | 23 | enabled | **React** |
| **2** | ClassicEvT (*Classic — England\* vs Turkey*) | 62 | enabled | legacy |
| **2** | ClassicFGvsRT (*Classic — Frankland vs Juggernaut*) | 26 | enabled | legacy |
| **2** | ColdWar | 91 | enabled | legacy |
| **2** | Duo | 22 | enabled | legacy |
| **2** | GoT2 (*Game of Thrones — Tully vs Lannister*) | 46 | enabled | legacy |
| **3** | — none installed | | | |
| **4** | — none installed | | | |
| **5** | AncMed (*The Ancient Mediterranean*) | 9 | enabled | legacy |
| **6** | — none installed | | | |
| **7** | Classic | 1 | enabled | **React** |
| **7** | FleetRome (*Classic, with fleet in Rome*) | 3 | enabled | legacy |
| **7** | CustomStart (*Classic with a custom start*) | 4 | enabled | legacy |
| **7** | BuildAnywhere (*Classic, but build anywhere*) | 5 | enabled | legacy |
| **7** | Colonial (*Colonial Diplomacy*) | 12 | enabled | legacy |
| **7** | Zeus5 (*Zeus 5*) | 70 | enabled | legacy |
| **8** | GoT (*Game of Thrones*) | 45 | enabled | legacy |

Also installed, outside the 2–8 range: **World** (`$id` 2, **17** players, legacy),
**Modern2** (19, 10 players, legacy), **Empire4** (20, 10 players, legacy), **ClassicChaos**
(17, **34** players, legacy).

**Every variant in the tree is now enabled and has been played through at least one adjudicated
phase.** Nothing is `present`-but-disabled any more.

**Picking one for two people.** Seven is a lot of choice, but four of them (FvA, GvI, EvT,
FGvsRT) are the same Classic board with different starting units, and only FvA and GvI get the
point-and-click board. The three that feel genuinely different are **ColdWar** (a world map,
USSR vs USA), **Duo** (an original symmetric map, with eight static neutral units in the middle
that both sides have to chew through) and **GoT2** (Westeros). Suggested defaults: **ClassicFvA**
if you want the nice board, **Duo** or **ColdWar** if you want a new map.

**Three, four and six players are still not servable.** For an awkward number the practical
answers are: play Classic (or one of its five seven-player cousins) with the spare people
spectating or sharing a seat, or run two of the two-player maps in parallel.

**The seven-player cousins, briefly**, since they are new and the New Game form only shows a
name: *FleetRome* is Classic with Italy starting `F Rome` instead of `A Rome`. *CustomStart* is
Classic where the game opens in a **Builds phase with no units at all** and everyone places their
own three (four for Russia) — expect to spend the first five minutes on that, and note fleets can
only go on coastal home centres. *BuildAnywhere* is Classic where you may build in **any** supply
centre you own, not just your home ones — it only starts to matter in your second winter.
*Colonial* (125 territories, solo on 30) and *Zeus5* (113 territories, solo on 21, also a custom
start) are real different maps and much longer games.

### B.2 Creating the game, step by step

1. **Log in** at <http://localhost:43000/logon.php>. Any account can create a game; `admin` has no
   special power here, and playing as a `playerN` account gives you the ordinary player's view
   (a moderator sees more of the board than a player does).
2. **Games ▸ Create a game** — <http://localhost:43000/gamecreate.php>.
3. Fill the form. Fields, their validation, and what to pick (`gamecreate.php:41-210`,
   `locales/English/gamecreate.php`):

| Field | Form name | Validation | For a game night |
| --- | --- | --- | --- |
| **Game Name** | `newGame[name]` | non-empty, unique | anything |
| **Variant** | `newGame[variantID]` | must be a key of `Config::$variants` | from the table above |
| **Bet size** | `newGame[bet]` | `5 … your points` (everyone starts on 100) | 5 |
| **Phase length** | `newGame[phaseMinutes]` | 5 … 14400 (10 days) | **60 or more — see B.3** |
| **Phase length (Retreats/Builds)** | `newGame[phaseMinutesRB]` | `-1`, or 1…14400 *and* within 10–100 % of `phaseMinutes` | `-1` (same as the main phase) |
| **Time to Fill Game** | `newGame[joinPeriod]` | 5 … 20160 (14 days) | 60 |
| **Game Messaging** (press) | `newGame[pressType]` | `Regular` (UI: *All*) · `PublicPressOnly` (*Global only*) · `NoPress` (*None*) · `RulebookPress` (*Per rulebook*) | `Regular` for a talking game; `NoPress` for gunboat |
| **Scoring** | `newGame[potType]` | `Winner-takes-all` (UI: *DSS*) · `Sum-of-squares` (*SoS*) · `Points-per-supply-center` · `Unranked` | anything; points are meaningless on a LAN box |
| **Anonymous players** | `newGame[anon]` | `Yes`/`No` | `No`, unless you want hidden identities |
| **Draw votes** | `newGame[drawType]` | `draw-votes-public` / `draw-votes-hidden` | public |
| **Excused delays** | `newGame[excusedMissedTurns]` | 0 … 4 | 2 |
| **Required reliability rating** | `newGame[minimumReliabilityRating]` | 0 … 100, and **not above your own** | 0 |
| **Invite Code / Password** | `newGame[password]` + `passwordcheck` | must match; empty = public game | empty, or set one for a private table |
| **Fill Empty Spots with Bots** | `newGame[botFill]` | only shown/honoured for variant **1 + NoPress** | **leave off — see B.7** |

Forced combinations, worth knowing before you are surprised:

- **Two-player variants are forced unranked** — but only three of the seven.
  `gamecreate.php:144` hard-codes `variantID == 15 or 23 or 91` ⇒ `bet = 5`,
  `potType = 'Unranked'`. **Duo (22), ClassicEvT (62), ClassicFGvsRT (26) and GoT2 (46) are not
  in that list**, so a two-player game on one of those can be created ranked and for a real bet.
  Points are meaningless on a LAN box, so this is a curiosity rather than a problem; if it ever
  matters, add the IDs to that line (it is core code, not variant code, so it is a real patch)
  or just pick *Unranked* on the form.
- **No-press forces anonymous**: `pressType = 'NoPress'` ⇒ `anon = 'Yes'`, to stop out-of-game
  messaging (`gamecreate.php:165-168`).
- **Bot fill forces no-press + unranked** (`gamecreate.php:176-182`).
- `phaseMinutes > 60` ⇒ `nextPhaseMinutes` is forced equal to it and `phaseSwitchPeriod` to `-1`
  (`gamecreate.php:110-115`). The "phase swap" advanced fields only do anything for live games.

4. **Submit.** You are redirected to your new game's board, already its first member with your bet
   placed.

### B.3 "Live" games, and the trap

`Game::isLiveGame()` (`objects/game.php:767-770`) is exactly:

```php
return $this->phaseMinutes < 60;
```

That single fact drives the most annoying failure mode on this install. In
`Game::needsProcess()` (`objects/game.php:876`) the pre-game branch is

```php
$this->phase=='Pre-game' && count($this->Members->ByID)==count($this->Variant->countries) && !($this->isLiveGame())
```

— so a **live game does not start when it fills**. It sits in Pre-game until its scheduled start,
which is `created + joinPeriod`. A 5-minute-phase game with a 24-hour join period sits there for
24 hours with everyone already in it. (This happened on the first attempt at issue 001.)

- **Want it to start the moment the last friend joins? Set phase length to 60 minutes or more.**
  That is the setting for a game night where everyone is at their keyboard and pressing Ready.
- **A genuinely "live" short-phase game** (5–59 minute turns, which also unlocks the "phase swap"
  fields so it lengthens its turns after a few hours) only starts at its scheduled time. Set
  **Time to Fill Game to its minimum, 5 minutes**, and expect to wait exactly that long after
  creating it. Use this when you actually want a fast clock and are happy to wait for the start
  gun.

Practical recommendation: **60-minute phases**. Everyone presses Ready, the turn processes within
about a second (the gamemaster runs once a second here), and the hour is only ever a backstop for
someone who wandered off.

### B.4 The "not in the list for up to 7 minutes" gotcha

`gamelistings.php` prints its tab counts from `$Misc->GamesNew`, a **cached** count, not from its
own query. That count is recomputed by `miscUpdate::game()`, which
`gamemaster/backgroundTasks.php:111` only calls when `$Misc->LastStatsUpdate < time() - 60*7`.

So a brand-new game is **invisible in the Games listings for up to seven minutes**, to everybody.

- **Do not create it again.** It exists.
- **Hand out the direct link instead** — `http://localhost:43000/board.php?gameID=<N>` — which
  works the instant the game exists. The gameID is in the URL you were redirected to after
  creating.
- Separately, the **New** tab deliberately excludes games you are already in, so the creator never
  sees their own game there. It is under **My games**. That is intended, not a fault.

### B.5 How friends join, and how you know it started

Each friend, on their **own device or at least their own browser profile** (the session cookie is
per browser profile — two tabs in one window cannot be two players; use a private window if you
must double up):

1. Open the LAN URL, log in at `/logon.php` with their own account, ticking **remember me**.
2. Open `board.php?gameID=<N>` (your link), or wait for the listing and use **Games ▸ New**.
3. Press **Join**. The bet is deducted from their points; on a two-player variant it is forced to
   5 and unranked anyway. If you set an invite code they must enter it.
4. To back out before it starts, the same page offers **Leave game** and refunds the bet.

**You know it started when** the board stops saying *Pre-game* and shows **Spring, 1901** with a
countdown to the deadline, and units appear. On a non-live game this happens within about a second
of the last player joining — the gamemaster deals the countries, creates the units and sets the
pot (measured at ~5 s in issue 001). The game also appears under **My games** on everyone's home
page. If it fills and nothing happens, you made a live game: see B.3.

### B.6 Playing: orders, press, deadlines, Ready

**On the React board** (Classic, ClassicFvA, ClassicGvI — `board.php?gameID=N` 302-redirects there,
or go straight to `/game/?gameID=N`):

- Click a unit, then click the order type, then the destination. Orders you have entered but not
  sent are drawn as unsaved.
- **Save** stores the orders without committing you. **Ready** stores them *and* declares you
  finished; the button then reads **Unready** and you can take it back while the phase is still
  open (`game-src/src/components/ui/WDOrderStatusControls.tsx:72-101`).
- There is an **Automatically Save Game** toggle in the controls panel, on by default.
- You can only save or ready while viewing the **current** phase — scroll the phase slider back to
  the present if the buttons are greyed out.
- Press is the chat panel on the same page; with `Regular` press you get a global channel plus one
  per country.

**On the legacy board** (everything else): the orders are drop-downs in a table under the map.
Fill them in, press **Save**, then press **Ready** — which becomes **Not ready** once set
(`board/orders/orderinterface.php:399-406`). With `RulebookPress` the Save button is hidden
outside Diplomacy phases. Chat is the box beside the map.

**Deadlines and Ready.** Every phase has a deadline of `phaseMinutes` from when the previous one
processed. Two ways it ends:

- **Everyone is Ready** — the phase processes almost immediately. `ready` sets the member's
  `orderStatus.Ready` and pushes the gameID onto a Redis `processHint`; the gamemaster picks it up
  on its next one-second pass once every country is Ready. This is how a game night actually runs.
- **The deadline passes** — whatever is entered is adjudicated, and anyone who submitted nothing
  takes a missed turn. After their **excused delays** are used up they go into civil disorder.
  That is why a paused-but-forgotten game quietly wrecks itself; see B.8.

A country with **no builds** still has to press Ready with an empty order set; that is handled
correctly and does not block the phase (verified in issue 001, Winter 1901).

### B.7 Bots: not available

**You cannot fill empty seats with bots on this install.** The "Fill Empty Spots with Bots"
checkbox appears in the create-game form for Classic + No-press, but nothing is behind it: the bot
checkout `webdiplomacy_bots_mila` that the commented-out bot section of `docker-compose.yml` points at **is not on this
machine and is not published anywhere** — the intended public repo
`kestasjk/diplomacy_mila` exists but contains only a LICENSE, and both prebuilt images the README
advertises are gone.

Full evidence and the decision that has to be made: **[`issues/004-findings.md`](issues/004-findings.md)**.
Issue [`005-bots-run.md`](issues/005-bots-run.md) is `blocked` on it.

So a **two-player game needs two humans**. Pick one of the three two-player variants in B.1.
Leaving bot-fill ticked would only force the game to no-press and unranked for nothing.

### B.8 Pausing, extending, cancelling

**Pause by player vote (no admin needed).** Each player has a **Pause** vote on the game page.
When it is unanimous `gamemaster/game.php:53` calls `togglePause()`; the deadline is stored in
`pauseTimeRemaining` and `processTime` goes `NULL`, so nothing processes and nobody goes into
civil disorder. Voting again unpauses, and `processTime` comes back as
`pauseTimeRemaining + time()` (`gamemaster/game.php:1364`) — you get back the time you had left,
not a fresh phase.

**Pause as admin.** <http://localhost:43000/admincp.php> as `admin`, **Toggle-pause game**,
parameter `gameID` (`admin/adminActions.php:35`). It toggles, so the same action unpauses. No
confirmation step: `adminActionsForms::isActionDangerous()` returns true for any action without a
`<action>Confirm` method, and `togglePause` has none, so it fires on the first submit.

If you are the game's **director** (set with admin CP **`setDirector`**, gameID + userID) the same
toggle appears in a *Director action forms* panel on the game's own page — `board.php:304-308`
only renders that panel for `Game::isDirector()`, not for plain moderators.

**Extend a deadline.** Two admin CP actions, both on `gameID`:

- **Reset process time** (`setProcessTimeToPhase`) — sets the deadline to *now + the phase length*,
  giving everyone a fresh full phase. This is the "we all need a break" button.
- **Change phase length** (`changePhaseLength`, + `phaseMinutes` 5…14400) — changes the game's
  phase length from here on **and** resets the next deadline to now + the new length
  (`admin/adminActions.php:250-273`). Refuses if the game is paused, crashed, finished or
  mid-process, so unpause first.

There is also **Process game now** (`setProcessTimeToNow`, senior-mod) — it forces the phase
through immediately and **NMRs anyone without orders**. Rarely what you want.

**Cancel a game.**

- **Before it starts:** everyone just leaves. When the *last* member leaves, the game is erased
  outright (`gamemaster/member.php`, `processMember::remove()` → `processGame::eraseGame`) — no admin action, no trace.
  This is the clean way to throw away a misconfigured game.
- **After it starts:** admin CP **Cancel game** (`cancelGame`, gameID,
  `admin/adminActionsSeniorMod.php:53-57`). It refunds every player's bet unless the game is
  finished, then deletes the game. Its own description warns that it **does not work on games that
  have not started** — there it just forces everyone into CD — which is why the leave-route above
  is the right one for a pre-game game.
- **Draw game** (`drawGame`, gameID) ends a running game by splitting the pot among survivors per
  the scoring system, which is the friendlier ending for "we have to stop, it is midnight".

### B.9 Game-night checklist

1. **Everyone bookmarks the LAN URL** — currently `http://10.13.101.254:43000/` (issue
   [`006-lan-access.md`](issues/006-lan-access.md)). It is a DHCP address and can change, so
   **RUNBOOK §7 is authoritative**; re-read it if the bookmark stops working.
2. **Each player logs in on their own device**, at `/logon.php`, with **remember me** ticked, and
   confirms the name in the top corner is theirs. One browser profile per player.
   (On plain HTTP, push notifications will not work — a secure context is required. The live board
   updates over `/events` regardless. RUNBOOK §7.)
3. **Health check first** — the one-shot block in RUNBOOK's Appendix. Five seconds, and it catches
   "the board was never built" and "the gamemaster is not running" before seven people are waiting.
4. **Admin (or whoever hosts) creates the game** — B.2, with **phase length 60 minutes** so it
   starts the instant it fills.
5. **Share the direct link**, `http://<host>:43000/board.php?gameID=<N>`. Do not wait for the
   listing; it is up to seven minutes behind (B.4).
6. **Watch it fill**, then confirm it flipped to Spring 1901 (B.5).
7. **Play by Ready**, not by the clock — every phase processes within about a second of the last
   Ready (B.6).
8. **Before packing up**, pause it: unanimous Pause vote, or admin CP **Toggle-pause game**. An
   unpaused 60-minute game grinds on all night and puts everybody into civil disorder once their
   excused delays run out (B.8).
9. **Take a backup** if the game matters: `local-setup/scripts/db-backup.sh` (RUNBOOK §3). Cron
   does it at 03:17 anyway, but the one you take deliberately is the one you trust.
