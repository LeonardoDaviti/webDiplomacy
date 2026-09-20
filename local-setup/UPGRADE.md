# UPGRADE — taking an upstream change onto this fork

Upgrading this instance is **a planned outage, never a casual `git pull`**. Read the whole
document before starting; the ordering is the point.

This is the long form of [`RUNBOOK.md` §9, *Git workflow*](RUNBOOK.md#9-git-workflow), which
stays the two-minute version. Operational detail it already covers — health checks, backup and
restore, the variant pipeline — is referenced rather than repeated.

| | |
| --- | --- |
| Repo / compose directory | `/home/normie/Documents/Projects/webDiplomacy` |
| Site | <http://localhost:43000/> |
| Backups | `/home/normie/webdiplomacy-backups/` |
| Branches | `master` = upstream mirror, never committed to · `local` = what is deployed |
| Remotes | `origin` = `LeonardoDaviti/webDiplomacy` · `upstream` = `kestasjk/webDiplomacy` |

---

## 0. The fact that dictates the whole sequence

`header.php` ends with:

```php
if ( $Misc->Version != VERSION )
{
	require_once(l_r('install/install.php'));
}
```

and `install/install.php` does nothing but `libHTML::error(...)` — *"Database version X and code
version Y don't match … Please wait while the admin runs update.sql"*. `header.php` is included
by every page, so **the moment the code's `VERSION` constant and `wD_Misc.Version` disagree, every
page on the site is a single error message.** Not degraded: gone. That includes the admin CP, so
you cannot fix it through the site.

That is a safety feature — it stops a half-upgraded site corrupting live games — but it means the
code and the schema must move together, in one window, with the site expected to be down in
between.

Today: `global/definitions.php` has `define("VERSION", 183);` and the database must hold `183`.

### The entrypoint does **not** rescue you

`install/gamemaster-entrypoint.sh` (the php-fpm entrypoint) **never applies `install/*/update.sql`.
It has no version logic at all.** Read it and it is unambiguous: its only database branch is

```sh
if dbInstalled; then echo "DB installed"; else installDB; fi
```

where `dbInstalled()` is `SHOW TABLES | grep -q 'w[Dd]_[Uu]ser'` and `installDB()` loads
`install/FullInstall/fullInstall.sql` + `install/createBotAccounts.sql` into an **empty** database.
A database that exists and has a `wD_Users` table is considered done, whatever version it records.
The same test is what the one-minute watchdog loop at the bottom of the script re-runs forever.

So: restarting the stack after pulling new code **does not migrate anything**. It leaves a bricked
site looking exactly like a bricked site. Applying the update scripts is a manual step in this
procedure and nowhere else.

(`fullInstall.sql` is not an upgrade path either — it is the blank-install schema. Never run it
against a database with games in it.)

---

## 1. Pre-flight

Nothing here is optional. Schema scripts are not reversible; the backup is the only rollback.

### 1.1 Tell the other player, and pick a quiet moment

Check no game is mid-adjudication and no phase deadline is minutes away:

```sh
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  -e "SELECT id,name,phase,turn,FROM_UNIXTIME(processTime) AS processAt
      FROM wD_Games WHERE phase <> 'Finished' ORDER BY processTime;"
```

Then stop game processing for the duration. The blunt, reliable way is to stop the container that
drives the gamemaster:

```sh
docker compose stop sse          # the gamemaster loop lives here, not in php-fpm
```

Maintenance mode also stops processing, but it is set through the admin CP — which is one of the
pages that stops existing the instant the versions diverge. Stop `sse` instead.

### 1.2 Record exactly where you are

Paste all of this into the upgrade note; it is what rollback is written against.

```sh
cd /home/normie/Documents/Projects/webDiplomacy
git rev-parse --abbrev-ref HEAD                 # must say: local
git rev-parse HEAD                              # PREVIOUS LOCAL SHA — rollback target
git rev-parse master                            # the upstream SHA local was last merged from
git merge-base local master                     # same value if the last merge was clean
git log --oneline -1 upstream/master            # (after a fetch) where you are going
```

`master` on this fork is a byte-identical mirror of `upstream/master`, so `git rev-parse master`
*is* "the upstream SHA we last took". If `git merge-base local master` is **older** than
`git rev-parse master`, a previous upgrade pulled `master` forward but never merged it into
`local` — sort that out before adding another hop.

Record the two version numbers as well:

```sh
grep -n 'define("VERSION"' global/definitions.php
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  -e "SELECT name,value FROM wD_Misc WHERE name='Version';"
```

They must **agree before you start**. If they already disagree, you are not upgrading, you are
recovering — go to [§7](#7-rollback).

### 1.3 Back up, and verify the backup

```sh
local-setup/scripts/db-backup.sh
# 2026-09-20T14:27:25+04:00 backup ok: /home/normie/webdiplomacy-backups/webdiplomacy-<stamp>.sql.gz (156K)
```

The script already refuses to keep a dump without mysqldump's `Dump completed` marker, so "backup
ok" means the file is complete. Write the filename into the upgrade note. Confirm it is really
there and non-trivial:

```sh
ls -l /home/normie/webdiplomacy-backups/webdiplomacy-<stamp>.sql.gz
gzip -dc /home/normie/webdiplomacy-backups/webdiplomacy-<stamp>.sql.gz | grep -c 'INSERT INTO `wD_Games`'
```

A backup that has never been restored is not a backup — see the drill in
[`RUNBOOK.md` §3](RUNBOOK.md#3-backup-and-restore). Re-run that drill after any change to the
scripts, not during an upgrade.

### 1.4 Note the installed variants

`config.php` is **gitignored**. No merge will touch it, and nothing in git records which variant
IDs this install has enabled — so if the file is ever lost or rewritten, this note is the record.

```sh
grep -n 'public static \$variants' config.php
```

At the time of writing:

```php
public static $variants=array(1=>'Classic',2=>'World',9=>'AncMed',15=>'ClassicFvA',
    17=>'ClassicChaos',19=>'Modern2',20=>'Empire4',23=>'ClassicGvI',91=>'ColdWar');
```

Cross-check against `local-setup/variant-registry.md`, which is the versioned record of which
number belongs to which map. If the two disagree, fix the registry **now**, before the upgrade
makes it hard to tell what changed.

Also note the other gitignored, un-mergeable things so you notice if they go missing:
`config.php`, `sse-server/.env` (rewritten by the entrypoint on every start), the built `/game/`
directory, `vendor/`, every variant `cache/`.

### 1.5 Pre-flight checklist

- [ ] Other player told; no phase about to process.
- [ ] `docker compose stop sse` — processing stopped.
- [ ] Previous `local` SHA, `master` SHA and target upstream SHA recorded.
- [ ] `VERSION` and `wD_Misc.Version` recorded **and equal**.
- [ ] Fresh backup taken, filename recorded, `backup ok` seen.
- [ ] `Config::$variants` copied into the upgrade note and reconciled with the registry.

---

## 2. Git: take the upstream change

Never merge `local` into `master`. Never commit to `master`. Both rules exist so that
`--ff-only` below always works.

```sh
git fetch upstream

git checkout master
git merge --ff-only upstream/master      # MUST fast-forward
git push origin master

git checkout local
git merge master
```

If `git merge --ff-only upstream/master` **refuses**, `master` has been committed to (or a merge
landed there). Do not paper over it with a merge commit — find the stray commit
(`git log --oneline upstream/master..master`), move it to a topic branch or to `local`, then
`git reset --hard upstream/master` on `master`.

Before merging into `local`, skim what is actually coming:

```sh
git log --oneline master ^local                          # commits being taken
git diff --stat local master                             # files being changed
git diff --name-only local master -- install/            # new schema steps (see §3)
git diff --name-only local master -- game-src/ phpdocker/ sse-server/ variants/
```

That last line is the whole of [§4](#4-rebuilds-and-restarts) decided in one command.

### 2.1 Where conflicts will be

The deviation surface is deliberately tiny. Conflicts should only ever appear in the two files
below; `local-setup/` is our own directory and **never conflicts** because upstream has no such
path.

| Path | Why it conflicts | Resolution |
| --- | --- | --- |
| `docker-compose.yml` | Upstream edits the same service blocks we changed for LAN access and DB persistence | Keep **both**: take upstream's new service/image/env lines, re-apply every `LOCAL DEVIATION` hunk from §2.2 |
| `phpdocker/nginx/nginx.conf` | Upstream edits locations/upstreams around our blocks | Same: upstream's new locations plus our `resolver`/`set $sse_upstream` and the two `register` 404 blocks |
| `config.php` | **Cannot conflict — gitignored, not tracked.** But upstream may add new `Config::` settings that `config.sample.php` gains and `config.php` lacks | `diff config.php config.sample.php` after the merge and hand-add any new setting |
| ported variants under `variants/` | Only if upstream ships a variant of the same name | Registry rules in [`RUNBOOK.md` §5](RUNBOOK.md#5-registry-maintenance): never displace an incumbent ID |

If a conflict turns up anywhere else, **stop and read it**. It means this fork has deviated
somewhere that is not written down, and this table needs a new row in the same change.

### 2.2 The LOCAL DEVIATION markers currently in the tree

Every intentional edit to an upstream file carries a `LOCAL DEVIATION (issue NNN)` comment, so a
merge conflict tells you why the hunk exists. Regenerate this list before any upgrade:

```sh
grep -rn "LOCAL DEVIATION" --include=*.yml --include=*.conf --include=*.php .
```

As of 2026-09-20 that is **nine markers in two files**:

**`docker-compose.yml`** (7)

| Line | Issue | What it is |
| ---: | --- | --- |
| 35 | 006 | Header note: port 43000 is bound to `0.0.0.0` so the site is reachable from the LAN |
| 93 | 006/011 | mariadb `restart: always` left as upstream's — already survives a host reboot |
| 106 | 002 | mariadb data directory on the **named volume** `webdiplomacy_dbdata` (upstream keeps nothing) |
| 140 | 006 | webserver: the `127.0.0.1:` prefix dropped **here and only here**, publishing 43000 to the LAN |
| 147 | 006/011 | webserver `restart:` policy, so the stack returns after a host reboot |
| 195 | 006/011 | php-fpm `restart:` policy, same reason |
| 282 | 002 | The named-volume declaration itself, at the bottom of the file |

**`phpdocker/nginx/nginx.conf`** (2)

| Line | Issue | What it is |
| ---: | --- | --- |
| 21 | 011 | `resolver 127.0.0.11 valid=10s` + `set $sse_upstream …` / `proxy_pass $sse_upstream$request_uri`, so nginx resolves `sse` at request time instead of dying at startup when `sse` isn't up yet |
| 62 | 003 | Public registration closed: `location = /register.php { return 404; }` and `location ^~ /register/ { return 404; }` |

Line numbers drift; the marker text does not. After resolving the merge, re-run the grep and
confirm the count is still nine (or that a deliberate change explains the difference).

### 2.3 Do not do these

- **Do not track upstream `HEAD`.** Upgrade on a decision, not on a schedule of someone else's
  commits — see [§8](#8-cadence).
- **Do not `git add -A`.** `config.php` and `local-setup/credentials/` are ignored and must stay
  that way; add the files the change owns.
- **Do not push `local` yet.** Push after the upgrade is verified, so the pushed tip is a state
  that was known to work.

---

## 3. Schema: walk the update scripts

### 3.1 Find the steps

Upstream ships one directory per version hop, `install/<from>-<to>/`, each with a `readme.txt` and
usually an `update.sql`. List them in version order and compare with the database:

```sh
ls install/ | grep -E '^[0-9]+\.[0-9]+-[0-9]+\.[0-9]+$' | sort -V | tail -10
grep -n 'define("VERSION"' global/definitions.php
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  -e "SELECT value FROM wD_Misc WHERE name='Version';"
```

The version constant is an integer, the directories use the dotted form: `183` ↔ `1.83`. The
directory that *ends* at your stored version is the last one already applied; everything after it
in `sort -V` order is outstanding. Newly-arrived directories are also exactly what
`git diff --name-only local@{1} local -- install/` printed in §2.

Read each `readme.txt` first. Some hops ship notes, a PHP step or a manual action rather than (or
as well as) SQL; a directory with no `update.sql` still has to be read.

**Do not skip intermediates.** Each script assumes the one before it ran; they are not cumulative.
`install/1.82-1.83/update.sql` is typical — it sets the version *first*, then alters tables:

```sql
UPDATE `wD_Misc` SET `value` = '183' WHERE `name` = 'Version';
ALTER TABLE `wD_Games` ADD INDEX `playerTypesPhasePot` (`playerTypes`, `phase`, `pot`);
```

Note the consequence: **the version row moves before the schema does**, so a script that fails
half-way leaves the site *unbricked* and running against a schema that is only partly migrated.
That is worse than bricked. Which is why they are applied one at a time, with the output read.

### 3.2 Apply them, one at a time, in order

```sh
cd /home/normie/Documents/Projects/webDiplomacy

docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  < install/1.83-1.84/update.sql
echo "exit: $?"
```

- One command per directory. Read the exit status and any error **before** running the next.
- Any error at all: stop. Do not run the next script. Go to [§7](#7-rollback).
- Expected noise: `ALTER TABLE phpbb_posts_likes ...` fails if phpBB is not installed here — it
  is not. `IF NOT EXISTS` covers some of that; a bare "table doesn't exist" on a `phpbb_*` table
  is benign, anything about a `wD_*` table is not.
- `docker compose exec -T` (with `-T`) is required for stdin redirection.

### 3.3 Verify the two numbers match

```sh
grep -n 'define("VERSION"' global/definitions.php
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  -e "SELECT value FROM wD_Misc WHERE name='Version';"
```

`183` and `183`. Until these agree, [§0](#0-the-fact-that-dictates-the-whole-sequence) applies and
the site is a single error message on every URL. **That check is the go/no-go for the whole
upgrade.**

---

## 4. Rebuilds and restarts

Driven entirely by what §2's `git diff --name-only` showed changed. Do only what applies — each of
these is slow, and skipping an unnecessary one is fine.

| Changed | Do | Note |
| --- | --- | --- |
| `game-src/` | `docker compose --profile build up game-build` | The React board. `/game/` is **not in git** — if `game-src/` changed and you skip this, the board is stale or blank. Takes minutes; success is `game-build exited with code 0` |
| `phpdocker/` (Dockerfiles, php.ini) | `docker compose build php-fpm && docker compose up -d php-fpm` | Only the **image** needs rebuilding for Dockerfile/ini changes; `phpdocker/nginx/nginx.conf` is bind-mounted and only needs `docker compose exec webserver nginx -t && docker compose restart webserver` |
| `sse-server/` | `docker compose restart sse` | Re-runs `npm ci`, ~20 s. It is also the gamemaster driver, so this is what you restart in §6 anyway |
| `composer.json` / `.lock` | `docker compose restart php-fpm` | The entrypoint reinstalls dependencies itself, but **only if `vendor/` is absent**. To force it: `rm -rf vendor && docker compose restart php-fpm` |
| `variants/`, or shared map/adjudicator code | admin CP: `updateVariantInfo` then `wipeVariants` | See below |
| anything at all | flush redis | See below |

### Variant caches

If `variants/` or the map/adjudicator code changed, the cached variant data is stale — it holds
territory IDs and map metadata, and a stale cache against new code is how you get a variant that
renders wrong rather than one that errors. Logged in as `admin`:

1. <http://localhost:43000/admincp.php?actionName=updateVariantInfo&variantID=#updateVariantInfo>
2. <http://localhost:43000/admincp.php?actionName=wipeVariants#wipeVariants>

Both are admin CP pages, so they only exist **after** §3 made the versions match. The entrypoint's
`clearCaches()` also wipes `variants/*/cache/` on every php-fpm start, which covers the file
caches but not the database-side variant info — `updateVariantInfo` is the one that is not
automatic.

### Redis

The site caches database rows in redis. After a schema change, and after any restore, stale
entries survive and make the upgrade look half-applied (old counts, old usernames, old game
state):

```sh
docker compose exec -T redis redis-cli FLUSHALL
```

Harmless at any time — everything in there is a cache. Do it after §3 unconditionally.
`db-restore.sh` already does it for you; a plain upgrade does not.

### Bring it back up

```sh
docker compose --profile core --profile dev up -d
curl -s http://127.0.0.1:43000/gamemaster-entrypoint.txt | tail -4     # wait for READY
```

---

## 5. Acceptance

Nothing is "done" until all of this passes. In order, cheapest first.

### 5.1 Health check

The full [`RUNBOOK.md` §2](RUNBOOK.md#2-health-check), or its one-shot appendix block. In
particular: `status.php` green on Game Processing / Gamemaster Called / SSE Server Online, the
entrypoint log ending in `READY`, and `docker compose logs --tail=3 sse` showing ~60 gamemaster
runs and 0 failed. The two permanent warnings (Game Backup Archived, SSE Last Client Connect) are
not faults.

### 5.2 Smoke test

The curl recipes in **[`local-setup/issues/001-smoke-test.md`](issues/001-smoke-test.md)**, under
*"The curl recipes"*, are the scripted end-to-end path: log in per account with a cookie jar,
submit orders through `api.php?route=game/orders` with `ready: "Yes"`, watch the phase process,
read `cache/games/0/<id>/{game,status,history,messages}.json`, and open `/events` with the
`sseAuth` token from `game/playercontext`.

After an upgrade the useful subset is:

- `login admin '<password>'` returns the expected `profile.php?userID=12`.
- Create a throwaway Classic game with `phaseMinutes >= 60` (below 60 is a *live* game and will
  **not** start when it fills — see that issue's gotchas), fill it, and let it deal countries.
- One order set per country with `ready: "Yes"`, including **one bounce and one support**; confirm
  it processes within seconds and the bounce failed on both sides.
- Confirm the four JSON files update and their `version` changes on each process.

Erase the throwaway afterwards by leaving as the last member — that deletes it outright.

### 5.3 DATC batch — the adjudicator regression

Classic rules only, and the regression suite for the rules engine:

1. Turn on maintenance mode in the admin CP (the DATC page requires it). Remember it **also stops
   game processing**; turn it off the moment you are finished.
2. <http://localhost:43000/datc.php> → **Batch all**.
3. Compare the summary against the recorded baseline.

**Any newly failing case is a release blocker, not a curiosity.** A case that failed on the
baseline and still fails is a known upstream gap.

> **Baseline status:** not yet recorded on this install. Issue 012's last open item is to run
> Batch all on a green stack and paste the summary into that issue, so there is something to
> compare against. Until then an upgrade can only compare against the *expected* pass set, which
> is much weaker. Record it at the first opportunity.

**DATC covers Classic rules only.** A green DATC run says nothing whatsoever about a
variant-specific adjudication regression — that is what §5.4 is for, and the two do not substitute
for each other.

### 5.4 Per-variant acceptance

Run the five-item checklist from [`SPEC.md`](SPEC.md) (*Per-variant acceptance checklist*) on
**Classic plus one variant per wave**:

1. It appears on the New Game form with the right name and player count.
2. Its starting position matches the source image: same units, types, provinces, owners.
3. A spring move phase adjudicates, including at least one bounce and one support order.
4. A build phase resolves — both a build and a disband, in the right provinces.
5. Supply-centre counts after autumn are correct on the scoreboard **and** on the map.

One per wave, from [`issues/009-harvest-waves.md`](issues/009-harvest-waves.md):

| Wave | Pick | Why |
| --- | --- | --- |
| — | **Classic** (id 1) | Always. Shared adjudicator, shared map code, React board |
| 0 | one of FleetRome / CustomStart / BuildAnywhere / Colonial / Zeus5 | In-tree derivatives; exercises the config-only path |
| 1 | Westeros, or one two-player map (`ClassicFvA` / `ClassicGvI`) | The named wants; FvA and GvI also render on the React board |
| 2 | any one harvested vDiplomacy variant | Third-party PHP, the most likely thing a PHP or framework bump breaks |

Also spot-check anything whose files upstream actually touched
(`git diff --name-only local@{1} local -- variants/`).

### 5.5 Variant fallout

Ported variants are third-party PHP and are the **first** thing a PHP version or framework bump
breaks. After the checks above:

```sh
docker compose logs --tail=300 php-fpm | grep -iE 'fatal|deprecated|uncaught'
grep -o '[0-9]\+=>' config.php | sort | uniq -d       # must print nothing
curl -s http://127.0.0.1:43000/gamecreate.php | grep -c '<option'
```

Any variant that now fails moves to the **deferred** table in
[`variant-registry.md`](variant-registry.md) with one line saying what broke — in the same change,
not afterwards. It does not stay marked `playable` "for now". Expect most breakage to be PHP
deprecations inside the variant's own `classes/`.

### 5.6 Deviations survived the merge

```sh
grep -rn "LOCAL DEVIATION" --include=*.yml --include=*.conf --include=*.php .   # nine markers (§2.2)
curl -s -o /dev/null -w 'register %{http_code}\n' http://127.0.0.1:43000/register.php   # 404
curl -s -o /dev/null -w 'register/ %{http_code}\n' http://127.0.0.1:43000/register/processUserForm.php  # 404
curl -s http://127.0.0.1:43000/events                              # Missing auth parameter
docker inspect webdiplomacy-db -f '{{json .Mounts}}' | grep -o webdiplomacy_dbdata
docker compose ps --format '{{.Service}}\t{{.Ports}}'              # webserver 0.0.0.0, everything else 127.0.0.1
```

### 5.7 Restart processing, and only then push

```sh
docker compose start sse            # or: docker compose --profile core up -d
```

Confirm processing resumes (§5.1), then publish the verified state:

```sh
git add <only what this change owns>
git commit -m "Merge upstream <sha> (1.NN); schema to <version>"
git pull --rebase origin local
git push origin local
```

Tell the other player the site is back.

---

## 6. Post-upgrade checklist

- [ ] `wD_Misc.Version` == `VERSION`; pages render.
- [ ] Health check green: status page, `READY` marker, sse gamemaster runs.
- [ ] Board rebuilt if `game-src/` changed; php-fpm image rebuilt if `phpdocker/` changed;
      sse restarted if `sse-server/` changed.
- [ ] `updateVariantInfo` + `wipeVariants` run if `variants/` or map code changed.
- [ ] Redis flushed.
- [ ] Smoke test: login, game, orders, bounce, support, phase processed, JSON updated.
- [ ] DATC batch run and compared to baseline; no new failures.
- [ ] Five-item checklist passed on Classic + one variant per wave.
- [ ] `variant-registry.md` updated for anything that broke.
- [ ] Nine `LOCAL DEVIATION` markers present; registration 404s; `/events` answers.
- [ ] Processing restarted; `local` pushed.

---

## 7. Rollback

Schema scripts are **not reversible**. There is no down-migration and none will be written. The
honest procedure is: put the code back and put the database back.

### How you know it went wrong

- **Every page is the version-mismatch error.** Code moved, schema did not (or a script died
  half-way after setting the version row — see §3.1).
- **DATC regressions** against the baseline.
- A variant that rendered yesterday now throws a PHP fatal.
- The gamemaster stops: `status.php` shows minutes or hours since last process, or the sse log
  fills with failures.

### The procedure

```sh
cd /home/normie/Documents/Projects/webDiplomacy

# 1. Stop processing before anything else, so nothing writes during the rollback.
docker compose stop sse

# 2. Code back to the recorded pre-upgrade tip (§1.2).
git checkout local
git reset --hard <PREVIOUS LOCAL SHA>

# 3. Database back to the pre-upgrade backup (§1.3). This overwrites in place and
#    flushes redis for you.
local-setup/scripts/db-restore.sh /home/normie/webdiplomacy-backups/webdiplomacy-<stamp>.sql.gz

# 4. Belt and braces on the cache.
docker compose exec -T redis redis-cli FLUSHALL

# 5. Restart everything.
docker compose --profile core --profile dev up -d
docker compose restart php-fpm sse
curl -s http://127.0.0.1:43000/gamemaster-entrypoint.txt | tail -4      # READY
```

Then verify the versions agree **at the old number**, and re-run §5.1:

```sh
grep -n 'define("VERSION"' global/definitions.php
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  -e "SELECT value FROM wD_Misc WHERE name='Version';"
```

Notes:

- `git reset --hard` discards uncommitted work in the tree. That is intended here; there should be
  none mid-upgrade.
- If `master` was already pushed forward, **leave it**. It is a mirror; it being ahead of `local`
  is normal and harmless, and it is what you will merge from on the retry.
- If `game-src/` moved between the two SHAs, rebuild the board again after the reset — the built
  `/game/` is not in git and does not roll back with it.
- Everything between the backup and the rollback is lost: orders submitted, messages sent. That is
  the cost of the window, and the reason for §1.1.

---

## 8. Cadence

**Quarterly, or on a specific need. Never track HEAD.**

- **Quarterly** is enough for a two-player LAN box. Longer and the hop count grows, which means
  more schema steps in one window and a bigger diff to reconcile against the deviations.
- **A specific need** — a security fix, a bug that is actually biting, a feature that has been
  asked for — justifies an off-cycle upgrade. Read the upstream log first
  (`git log --oneline local..upstream/master`) and decide; do not upgrade because there are
  commits.
- **Never automatically.** There is no CI here, no staging copy, and the failure mode is the whole
  site down for both players. `gitpull.php` and the `production` branch in the root `CLAUDE.md`
  describe **upstream's** deploy automation for webdiplomacy.net; none of it runs on this install
  and none of it should.
- **Never during a live game's phase window**, and never when the person who would have to roll it
  back is not available.
- Re-read this document each time. If anything in it turned out to be wrong, fix it in the same
  sitting — a runbook that is wrong once is not trusted again.
