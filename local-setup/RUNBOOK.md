# RUNBOOK — the LAN webDiplomacy instance

Operations manual for the private instance in this checkout. Written for **someone who has
forgotten everything**, including the author. Every command below was run on this host; where a
step could not be proven at the time of writing it says so explicitly.

Two constants used throughout:

| | |
| --- | --- |
| Repo / compose directory | `/home/normie/Documents/Projects/webDiplomacy` |
| Site | <http://localhost:43000/> |
| Backups | `/home/normie/webdiplomacy-backups/` (outside the repo, mode 0700) |
| Credentials | `local-setup/credentials/` — **gitignored, never committed** |
| Working branch | `local` (see [§8](#8-git-workflow)) |

Every `docker compose` command must be run from the repo directory; the compose file is found by
that, not by the container names.

---

## 1. Start, stop, restart

### Start from cold

```sh
cd /home/normie/Documents/Projects/webDiplomacy
docker compose --profile core --profile dev up -d
```

- `core` = mariadb, webserver, php-fpm, sse, redis — the site. **Required.**
- `dev` = mailhog (mail catcher, <http://localhost:43001>) and phpMyAdmin
  (<http://localhost:43009>). Optional but harmless, and mailhog is how you read a password-reset
  mail.
- `build` and `reactdev` are not part of running the site; see below.

First start after a reboot takes ~20 s for the containers plus up to a minute before
`status.php` is green, because php-fpm's entrypoint re-checks the database and the sse container
runs `npm ci` before its loop starts. Go to [§2](#2-health-check) and wait for the markers, don't
guess.

### The React board build

`/game/` is **not in git**. A fresh checkout — or one after `git clean -xdf` — has no board and
`http://localhost:43000/game/` 404s or shows a blank page. Build it once:

```sh
docker compose --profile build up game-build      # runs, then exits 0
```

Takes a few minutes. `game-build exited with code 0` is success. Re-run it only when
`game-src/` changes; the built output persists in the working tree.

### Stop

```sh
docker compose --profile core --profile dev stop     # keep containers, just stop them
docker compose --profile core --profile dev down     # remove the containers too
```

**What is safe:**

- `stop` / `start` / `restart` of anything — always safe.
- `down` — safe. The database lives on the named volume `webdiplomacy_dbdata`
  (LOCAL DEVIATION, issue 002), so `down` and `up -d` preserve every game. Everything else is in
  git or rebuilt on start.
- Recreating `mariadb` alone (`docker compose rm -f mariadb && docker compose up -d`) — safe,
  same reason.

**What destroys data:**

- `docker volume rm webdiplomacy_dbdata` — this, and only this, deletes the database.
- `docker compose down -v` — the `-v` deletes the volume. **Never use `-v` here.**
- `git clean -xdf` in the repo — removes `config.php` (gitignored), the built `/game/`, every
  variant `cache/` and `node_modules`. It does *not* touch the database or the backups, which is
  why the backups live outside the tree.

After a data loss the site does **not** error: php-fpm's watchdog notices the empty database
within a minute and silently reinstalls a blank one. See the triage table in
[§2](#2-health-check).

### Restart just one thing

```sh
docker compose restart webserver     # after editing phpdocker/nginx/nginx.conf (~2 s)
docker compose restart sse           # after editing sse-server/ (re-runs npm ci, ~20 s)
docker compose restart php-fpm       # after editing config.php
```

Validate an nginx change *before* restarting, or you get a container that exits and a site that
is simply gone:

```sh
docker compose exec webserver nginx -t
# nginx: configuration file /etc/nginx/nginx.conf test is successful
```

A `docker compose restart webserver` costs the gamemaster exactly one failed call — the sse log
prints `Gamemaster call failed (1 in a row): fetch failed` then
`Gamemaster call succeeded again, after 1 failures`. That pair is normal and needs no action.

### After a host reboot

Docker itself starts (`systemctl is-enabled docker` → `enabled`), and the containers with a
restart policy come back by themselves: **mariadb** (`always`), **redis** and **sse**
(`unless-stopped`). **webserver, php-fpm, mailhog and phpmyadmin have no restart policy
(`restart=no`) and do not come back.** The symptom is exactly the one in the triage table:
nothing answers on 43000 while the sse log fills with `fetch failed`.

So the reboot procedure is: log in and run the cold-start command in [§1](#1-start-stop-restart).
It is idempotent — containers already running are left alone.

(This was determined from `docker inspect -f '{{.HostConfig.RestartPolicy.Name}}'` on every
container, not from an actual reboot; a reboot has not been performed while a game was live. If
unattended survival is wanted, add `restart: unless-stopped` to `webserver` and `php-fpm` in
`docker-compose.yml` — that is an upstream-file change and has not been made.)

---

## 2. Health check

Run these in order. Each one answers a different question.

### 2.1 Status page — is the gamemaster running?

<http://localhost:43000/status.php>, or from a shell:

```sh
curl -s http://127.0.0.1:43000/status.php | sed 's/<[^>]*>/|/g' | tr -s '|' '\n' \
  | grep -nE 'Game Processing|Gamemaster Called|SSE Server'
```

Healthy output:

```
Game Processing        ✅ Now since last process
Gamemaster Called      ✅ Now since last call
SSE Server Online      ✅ 0 minutes, 5 seconds since last update
```

"Now", or anything under a minute, is healthy. Minutes or hours means processing has stopped —
go to the triage table.

Two warnings on this page are **permanent here and are not faults** (recorded in issue 001):

- **Game Backup Archived ⚠️ 20716 days …** — nothing ships game backups offsite on a LAN box.
- **SSE Server Last Client Connect ⚠️ 20716 days …** — clears as soon as any browser opens a
  game board, and returns after a restart. It does not mean the event server is broken; "SSE
  Server Online" is the check that does.

### 2.2 Entrypoint log — did the install finish?

Served as a static file, so it works even when PHP does not:

```sh
curl -s http://127.0.0.1:43000/gamemaster-entrypoint.txt | tail -4
```

Healthy tail:

```
READY - webDiplomacy system initialized
The gamemaster runs from the sse container: docker compose logs -f sse
Watching for the database being emptied
.......
```

The trailing dots are the watchdog's one-per-minute heartbeat and grow forever. If the file ends
**before** `READY`, read the whole file: the last line is the step that failed (a composer
failure is the usual one). The same text is also in `docker compose logs -f php-fpm`.

### 2.3 Event-server log — are gamemaster calls arriving?

```sh
docker compose logs -f sse
```

Healthy, once a minute:

```
SSE stats: 0 clients open on 0 channels, 0 messages forwarded to clients in the last minute;
60 gamemaster runs, 0 failed
```

~60 runs a minute, 0 failed. `Waiting for php-fpm to write sse-server/.env` repeating forever
means the PHP entrypoint never finished (§2.2). Persistent `fetch failed` means nginx is down.
Persistent 403s mean the gamemaster secret in `sse-server/.env` does not match
`Config::$gameMasterSecret` in `config.php`.

### 2.4 Redis — the machine-readable last-run

```sh
docker compose exec -T redis redis-cli GET GAMEMASTER_LASTRUN
# 1789899893
date -d @$(docker compose exec -T redis redis-cli GET GAMEMASTER_LASTRUN) -Is
```

A unix timestamp within the last few seconds. This is the same fact as the status page's "Game
Processing" row without any HTML, so it is what a monitoring one-liner should read.

### 2.5 Everything is up

```sh
docker compose ps --format '{{.Service}}\t{{.State}}'
```

All of mariadb, webserver, php-fpm, sse, redis (and mailhog, phpmyadmin on the dev profile)
`running`, none `restarting`.

### Triage table

| Symptom | Almost certainly | Fix |
| --- | --- | --- |
| Pages serve, **nothing ever processes**; status shows minutes/hours since last process | The sse container is down or stuck, or `sse-server/.env` was never written, or the gamemaster secret is empty/mismatched | `docker compose logs --tail=50 sse`. Stuck on "Waiting for php-fpm to write sse-server/.env" → fix the entrypoint failure (§2.2) and `docker compose restart sse`. 403s → compare `grep -i gamemaster sse-server/.env` with `Config::$gameMasterSecret` in `config.php`; they must be identical and non-empty |
| Site **up and looks fine, but every game is gone** and the user list is 11 bots | The mariadb container was recreated without its volume and the watchdog silently reinstalled blank. The installer is idempotent and quiet — this never errors | **Stop creating anything** and restore: [§3](#3-backup-and-restore). Check first: `docker volume ls \| grep webdip` and `docker inspect webdiplomacy-db -f '{{json .Mounts}}'` should show `webdiplomacy_dbdata` on `/var/lib/mysql` |
| Nothing answers on 43000 at all; sse log full of `fetch failed` | webserver container exited. Historically the `sse` upstream (fixed, see [§7](#7-known-gotchas)); now more likely a bad nginx edit, or a host reboot (§1) | `docker compose ps -a`, `docker compose logs --tail=20 webserver`, then `docker compose exec webserver nginx -t` before `docker compose up -d` |
| `/events` returns **404 from nginx** instead of `403 Missing auth parameter` | The `/events` location is shadowed or was edited away | Check `location /events` in `phpdocker/nginx/nginx.conf`; `403 Missing auth parameter` with `X-Powered-By: Express` is the *correct* answer — it comes from the event server, so the proxy works |
| `/game/` blank or 404 | The React board was never built, or was cleaned | `docker compose --profile build up game-build` |
| Status page green, board never updates live in a browser | Event stream not reaching the browser; on plain HTTP over the LAN this is expected for **push notifications** but not for the stream | Open the browser console on a game page and look for the `/events` request; confirm `curl -s http://127.0.0.1:43000/events` says `Missing auth parameter` |

---

## 3. Backup and restore

### Where dumps live

`/home/normie/webdiplomacy-backups/`, mode 0700, dumps mode 0600 — they contain password hashes
and message text. **Deliberately outside the repo working tree** so a `git clean -xdf` cannot
take them.

Retention: the newest **30** dumps are kept, the rest pruned by the script itself. Scheduled with
**cron** (`cronie`), as user `normie`:

```
17 3 * * * /home/normie/Documents/Projects/webDiplomacy/local-setup/scripts/db-backup.sh >> /home/normie/webdiplomacy-backups/backup.log 2>&1
```

Check it with `crontab -l` and `tail /home/normie/webdiplomacy-backups/backup.log`.

### Take one now

```sh
local-setup/scripts/db-backup.sh
# 2026-09-20T14:27:25+04:00 backup ok: /home/normie/webdiplomacy-backups/webdiplomacy-20260920-142724.sql.gz (156K)
```

`mysqldump --single-transaction`, so a dump taken mid-adjudication is coherent. It writes to
`<name>.partial` and only moves it into place after confirming mysqldump's `Dump completed`
marker, so a truncated file is never mistaken for a backup and never what retention keeps.
`REPO_DIR`, `BACKUP_DIR`, `KEEP`, `DB_NAME` and `DB_ROOT_PASSWORD` are all overridable from the
environment.

### Restore — the procedure proven in issue 002

```sh
cd /home/normie/Documents/Projects/webDiplomacy
docker compose --profile core --profile dev up -d          # the database must be running
local-setup/scripts/db-restore.sh /home/normie/webdiplomacy-backups/webdiplomacy-<stamp>.sql.gz
```

The dumps are `--databases` dumps: they carry their own `CREATE DATABASE` and `DROP TABLE`, so
they overwrite in place and nothing has to be dropped first. The script also **flushes redis**
afterwards — without that the site keeps serving rows cached from before the restore, which looks
like the restore half-worked.

**If the volume itself was lost**, bring the stack up first and *wait for the watchdog to finish
reinstalling a blank database* before restoring — restoring into a half-installed database races
the installer:

```sh
tail -f gamemaster-entrypoint.txt        # wait for "DB created", then "READY", up to ~2 minutes
```

then run the restore command above.

### Verify a restore actually restored

The script prints user/game/version counts itself. Independently:

```sh
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  -e "SELECT COUNT(*) FROM wD_Users; SELECT COUNT(*) FROM wD_Games;
      SELECT name,value FROM wD_Misc WHERE name IN ('Version','LastProcessTime');"
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:43000/     # 200
```

Expect **21 rows in `wD_Users`** on this install: the ten human accounts (issue 003) plus the
eleven Guest/System/Bot rows the installer ships. Eleven means you are looking at a blank
reinstall, not a restore. `LastProcessTime` must be non-zero, and `status.php` should go green
within seconds.

The full drill — marker row, dump, `down`, `docker volume rm webdiplomacy_dbdata`, blank
reinstall, restore, marker back — was performed in issue 002. Repeat it after any change to the
scripts; a backup that has never been restored is not a backup.

---

## 4. Adding a variant

Condensed from issue 009. **Every variant is third-party PHP that this site executes.** Budget
twenty minutes per variant; when it expires, mark it `deferred` in the registry with one line
about what broke and move on.

1. **Security review.** Read the variant definition, its installer and every file in `classes/`.
   Reject anything that makes network calls, touches files outside its own directory, calls
   `eval`/`exec`/`system`/`shell_exec`/`passthru`, touches user or session tables, or executes an
   obfuscated string.
2. **Registry check.** Look up its `$id` and `$mapID` in `local-setup/variant-registry.md`. On a
   collision, renumber the newcomer into the **900 block** and record its original ID in Notes.
   Never displace an incumbent. Derivatives sharing a parent's `$mapID` (three Classic
   derivatives share map 1) are correct and must not be "fixed".
3. **Place the folder** under `variants/<Name>/` and create a writable `cache/` directory —
   it will not arrive with a download and the root `.gitignore` hides `/cache/`:
   `mkdir -p variants/<Name>/cache`
4. **Check resources.** It must ship `resources/style.css` (**not** `variant.css`) and dark-mode
   resources.
5. **Register the ID** in `config.php`, in `Config::$variants` (currently
   `array(1=>'Classic',2=>'World',9=>'AncMed',15=>'ClassicFvA',17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',23=>'ClassicGvI',91=>'ColdWar')`).
   First page load auto-installs it. `config.php` is gitignored — record the change in the issue
   and the registry, since git will not.
6. **Admin panel**, logged in as `admin`:
   - <http://localhost:43000/admincp.php?actionName=updateVariantInfo&variantID=#updateVariantInfo>
   - then <http://localhost:43000/admincp.php?actionName=wipeVariants#wipeVariants> to clear the
     caches.
   Enabling maintenance mode around this is optional on a quiet LAN box, and note that
   maintenance mode also stops processing.
7. **Acceptance checklist** — the five items in `SPEC.md`. Note that every harvested variant is
   **legacy-board only**; only Classic, ClassicFvA and ClassicGvI render on the React board, so do
   not spend triage time on that.
8. **Update the registry in the same change**, never afterwards.

Sanity checks afterwards:

```sh
grep -o '[0-9]\+=>' config.php | sort | uniq -d            # must print nothing (no duplicate IDs)
curl -s http://127.0.0.1:43000/gamecreate.php | grep -c '<option'
docker compose logs --tail=200 php-fpm | grep -iE 'fatal|deprecated'
```

---

## 5. Registry maintenance

`local-setup/variant-registry.md` is the authoritative list of which numeric ID belongs to which
variant **on this install**.

- **Add a row** to the in-tree table with: variant name, `$id`, `$mapID`, player count, source
  (including the original author — attribution is copied from the variant's own definition and
  must be preserved), status, and notes.
- **Statuses:** `enabled` (in the config array and installed) · `present` (folder in the tree, ID
  not in the config, so the site never loads it) · `porting` (in progress) · `playable` (a human
  has passed all five acceptance items) · `deferred` (failed triage). `enabled` means the config
  knows about it; `playable` means a human verified it. The gap between them is the work.
- **IDs are never reused**, enabled or not. A retired variant's row stays, because game rows in
  the database and the URLs people have bookmarked refer to variants by number; handing a number
  to a different map silently reinterprets old data.
- **The registry is updated in the same change as the install**, not afterwards. `config.php` is
  gitignored, so the registry is the only versioned record that an ID was taken; a registry
  updated "later" is a registry that is wrong for exactly as long as it takes to forget.

---

## 6. Accounts

Ten accounts exist: `admin` (userID 12, `User,Moderator,Admin`) and `player2` … `player10`
(userIDs 13–21). **Usernames, e-mails and passwords are in
`local-setup/credentials/accounts.md`; secrets are in `local-setup/credentials/secrets.md`.**
That directory is gitignored (`local-setup/.gitignore`) and no credential ever goes into a
tracked file, an issue, or a commit message. Verify with
`git check-ignore -v local-setup/credentials/accounts.md`.

### Registration is closed

Public signup is refused by nginx, before PHP sees it — there is no config flag for this, and the
site's panic switch would also stop game processing. In `phpdocker/nginx/nginx.conf`, marked
`LOCAL DEVIATION (issue 003)`:

```nginx
location = /register.php { return 404; }
location ^~ /register/   { return 404; }
```

**To reopen:** comment both blocks out, `docker compose exec webserver nginx -t`, then
`docker compose restart webserver`; `curl -o /dev/null -w '%{http_code}' http://localhost:43000/register.php`
should print 200.
**To close again:** uncomment, test, restart, and confirm 404 for `/register.php`, `/register/`
*and* `/register/processUserForm.php`.

### Adding an account

Three routes, all written up in full (with the exact commands) under **"Adding an eleventh
account"** in `local-setup/credentials/accounts.md`:

1. **Mint an e-mail token and POST `register.php`** — the route used for the nine players. One
   request, chosen username, password and e-mail, and it runs the real registration code so the
   account gets its welcome notice and user-options row. Needs the nginx block commented out for
   the duration.
2. **Admin CP** — `createUser`, then `changeEmail`, then `resetPass`. Works with registration
   still closed. Three round-trips, and it sets `email = username` and generates a random
   password you have to scrape off the response page, so you cannot choose the password.
   `admincp.php` → the user actions tab.
3. **Straight SQL** — insert with `md5(Config::$salt . md5($password))`. Last resort; skips the
   welcome notice and user-options row.

Whichever route: write the credential into `local-setup/credentials/accounts.md` in the same
sitting, and confirm the account can actually log in at <http://localhost:43000/logon.php>.

### Resetting a password

Admin CP's `resetPass` prints a freshly generated random password on the page — read it there;
there is no second chance. If you instead use the site's own reset-mail flow, read the mail in
mailhog at <http://localhost:43001>, and note the gotcha in [§7](#7-known-gotchas): generated
links hard-code `https://` and must be hand-edited to `http://`.

---

## 7. LAN address and access

**Current state: the site is localhost-only.** Every published port is bound to `127.0.0.1`:

```
webserver   127.0.0.1:43000->80/tcp        the site
mailhog     127.0.0.1:43001->8025/tcp      mail catcher
mariadb     127.0.0.1:43003->3306/tcp      database
redis       127.0.0.1:43005->6379/tcp      cache
sse         127.0.0.1:43006->43006/tcp     event server (also proxied at /events)
phpmyadmin  127.0.0.1:43009->80/tcp        database admin UI
```

Opening it to the house is **issue 006 and is not done yet**; when it is, this section gets the
host's actual LAN address. The shape of that change, so it is not re-derived:

- There is **no site-URL setting and no static-host setting** in this codebase. Everything — the
  React board's requests and the `/events` stream — is relative to whatever host the browser
  used. LAN access is a **port-binding change and nothing else**.
- Drop the `127.0.0.1:` prefix from the **webserver** binding only. Everything else stays on
  localhost. **Docker's firewall rules sit in front of the host firewall's**, so removing that
  prefix really does publish the port to the network whatever `ufw`/`firewalld` says — which is
  why exposing the database, whose password is the compose file's published default, is the worst
  single mistake available here.
- Change the database password off the compose default and update `config.php` in the same change.
- Give the host a stable address with a **DHCP reservation on the router** keyed to its MAC, in
  preference to a static address on the host.
- Restrict the host firewall so 43000 admits the LAN subnet only.
- Verify: `docker compose ps --format '{{.Service}}\t{{.Ports}}'` — webserver on `0.0.0.0`,
  every other published port on `127.0.0.1`. Then, from a second physical device, load
  `http://<host-lan-ip>:43000/`, log in, submit an order, and watch the board update without a
  reload.

**Two accepted losses on plain HTTP**, both known and both permanent without a certificate:

- **Web push notifications do not work.** They require a secure context and `http://<ip>` is not
  one. The `/events` stream itself is unaffected — that is what makes the board update live.
- **Registration and password-reset links come out with `https://`.** They take the host from the
  request but hard-code the scheme. Hand-edit the `https://` to `http://` when using one.

---

## 8. Known gotchas

Everything below has bitten this install at least once. In the order you are likely to meet them.

| Gotcha | Symptom | Fix |
| --- | --- | --- |
| **`$sseSecret` placeholder stays empty** — `install/gamemaster-entrypoint.sh` generates it only inside its `if [ ! -f config.php ]` branch, so writing `config.php` by hand skips it. The entrypoint then copies the empty value into `sse-server/.env` and only warns | Site healthy, but it hands out no SSE tokens and boards never update live | Generate **five** secrets by hand, not four: general secret, salt, JSON secret, gamemaster secret, SSE secret. Then `docker compose restart php-fpm sse` |
| **`$gameBackupDirectory = false` kills every gamemaster run** — `isset()` is true for `false`, so the guard in `gamemaster/backgroundTasks.php:380` passes and calls `mkdir('')` | Every run dies with `mkdir(): Invalid path` *before* `LastProcessTime` is written; games never process and the error is about `mkdir`, not backups | Set a real path. Here: `/tmp/webdiplomacy-gamebackups` — outside the webroot (these files contain message data) and inside the container (nothing here is worth keeping) |
| **nginx exited on a fresh `up`: `host not found in upstream "sse"`** — nginx resolved every upstream once at startup, and compose starts `webserver` before `sse` | The site did not answer at all and the sse log filled with `fetch failed`. Worked around with `docker compose start webserver` every time | **Fixed** (issue 011): `resolver 127.0.0.11 valid=10s` plus `set $sse_upstream …` / `proxy_pass $sse_upstream$request_uri` in `phpdocker/nginx/nginx.conf`, which defers the lookup to request time. If this ever recurs, `docker compose start webserver` is still the one-command workaround |
| **Redis holds rows from before a database restore** | Restore looks half-applied: old usernames, old game state | `db-restore.sh` flushes redis for you. Doing it by hand: `docker compose exec -T redis redis-cli FLUSHALL` |
| **The root `.gitignore` hides all `*.md`** as working notes, which hid this whole tracker | `git status` shows nothing after writing a document; `git add` refuses it | `local-setup/.gitignore` re-includes them with `!*.md`, `credentials/` still excluded. When a file you wrote will not stage, run `git check-ignore -v <path>` before assuming git is broken |
| **A blank reinstall is silent** — the installer is idempotent and checks whether the users table exists, so recreating mariadb without its volume brings back a healthy-looking empty site | Site fine, all games gone | Triage table in [§2](#2-health-check); restore per [§3](#3-backup-and-restore) |
| **Generated reset/registration links hard-code `https://`** | The link 404s or the browser refuses it | Hand-edit the scheme to `http://` |
| **Maintenance mode also stops game processing** — as does the panic switch | Turned on "just to be safe" during admin work, turns into stalled turns | Use it only for variant wipes, and turn it off immediately |

---

## 9. Git workflow

Two branches, two rules:

- **`master` tracks upstream `kestasjk/webDiplomacy` and is never committed to.** It must stay
  byte-identical so upstream merges cleanly forever.
- **`local` is what is deployed.** Everything in `local-setup/`, the `LOCAL DEVIATION` edits to
  `docker-compose.yml` and `phpdocker/nginx/nginx.conf`, and any ported variant lives here.

Remotes: `origin` = this fork (`LeonardoDaviti/webDiplomacy`), `upstream` = `kestasjk/webDiplomacy`.

Day-to-day work on `local`:

```sh
git checkout local
git add <only the files this change owns>          # never `git add -A`; config.php and
                                                   # credentials/ are ignored, keep it that way
git commit -m "Issue NNN: <what>"
git pull --rebase origin local                     # in case another machine/agent pushed
git push origin local
```

If a change is genuinely a fix to **upstream** code, make it on a topic branch off `master` and
offer it upstream — do not smuggle it into `local` only.

### Taking upstream changes

```sh
git fetch upstream
git checkout master
git merge --ff-only upstream/master                # must fast-forward; if it can't, master was
                                                   # committed to and needs resetting
git push origin master

git checkout local
git merge master                                   # expect conflicts only in the LOCAL DEVIATION
                                                   # hunks of docker-compose.yml and nginx.conf
```

Then, before declaring it done:

1. **Walk the database updates.** Upstream ships incremental SQL in `install/*/update.sql`
   (and the full installer in `install/FullInstall/fullInstall.sql`). Compare
   `SELECT value FROM wD_Misc WHERE name='Version'` with the versions shipped, and apply each
   update in order — **take a backup first** ([§3](#3-backup-and-restore)) and apply them one at
   a time so a failure names itself.
2. **Rebuild the board** if `game-src/` changed: `docker compose --profile build up game-build`.
3. **Restart the event server** if `sse-server/` changed: `docker compose restart sse`.
4. **Re-run the acceptance checks**: the full health check in [§2](#2-health-check), a login, a
   game board that renders, and an order submitted and processed. Re-run the five-item variant
   acceptance from `SPEC.md` for any variant whose files upstream touched.
5. **Re-check the deviations survived the merge**: registration still 404s, the named volume is
   still on mariadb, and the `/events` proxy still answers `403 Missing auth parameter`.

The full procedure — pre-flight, the schema walk, acceptance, rollback and cadence — is
[`UPGRADE.md`](UPGRADE.md) (issue 012). The above is the short form.

---

## Appendix — one-shot health check

Paste this whole block; everything should be green.

```sh
cd /home/normie/Documents/Projects/webDiplomacy
docker compose ps --format '{{.Service}}\t{{.State}}'
curl -s http://127.0.0.1:43000/gamemaster-entrypoint.txt | grep -c '^READY'    # 1
for u in / /game/ /logon.php /status.php; do
  printf '%-14s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:43000$u)"
done                                                                            # all 200
curl -s -o /dev/null -w 'register %{http_code}\n' http://127.0.0.1:43000/register.php   # 404
curl -s http://127.0.0.1:43000/events                                           # Missing auth parameter
date -d @$(docker compose exec -T redis redis-cli GET GAMEMASTER_LASTRUN) -Is   # within seconds
docker compose logs --tail=3 sse                                                # 60 runs, 0 failed
```
