---
id: 001
title: Bring the stack up on localhost and make it trustworthy
label: ready-for-agent
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

- [ ] Branch is `local` and `master` has no new commits.
- [ ] `config.php` exists with all four secrets set to distinct non-empty values, and the
      mailer debug flag true.
- [ ] The core and dev profile containers are all up and none is restarting.
- [ ] The entrypoint log ends with `READY - webDiplomacy system initialized`.
- [ ] The event server's logs show repeated gamemaster calls succeeding (not 403s — a 403 means
      the secret mismatched).
- [ ] The site's status page reports a gamemaster last-run time within the last minute.
- [ ] The React board is built and the front page renders.
- [ ] `git status` shows no modification to any file that existed before this issue, other than
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
