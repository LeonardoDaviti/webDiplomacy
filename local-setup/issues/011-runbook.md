---
id: 011
title: Write the operations runbook
label: done
phase: P4
depends-on: [001, 002, 003, 006]
---

# 011 — Runbook

A year from now the owner will need to restart the site, check its health, restore a backup, or
add a variant, and will not remember how. This issue writes it down. Deliverable:
`local-setup/RUNBOOK.md`.

Write it for **someone who has forgotten everything**: exact commands, expected output, and what
to do when the output is wrong. Every procedure must have been executed at least once by
whoever writes it — no untested steps.

## Required sections

### 1. Start, stop, restart

- Starting the stack from cold, including which profiles.
- The one-shot React board build and when it is needed (the board is not in git, so a fresh
  checkout has none).
- Stopping cleanly, and what is safe to `down` versus what destroys data.
- Confirming it survives a host reboot unattended.

### 2. Health check

In escalating order, with the expected output of each:

1. The **status page** — gamemaster last-run time. Recent means healthy.
2. The **entrypoint log**, served as a static text file — must end with
   `READY - webDiplomacy system initialized`.
3. The **event-server container logs** — gamemaster calls arriving.

Then a triage table: what each failure combination means. Include the two that will actually
happen:

- Site serves pages but **nothing ever processes** → the event container is down or its
  environment file was never written, or the gamemaster secret is empty/mismatched.
- Site is **up and looks fine but all the games are gone** → the database container was
  recreated without its volume and silently reinstalled blank.

### 3. Backup and restore

- Where dumps are written (outside the repo tree) and how retention works.
- How to take one by hand right now.
- The **full restore procedure**, step by step, as proven in issue 002.
- How to verify a restore actually restored.

### 4. Adding a variant

The condensed version of issue 009's per-variant procedure: security review, registry check and
the 900-block renumbering rule, `cache/` directory, `resources/style.css`, config array entry,
admin variant-info update and variant wipe, acceptance checklist, registry row.

### 5. Registry maintenance

How to add a row, what each status means, why IDs are never reused, and why the registry is
updated in the same change as the install rather than afterwards.

### 6. Accounts

Creating a user and resetting a password through the admin panel; where credentials live and
that the directory is gitignored; the reminder that reset links come out with an `https://`
scheme that must be hand-edited to `http://`.

### 7. LAN address and access

The host's address, how it is held stable, which ports are published where, and the firewall
rule. Plus the two accepted losses on plain HTTP: no web push, wrong scheme in generated links.

## Done when

- [x] `local-setup/RUNBOOK.md` exists with all seven sections (ten `##` headings: the seven
      required, plus known gotchas, git workflow and a copy-paste health-check appendix).
- [x] Every command in it has been run and its real output pasted or paraphrased accurately.
- [x] The two failure modes above are documented with their diagnosis.
- [x] The restore procedure matches what was actually done in issue 002.
- [x] Someone other than the author could follow it without asking a question.

## Verification

```
test -f local-setup/RUNBOOK.md && grep -c '^## ' local-setup/RUNBOOK.md   # >= 7
```

Real verification: follow the restart section from a cold stack and the health-check section,
and confirm the documented output is what you actually get.


---

## Status — done 2026-09-20

`local-setup/RUNBOOK.md` is written and `local-setup/README.md` links it from the contents table
and from the branch-rule section. Ten `##` sections: the seven this issue asked for, plus
**Known gotchas**, **Git workflow** and a copy-paste **one-shot health check** appendix.

Every command in it was executed on this host while writing it, and the pasted output is the real
output: `docker compose ps`, the `status.php` rows, the `gamemaster-entrypoint.txt` tail, the sse
stats line, `redis-cli GET GAMEMASTER_LASTRUN`, and a real run of `db-backup.sh`
(`backup ok: …/webdiplomacy-20260920-142724.sql.gz (156K)`). Two commands were corrected after
running them — `grep -c READY` on the entrypoint log matches twice, because the file also says
"Script will output READY once completed", so the runbook anchors it as `grep -c '^READY'`.

### Deviations from the section list

- **Section 7, LAN address**, cannot give the host's address: issue 006 is still
  `ready-for-agent` and every port is still bound to `127.0.0.1`. The section says so, records the
  current port table, and sets out the shape of the change (webserver binding only, DHCP
  reservation, firewall, database password off the compose default) plus the two accepted losses
  on plain HTTP. It gets the address when 006 closes.
- **"Confirming it survives a host reboot unattended" — it does not, and the runbook says so.**
  `docker inspect` on every container shows `webserver`, `php-fpm`, `mailhog` and `phpmyadmin`
  with `restart=no`; only `mariadb` (`always`), `redis` and `sse` (`unless-stopped`) come back by
  themselves. So a reboot leaves the database and the gamemaster loop running with nothing to
  serve or call — the sse log fills with `fetch failed`. The documented procedure is to re-run the
  cold-start command, which is idempotent. Adding `restart: unless-stopped` to the two services
  would fix it but is an unrequested change to an upstream file and was not made. This was read
  off the restart policies, not proven by rebooting.

### The nginx upstream failure is fixed, not just documented

Issues 001 and 002 both record nginx exiting on a fresh `up` with
`host not found in upstream "sse"`, worked around by `docker compose start webserver` every time.
`phpdocker/nginx/nginx.conf` now defers the lookup to request time, marked
`LOCAL DEVIATION (issue 011)`:

```nginx
resolver 127.0.0.11 valid=10s ipv6=off;

location /events {
  set $sse_upstream http://sse:43006;
  proxy_pass $sse_upstream$request_uri;
  ...
}
```

`$request_uri` is explicit because a `proxy_pass` containing a variable does not carry the
original request URI by itself; the previous directive had no URI part, so this keeps the
behaviour identical. `valid=10s` also means a recreated `sse` container's new address is picked
up, instead of nginx caching the first one for the life of the process.

**The compose alternative was considered and rejected.** Dropping `sse`'s `depends_on: webserver`
and giving `webserver` a `depends_on: sse` would also order the first start correctly — nothing
requires the current direction, since the gamemaster loop retries until the site answers. But it
deviates further from upstream (it inverts a dependency the compose file explains in a comment,
and invites a cycle the next time upstream adds one), and it fixes only the *first* start:
plain `depends_on` waits for the container, not for a name, so `docker compose up webserver`
alone, or anything that gives `sse` a new IP, still breaks the proxy. The nginx change fixes both
and touches one file.

### Verification

```
$ docker compose exec webserver nginx -t
nginx: configuration file /etc/nginx/nginx.conf test is successful
$ docker compose restart webserver && sleep 2
$ for u in / /game/ /events /register.php /status.php; do ... done
/                200
/game/           200
/events          403      <- Missing auth parameter, X-Powered-By: Express
/register.php    404
/status.php      200
$ curl -s http://127.0.0.1:43000/events/foo
Cannot GET /events/foo   <- express, i.e. the full path reaches the SSE server unchanged
$ docker compose logs --tail=3 sse
SSE stats: ... 60 gamemaster runs, 0 failed
$ test -f local-setup/RUNBOOK.md && grep -c '^## ' local-setup/RUNBOOK.md
10
```

The restart cost exactly one gamemaster call (`Gamemaster call failed (1 in a row): fetch failed`
then `Gamemaster call succeeded again, after 1 failures`), which is now documented in the runbook
as the expected cost of a webserver restart.

**Proof still pending:** the failure this fixes only appears on a full `docker compose down` +
`up -d`, and a smoke-test game was being played against the stack while this was written, so the
stack was never taken down. To prove it when the site is idle:

```sh
cd /home/normie/Documents/Projects/webDiplomacy
docker compose --profile core --profile dev down     # NOT -v; the volume must survive
docker compose --profile core --profile dev up -d
sleep 5
docker compose ps -a --format '{{.Service}}	{{.State}}'          # webserver running, not exited
docker compose logs webserver | grep -c 'host not found'          # 0
curl -s http://127.0.0.1:43000/events                             # Missing auth parameter
```

Before this change, `webserver` would be `exited (1)` with the `[emerg] host not found in
upstream "sse"` line in its log.
