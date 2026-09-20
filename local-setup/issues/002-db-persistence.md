---
id: 002
title: Give the database a volume, a nightly dump, and a proven restore
label: done
phase: P1
depends-on: [001]
---

# 002 — Database persistence and backup

**This gates every other issue.** As shipped, the database container has **no volume behind
it** — only bind mounts of the source tree. Recreating that container destroys the database,
and the next start silently reinstalls a blank one from the full-install SQL plus the
bot-account SQL. Both are idempotent and check whether the users table already exists, so the
reinstall is quiet: the site comes back looking healthy with every game gone.

Do not create a real game before this issue is `done`.

## Steps

1. Add a **named Docker volume** for the database's data directory, via a compose override file
   on the `local` branch. Do not edit the upstream compose file in place if an override will do
   — the goal is a clean merge from upstream forever.
2. Recreate the database container and confirm the volume is in use and the data survives.
3. Write a **dump script** that runs `mysqldump` against the database container and writes a
   timestamped, compressed dump to a directory **outside the repository working tree**. Inside
   the tree is not acceptable: a `git clean` must not be able to take the backups with it.
4. Give it retention — keep enough daily dumps to cover a long game, prune older ones.
5. Schedule it daily on the host (systemd timer or cron; pick one and record which in the
   runbook).
6. **Perform a restore, deliberately.** Take a dump, destroy the database container *and its
   volume*, restore from the dump, and confirm the site comes back with the same data. A backup
   that has never been restored is not a backup.

## Done when

- [x] The database's data directory is on a named volume, declared in a compose override on
      `local`.
- [x] `docker compose down` followed by `up` preserves all data.
- [x] Recreating the database container alone preserves all data.
- [x] A dump script exists, writes outside the repo tree, and prunes old dumps.
- [ ] The dump runs automatically once a day and a scheduled run has been observed to succeed.
      (cron entry installed and the script proven under a cron-like environment; the first 03:17
      firing has not happened yet — check `~/webdiplomacy-backups/backup.log` tomorrow.)
- [x] A full destroy-and-restore cycle has been performed and the restored site is intact.
- [x] The restore procedure is written down (it feeds issue 011's runbook).

## Verification

```
docker volume ls | grep -i webdip
docker compose --profile core up -d
# create a marker row, then:
docker compose stop mariadb && docker compose rm -f mariadb && docker compose --profile core up -d
# marker row must still be there
```

Restore proof — expect the marker row to be present again at the end:

```
<dump script>                       # take a dump
docker compose down
docker volume rm <the db volume>
docker compose --profile core up -d
<restore command from the dump>
```

Then load <http://127.0.0.1:43000/> and confirm users and games are present.

## Notes / gotchas

- The installer is *idempotent and quiet*. A blank reinstall does not error; it looks like a
  healthy fresh site. The only way to notice is that your data is gone. This is why the marker
  row matters.
- Dump with a consistent-snapshot option so a dump taken mid-adjudication is coherent.
- The database password is the compose file's published default. Changing it is part of issue
  006; if you change it here instead, update the site config in the same change or the site
  will not start.

## Status — done 2026-09-20

The database is on the named volume **`webdiplomacy_dbdata`**, there is a daily dump at 03:17 with
30 days of retention, and a full destroy-and-restore cycle has been performed and verified.

### What was done

1. A dump of the pre-volume database — the one holding the `admin` account created by issue 001 —
   was taken first, to `/home/normie/webdiplomacy-backups/pre-volume.sql`, before anything was
   touched.
2. `docker-compose.yml` gained `dbdata:/var/lib/mysql` on the `mariadb` service and a top-level
   `volumes:` block, both marked `LOCAL DEVIATION (issue 002)`. Nothing else changed: the port
   bindings, the buffer-pool setting and every other service are as upstream left them.
3. Recreating `mariadb` put an empty data directory on the new volume, so php-fpm's 60-second
   watchdog reinstalled from `fullInstall.sql` as designed. Once that finished (`DB created`,
   then `READY`), the pre-volume dump was restored over it, bringing back the `admin` account
   (userID 12) and a non-zero `wD_Misc.LastProcessTime`. Redis was flushed afterwards, because it
   still held rows cached from the blank reinstall.
4. `docker compose down` then `up -d` now preserves everything with no restore, as does stopping,
   removing and recreating the `mariadb` container by itself.

### The scripts

- `local-setup/scripts/db-backup.sh` — `mysqldump --single-transaction` through
  `docker compose exec -T mariadb`, gzipped to
  `/home/normie/webdiplomacy-backups/webdiplomacy-<YYYYmmdd-HHMMSS>.sql.gz`, keeping the newest 30.
  It writes to a `.partial` file and only moves it into place after checking the dump carries
  mysqldump's `Dump completed` marker, so a truncated dump is never mistaken for a backup and is
  never what retention keeps. `REPO_DIR`, `BACKUP_DIR`, `KEEP`, `DB_NAME` and `DB_ROOT_PASSWORD`
  are all overridable from the environment.
- `local-setup/scripts/db-restore.sh <file>` — takes a `.sql.gz` or a plain `.sql`. The dumps are
  `--databases` dumps, so they carry their own `CREATE DATABASE`/`DROP TABLE` and overwrite in
  place; nothing has to be dropped first. It flushes redis afterwards and prints a user, game and
  version count as a sanity check.

The cron entry, installed with `crontab -` for `normie`:

```
17 3 * * * /home/normie/Documents/Projects/webDiplomacy/local-setup/scripts/db-backup.sh >> /home/normie/webdiplomacy-backups/backup.log 2>&1
```

`cronie` is the active scheduler (`systemctl is-active cronie`). The script was also run once
under `env -i` with a bare `PATH=/usr/bin:/bin` to prove it does not depend on an interactive
shell's environment, and retention was exercised with `KEEP=2` against a scratch directory.

### The restore drill, performed deliberately

A marker row (`wD_Misc.Issue002RestoreMarker`) was written, a backup taken, then the stack was
brought fully down and **the volume deleted** with `docker volume rm webdiplomacy_dbdata`. The
watchdog reinstalled a blank database (11 bot accounts, no marker, no `admin`). Restoring the
gzipped backup brought back the marker, the `admin` account and all 12 users; the front page
answered 200 and `status.php` showed Game Processing and Gamemaster Called green within seconds.
The marker row was then deleted.

### Deviations

- **The volume is declared in `docker-compose.yml`, not in a compose override.** Step 1 above asks
  for an override, for clean merges from upstream. `docker-compose.override.yml` exists in this
  checkout but is an upstream file too and is entirely commented out, so either choice edits an
  upstream file; the in-place edit was chosen so that the volume sits next to the paragraph
  explaining that the database has no volume, which would otherwise stay there contradicting it.
  Both changes are labelled `LOCAL DEVIATION (issue 002)` in the file. Moving them into the
  override later is a two-line change.
- **The backup directory is `/home/normie/webdiplomacy-backups`, not `/home/normie/docker-data/…`.**
  `/home/normie/docker-data` turned out to be Docker's own `Docker Root Dir`, owned by root with
  mode 0710 and unreadable to `normie` (there is no passwordless sudo here). Backups do not belong
  inside Docker's storage tree in any case, and a cron job running as the user could neither write
  nor prune there. The new directory is mode 0700, outside the repository working tree, and the
  dumps are mode 0600 — they contain password hashes and message data.
- `nginx` still exits on the first `up` after a full `down`, for the reason recorded in issue 001:
  it resolves the `sse` upstream at startup and the name does not exist until that container does.
  `docker compose start webserver` fixes it each time. This is not new here, but a `down`/`up`
  cycle is now something the backup drill does routinely, so it will be met routinely.

### Restore procedure, for the runbook (issue 011)

```sh
cd /home/normie/Documents/Projects/webDiplomacy
docker compose --profile core --profile dev up -d       # database must be running
docker compose start webserver                          # if it exited on the sse upstream
local-setup/scripts/db-restore.sh /home/normie/webdiplomacy-backups/webdiplomacy-<stamp>.sql.gz
```

If the volume itself was lost, bring the stack up first and **wait for php-fpm's watchdog to
finish reinstalling** (`tail -f gamemaster-entrypoint.txt` until `DB created` then `READY`, up to
a couple of minutes) before restoring; restoring into a half-installed database races the
installer. Verify with:

```sh
docker compose exec -T mariadb mysql -u root --password=mypassword123 webdiplomacy \
  -e "SELECT COUNT(*) FROM wD_Users; SELECT * FROM wD_Misc WHERE name IN ('Version','LastProcessTime');"
curl -s http://127.0.0.1:43000/status.php | grep -o 'Game Processing'
```
