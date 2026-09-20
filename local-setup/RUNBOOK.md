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
   collision, renumber the newcomer **downwards from 250** — *not* into the 900 block, which this
   schema cannot hold (see the gotcha table) — and record its original ID in Notes. Never displace
   an incumbent. Derivatives sharing a parent's `$mapID` (three Classic
   derivatives share map 1) are correct and must not be "fixed".
3. **Place the folder** under `variants/<Name>/` and create a writable `cache/` directory —
   it will not arrive with a download and the root `.gitignore` hides `/cache/`:
   `mkdir -p variants/<Name>/cache`
4. **Check resources.** It must ship `resources/style.css` (**not** `variant.css`) and dark-mode
   resources.
5. **Register the ID** in `config.php`, in `Config::$variants` — one hundred and eleven entries
   after issue 009 wave 4; the current line is recorded in `local-setup/variant-registry.md`,
   because `config.php` is gitignored and git will not record it. **Every ID must be ≤ 255**:
   `wD_Games.variantID` and `wD_Territories.mapID` are `tinyint unsigned` and silently clamp
   anything larger. Renumber a colliding port from **254 downwards** (250, 251 and 254 are taken;
   252 and 253 are reserved to deferred variants). **ID 57 must never be used** — core hard-codes
   `if($variantID != 57)` in five places and a variant registered as 57 never appears in any
   dropdown.
6. **Admin panel**, logged in as `admin`, in this order:
   - <http://localhost:43000/admincp.php?actionName=wipeVariants#wipeVariants> to cool the caches;
   - then <http://localhost:43000/admincp.php?actionName=updateVariantInfo&variantID=#updateVariantInfo>,
     which is what actually installs the map, because it is a normal page that reaches
     `libHTML::footer()`;
   - then `updateVariantInfo` **once more**, because `wD_VariantInfo` is written from the variant
     object and a run made while the map was still empty writes nothing.
   **Then count the rows** — see the gotcha table. Enabling maintenance mode around this is
   optional on a quiet LAN box, and note that maintenance mode also stops processing.
7. **Acceptance checklist** — the five items in `SPEC.md`. Note that every harvested variant is
   **legacy-board only**; only Classic, ClassicFvA and ClassicGvI render on the React board, so do
   not spend triage time on that.
8. **Update the registry in the same change**, never afterwards.

Sanity checks afterwards:

```sh
# no duplicate variant IDs (do NOT grep the whole file: the server-message and bot arrays match too)
python3 -c "import re;l=re.search(r'public static \\\$variants=array\\(.*?\\);',open('config.php').read(),re.S).group(0);i=re.findall(r\"(\\d+)=>'\",l);print(len(i),'variants','OK' if len(i)==len(set(i)) else 'DUPLICATES')"
# the dropdown must have exactly as many options as the config has variants (issue 009 wave 4).
# gamecreate.php needs a logged-in session, so use a cookie jar from a logon.php POST:
curl -s -b jar.txt http://127.0.0.1:43000/gamecreate.php \
  | grep -o '<option name="newGame\[variantID\]"' | wc -l        # must equal the count above
docker compose logs --tail=200 php-fpm | grep -iE 'fatal|deprecated'

# the map really installed: install.php's row count must equal the database's
docker compose exec -T mariadb mysql -u root --password=mypassword123 -N -e \
  "use webdiplomacy; select mapID,count(*),sum(supply='Yes') from wD_Territories where mapID=<id>;"
# NB: count only the $territoryRawData block — $bordersRawData rows also start with array('
python3 - <<'EOF'
import re
t=open('variants/<Name>/install.php',encoding='utf-8',errors='replace').read()
i=t.find('$territoryRawData=array(');j=t.find('$bordersRawData',i)
rows=re.findall(r"^\s*array\((.*?)\),?\s*$",t[i:j],re.M)
print(len(rows),'territories,',sum(1 for r in rows if "'Yes'" in r.split(',')[2]),'supply centres')
EOF
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

**The site is on the LAN at <http://10.13.101.254:43000/>** — bookmark that. `http://localhost:43000/`
still works on this host. Full write-up, including the human steps still outstanding, is in
`local-setup/issues/006-lan-access.md`.

| | |
|---|---|
| Host | `helicarrier`, interface `wlp194s0` (wifi), MAC `f8:3d:c6:b7:12:fc` |
| Address | `10.13.101.254/22` — **DHCP, not reserved**, so it can change |
| Subnet / gateway | `10.13.100.0/22` / `10.13.100.1` |
| Tailscale | `100.93.151.44` — works from anywhere, see below |

If the bookmark stops working, the address moved. Get the new one with:

```sh
ip -4 addr show wlp194s0 | awk '/inet /{print $2}'
```

### Which ports are open where

```
webserver   0.0.0.0:43000->80/tcp     the site        <- the only one on the network
mailhog     127.0.0.1:43001->8025/tcp mail catcher
mariadb     127.0.0.1:43003->3306/tcp database
redis       127.0.0.1:43005->6379/tcp cache
sse         127.0.0.1:43006->43006/tcp event server (also proxied at /events)
phpmyadmin  127.0.0.1:43009->80/tcp   database admin UI
```

Check it has not drifted — the first must show `0.0.0.0`, the rest `127.0.0.1`:

```sh
docker compose ps --format '{{.Service}}\t{{.Ports}}'
ss -ltnp | grep 43000
```

**Never drop the `127.0.0.1:` from any other binding.** Docker's firewall rules sit in front of
the host firewall's, so removing that prefix really does publish the port to the network whatever
`ufw` says — and the database password is still the compose file's published default
(`mypassword123`), so exposing 43003 is the worst single mistake available here.

### This is a coworking wifi, not a house LAN

The network is `D Block Workspace@stamba`, a shared /22 with room for ~1022 machines that are not
yours. Everything on it can reach the site. Registration is 404 (issue 003) and the secrets in
`config.php` are generated rather than sample values, so nobody gets an admin account for free —
but **the ten account passwords are short, known, and now travel over plain HTTP in front of
strangers.** Treat them as public and never reuse them.

Restricting the firewall to "the LAN subnet" does not help here, because the LAN subnet *is* the
untrusted population. It is still worth having for the next network this laptop joins; the rule
needs root and lives in Docker's `DOCKER-USER` chain, not in `ufw` (`ufw` rules do not apply to
published container ports). The exact commands are in issue 006.

**Prefer Tailscale for real use.** It is already running here and the owner's phone is already in
the tailnet: `http://100.93.151.44:43000/` is the same site over an encrypted link, reachable from
anywhere, with no strangers on the path.

### Two accepted losses on plain HTTP

Both known, both permanent without a certificate, and both apply to the Tailscale address too:

- **Web push notifications do not work.** They need a secure context and `http://<ip>` is not one.
  The `/events` stream is unaffected — that is what makes the board update live, and it is the
  part that matters.
- **Registration and password-reset links come out with `https://`.** They take the host from the
  request but hard-code the scheme. **Hand-edit the `https://` to `http://`** when using one.
  Registration is closed anyway, so in practice this only bites on a password reset.

### Surviving a reboot

`webserver`, `php-fpm`, `sse` and `redis` are `restart: unless-stopped` and `mariadb` is
`restart: always`, so the stack comes back by itself — this replaces the earlier note that it did
not. A full `down`/`up` was verified: all containers running, no `host not found in upstream "sse"`,
`READY` present, and the `admin` account and all 21 users still in the database.

`mailhog` and `phpmyadmin` are still `restart: no` and will not come back on their own; start them
with the cold-start command in section 1. Also confirm Docker itself starts at boot:

```sh
systemctl is-enabled docker
```

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
| **Maintenance mode also stops game processing** — as does the panic switch. The mechanism: `gamemaster.php` is called anonymously by the SSE server, and an anonymous request dies in `header.php:260` before it reaches any processing code, while still returning **HTTP 200** | Turned on "just to be safe" during admin work, turns into stalled turns — and `docker compose logs php-fpm` shows a healthy-looking stream of `"GET /gamemaster.php" 200`. Check `wD_Misc.LastProcessTime`, which stops advancing | Use it only for variant wipes, and turn it off immediately. If you must leave it on (`datc.php:31` needs it for the interactive DATC runner), an **admin's own** request to `gamemaster.php` still processes, because `header.php:244` takes the Admin branch first |
| **The error page hides the error** — `error_handler()` (`global/error.php:158`) sets `$DB = null` and then calls `libHTML::error()`, which calls `libHTML::gameNotifyBlock()` (`lib/html.php:830`), which dereferences `$DB` | Every triggered error, whatever it was, renders as the same `Uncaught Error: Call to a member function sql_tabl() on null in /application/lib/html.php:830` — in the browser **and** in the php-fpm log. The real message is lost | Do not debug the `sql_tabl()` fatal; it is never the cause. Read the page body instead — the original notice text is still printed above the stack trace (e.g. *"Server is in maintenance mode…"*) |
| **A variant can be registered, cached, in the dropdown, drawing a map — and have zero rows in `wD_Territories`** (issue 009 wave 3). The install only runs when `variants/<X>/cache/data.php` is absent (`lib/variant.php:150`), and the only `COMMIT` is in `close()` (`header.php:304`), reached from `libHTML::footer()`. `map.php` builds the variant, writes `data.php` and dies without committing; `admincp actionName=wipeVariants` cools *every* variant's cache at once while the SSE gamemaster driver hits the site once a second | Games start in Pre-game and never begin, or every board is empty. Nothing in any log | Drive the install with `updateVariantInfo` on a **cold** `data.php`, then **count the rows against `install.php`** instead of assuming. If it is zero, delete `data.php` and repeat |
| **Variant and map IDs above 255 are silently clamped** (issue 009 wave 3). `wD_Games.variantID` and `wD_Territories.mapID` are `tinyint(3) unsigned`; `wD_VariantInfo.mapID` is a `smallint`, so the mismatch does not announce itself | A variant registered as 900 installs its map as 255, and games created for it come back with `variantID` 255 | Keep every ID ≤ 255. Renumber colliding ports from **254 downwards**, not into the registry's old 900 block |
| **`admincp`'s `updateVariantInfo` does not escape the description** (issue 009 waves 1 and 3, `admin/adminActionsRestricted.php:1275`) | One apostrophe in a variant's `$description` and the action dies with a SQL syntax error; no `wD_VariantInfo` row is written and the variant has no entry on `variants.php` | Replace the apostrophe with a typographic one (U+2019) in the variant's own `variant.php`. The bug is in core `admin/` and is still there |
| **A game with inconsistent pause fields breaks `index.php` for every user** (issue 009 wave 3, `objects/game.php:432-444`) | *"Not-paused game process-time values incorrectly set."* on every page that lists that game, for everyone, not just its players | A paused game must have `processTime` NULL and `pauseTimeRemaining` set, a running game the reverse. Never set `processTime` by hand on a paused game — unpause with `admincp actionName=togglePause` first |
| **`processStatus='Crashed'` is sticky** | The gamemaster skips the game for ever after, silently | Set it back to `Not-processing` once the cause is fixed |
| **Sum-of-squares scoring divides by zero when nobody owns a supply centre** (issue 009 wave 3, `objects/scoringsystem.php:136`) | Every board load of the game is *"Division by zero"*. Hits `Imperium`, whose installer makes all 28 centres neutral | Create such games with any other pot type. Core bug; see `PLAYING.md` §B.1 |
| **A variant's own order class can reject an order silently** (issue 009 wave 3, e.g. Lepanto forbidding `Move` from four territories) | The order comes back with **no type**, the member never reaches `Completed`, `Game::needsProcess()` never fires, and the game sits in one phase for ever with nothing in any log | Re-read the board's order context after submitting and give anything typeless a `Hold` (or `Wait` in a Builds phase) |
| **Core hard-codes variant ID 57 out of every dropdown** (issue 009 wave 4). `if($variantID != 57)` appears twice in `locales/English/gamecreate.php`, three times in `locales/English/gamecreateSandbox.php` and once in `gamelistings.php:334` | A variant registered as 57 installs, row-counts correctly, draws on `map.php` and lists on `variants.php` — and is **invisible** in New Game, Start a Sandbox Game and the games-list variant filter. No error anywhere | Never use ID 57. `KnownWorld_901` was renumbered 57 → 250. Catch it by counting: the `<option>`s in the New Game variant select must equal `count(Config::$variants)` |
| **Forcing a phase that is not Ready NMRs everyone, draws the game and temp-bans the accounts** (issue 009 wave 4). Setting `wD_Games.processTime` into the past and calling `gamemaster.php` processes regardless of order status; `gamemaster/game.php:786` draws a game where no member is left `Playing`, and the reliability system writes `wD_Users.tempBan` with `tempBanReason='System'` | The game ends `Drawn` out of nowhere, and afterwards `board.php` shows those accounts **no Join button and no error** — only the banner *"You are blocked from joining, rejoining, or creating new games for 4 days"* (`lib/html.php:758-762`) | Only ever force `processTime` on a **Pre-game** phase, to end the join period. For every other phase, confirm `wD_Members.orderStatus LIKE '%Ready%'` for all members and then wait — the SSE gamemaster driver polls once a second and `needsProcess()` fires on its own. To clear a ban: `UPDATE wD_Users SET tempBan=NULL, tempBanReason=NULL WHERE tempBanReason='System';` |
| **`admincp actionName=cancelGame` refuses a `Finished` game** | Nothing happens and the game stays in the listings | `cancelGame` only handles `Diplomacy`/`Retreats`/`Builds`. A finished game has to be removed by hand: delete its rows from `wD_Orders`, `wD_Moves`, `wD_Units`, `wD_TerrStatus`, `wD_Members`, `wD_GameMessages` and `wD_Games` |
| **`wD_TerrStatus` has no row for a territory nobody has ever owned** | A query that finds neutral supply centres by joining `wD_TerrStatus` returns almost nothing, and any tooling built on it thinks the map is full | Find them from `wD_Territories` with a `LEFT JOIN wD_TerrStatus`, treating a missing row as unowned |

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
