---
id: 001
title: Bring the stack up on localhost and make it trustworthy
label: done
phase: P1
depends-on: []
---

# 001 — Bring the stack up on localhost

Get upstream 1.83 running on the host, with every secret set and the gamemaster demonstrably
processing. Nothing else in P1 can be verified until this is true.

## Context

- Web tier is nginx + php-fpm; there is nothing to install on the host but Docker. Composer runs
  inside the PHP container from its entrypoint.
- The gamemaster is **not** a host cron job: the event-server container calls the gamemaster
  endpoint in a ~1s loop. If that container is unhealthy, games silently never process.
- The event server's environment file is regenerated from the site config on every start, so the
  gamemaster secret must exist in the config *before* the loop can authenticate.

## Steps

1. Confirm the working branch is `local` (`git branch --show-current`). If not, stop.
2. Copy the sample config to `config.php` if it does not exist. Do not overwrite an existing one.
3. Set all four secrets in the config — the general secret, the salt, the JSON secret and the
   gamemaster secret. Generate each independently; do not reuse one value across fields. The
   entrypoint fills only the event-server secret, so an unset gamemaster secret is the single
   most common cause of "games never process".
4. Set the mailer's debug flag to true so message bodies print to the page instead of being
   sent. There is no outbound mail on this install.
5. Bring up the core and dev profiles:
   `docker compose --profile core --profile dev up -d`
6. Build the React board once — it is not in git and is missing from a fresh checkout:
   `docker compose --profile build up game-build`
7. Watch the entrypoint complete and the gamemaster loop start.

## Done when

- [x] Branch is `local` and `master` has no new commits.
- [x] `config.php` exists with all four secrets set to distinct non-empty values, and the
      mailer debug flag true.
- [x] The core and dev profile containers are all up and none is restarting.
- [x] The entrypoint log ends with `READY - webDiplomacy system initialized`.
- [x] The event server's logs show repeated gamemaster calls succeeding (not 403s — a 403 means
      the secret mismatched).
- [x] The site's status page reports a gamemaster last-run time within the last minute.
- [x] The React board is built and the front page renders.
- [x] `git status` shows no modification to any file that existed before this issue, other than
      `config.php` if it is tracked-ignored.

## Verification

```
docker compose ps
curl -s http://127.0.0.1:43000/gamemaster-entrypoint.txt | tail -5
docker compose logs --tail=50 sse
curl -s http://127.0.0.1:43000/status.php | head -40
```

Success markers, in order: every service `running`; the entrypoint tail containing
`READY - webDiplomacy system initialized`; the sse log showing gamemaster iterations; the status
page showing a recent gamemaster last-run.

Then open <http://127.0.0.1:43000/> in a browser and confirm the front page renders.

## Notes / gotchas

- If the status page shows no last-run but the containers are up, check the event server's
  environment file was written — it waits for the PHP entrypoint and will sit in a
  "waiting for .env" loop forever if the entrypoint failed.
- If the entrypoint log stops before the READY line, read the whole file: it prints the failing
  step. A Composer failure there is the usual cause and is not a host problem.
- Leave the port bindings alone in this issue. LAN exposure is issue 006 and must not happen
  before registration is blocked (003).

## Status — done 2026-09-20

The stack is up at <http://localhost:43000/> and the gamemaster is processing: 60 runs a minute,
0 failed, and the status page reports "0 minutes, 1 seconds since last process". Every health
check on `status.php` is green except two that cannot be anything else on a fresh install —
"Game Backup Archived" (nothing ships backups offsite here) and "SSE Server Last Client Connect"
(no browser has opened the board yet).

Container states after the run: `mariadb`, `webserver`, `php-fpm`, `sse`, `redis`, `mailhog`,
`phpmyadmin` all running, none restarting; `game-build` exited 0, which is what it is for.

Four things were not as the steps above assumed. Each is recorded because each will recur on the
next fresh checkout.

### The entrypoint does not fill the SSE secret into a config you wrote yourself

`install/gamemaster-entrypoint.sh` generates `$sseSecret` inside its `if [ ! -f config.php ]`
branch, together with the `cp config.sample.php config.php`. Write the config by hand — which
this issue's step 2 does — and that branch never runs, so the placeholder stays empty and the
site hands out no SSE tokens. The entrypoint then reads the empty value straight into
`sse-server/.env` and warns about it, but the site is otherwise healthy, so it is easy to miss.
All five secrets are therefore generated here, not four.

### nginx exits on a fresh `up`, before anything else has gone wrong

`phpdocker/nginx/nginx.conf` proxies `/events` to an upstream named `sse`, and nginx resolves
every upstream at startup and refuses to start if one is unknown. Compose starts `webserver`
before `sse` — it has to, because `sse` declares `depends_on: webserver` — so on the very first
`up` the name does not resolve yet and the container dies with

```
[emerg] host not found in upstream "sse" in /etc/nginx/conf.d/default.conf:23
```

Everything else comes up fine, so the symptom is a site that does not answer at all and a
gamemaster loop logging `fetch failed`. `docker compose start webserver` fixes it permanently;
the name resolves on every start after the first. The clean fix is an nginx `resolver` with the
upstream in a variable, which is left alone here — the dependency cannot simply be reversed
without making a cycle.

### `$gameBackupDirectory = false` kills every gamemaster run

`isset()` is true for a property whose value is `false`, so the guard in
`gamemaster/backgroundTasks.php:380`

```php
if( isset(Config::$gameBackupDirectory) )
    if( !is_dir(Config::$gameBackupDirectory) )
        mkdir(Config::$gameBackupDirectory, 0700, true);
```

passes with the sample config's `false` and calls `mkdir('')`. That raises `mkdir(): Invalid
path`, which the error handler turns into a fatal page, and the run dies *before*
`$Misc->LastProcessTime` is written. The result is the loop failing identically once a second
with games never processing, and the message in the log is about `mkdir`, not about backups, so
it does not obviously point at the config. `config.php` now sets a real path,
`/tmp/webdiplomacy-gamebackups` — outside the webroot, because these files contain message
data, and inside the container, because nothing here is worth keeping.

### An account had to be created before anything could process

A fresh database has `wD_Misc.LastProcessTime = 0` and `gamemaster.php` refuses to process until
it is set. The only supported way to set it is to visit
`gamemaster.php?gameMasterSecret=...` **while logged in as a non-moderator user**, which the
same branch uses to promote that account to admin. So the site admin account, `admin`, was
registered here rather than in 003. Its password and the recipe for minting further accounts
without the anti-bot puzzle are in `local-setup/credentials/secrets.md`. Issue 003 still owns the
player accounts and closing registration.

### Also

The repository's root `.gitignore` hides all `*.md` as working notes, which hid this tracker.
`local-setup/.gitignore` now re-includes them, with `credentials/` still excluded.
