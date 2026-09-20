---
id: 012
title: Write the upgrade procedure
label: done
phase: P4
depends-on: [002, 011]
---

# 012 — Upgrade procedure

**Status: done.** The procedure is [`local-setup/UPGRADE.md`](../UPGRADE.md), linked from
`local-setup/README.md` and from `RUNBOOK.md` §9 (which stays the short form). The one carried-over
item, the DATC baseline, was recorded on 2026-09-20:
[`local-setup/datc-baseline-2026-09-20-c645b132.md`](../datc-baseline-2026-09-20-c645b132.md) —
**137 of 137 cases pass, no failures.** Nothing is outstanding.

Upgrading this site is **a planned outage, never a casual `git pull`**. Deliverable: an
`## Upgrading` section in `local-setup/RUNBOOK.md`, or a separate `local-setup/UPGRADING.md` —
pick one and link it from the runbook.

## The fact that makes this necessary

The site's header **bricks every page** when the schema version recorded in the database does
not match the code's version constant (currently **183**). That is a safety feature — it stops a
half-upgraded site corrupting live games — but it means that pulling new code without running
the matching schema upgrade takes the whole site down, for everyone, immediately.

Schema upgrades are per-version SQL scripts in the install tree, named for the version pair they
bridge. Upgrading across several versions means running several in order.

## Required content

1. **Pre-flight**
   - Take a fresh backup **first**, and verify it (issue 002's procedure). Non-negotiable.
   - Record the current code version and the version stored in the database.
   - Check no game is mid-adjudication; stop game processing for the duration.
   - Tell the other player the site is going down.
2. **The upgrade**
   - Merge upstream `master` into `local`. Never the reverse; never commit to `master`.
   - Identify every version step between the stored version and the new one.
   - Run each version's schema script **in order**. Do not skip intermediates.
   - Rebuild the React board if the board source changed — it is not in git.
   - Restart the stack; the PHP entrypoint reinstalls dependencies itself.
3. **Post-flight**
   - Confirm the stored version now matches the code's constant and pages render.
   - Health check: status page, entrypoint READY marker, event-server logs.
   - Run the **DATC batch tests** and compare against the recorded baseline. Any newly failing
     case is a release blocker, not a curiosity.
   - Spot-check one game of each of two or three variants: the acceptance checklist's
     adjudication items.
   - Restart game processing.
4. **Rollback**
   - The honest procedure: **restore the backup and revert the branch.** Schema scripts are not
     reversible. This is why the pre-flight backup is non-negotiable.
   - How to tell quickly that an upgrade went wrong: every page bricked with a version mismatch,
     or DATC regressions.
5. **Variant fallout**
   - Ported variants are third-party PHP and are the most likely thing to break on a PHP or
     framework bump. After an upgrade, re-check the variants in the registry and move any that
     now fail to the deferred table with a note.

## Done when

- [x] The procedure exists, covering pre-flight, upgrade, post-flight, rollback and variant
      fallout. — `local-setup/UPGRADE.md`, §§1–8.
- [x] It names the version-mismatch bricking behaviour explicitly as the reason for the
      sequence. — §0, quoting `header.php:197` and `install/install.php`, and stating that
      `install/gamemaster-entrypoint.sh` does **not** auto-apply `update.sql`.
- [x] It requires and describes a verified backup before any schema change. — §1.3, with the
      `Dump completed` marker check and a pre-flight checklist that gates on it.
- [x] It requires DATC batch tests against a recorded baseline afterwards. — §5.3, with the
      "any new failure is a release blocker" rule and the Classic-only caveat.
- [x] A DATC **baseline has actually been recorded** on the current install, so there is
      something to compare against. — `local-setup/datc-baseline-2026-09-20-c645b132.md`,
      137/137 passing, and §5.3 now links it.
- [x] It is linked from the runbook. — `README.md` contents table and `RUNBOOK.md` §9.

## The DATC baseline — recorded 2026-09-20

Run on `local` `c645b132` (upstream merge base `7be9bb05`), schema 183, maintenance mode on for
the duration and off afterwards with `status.php` green again.

**137 of 137 cases pass. 0 failures. No failing case IDs.** 6.A (12), 6.B (14), 6.C (7), 6.D (34),
6.E (15), 6.F (24), 6.G (18), wD.Intro (12), wD.Test (1) — every `wD_DATC` row ended `Passed`, and
each case's own page printed `<case> has passed!`. Retreat and build phases are outside the suite
(`datc/interactive.php`: "Non-Diplomacy phase DATC tests are currently unsupported").

Full record, including how to re-run it and the three traps:
[`local-setup/datc-baseline-2026-09-20-c645b132.md`](../datc-baseline-2026-09-20-c645b132.md).
`UPGRADE.md` §5.3 links it and no longer says the baseline is missing.

Three findings worth carrying, all in the baseline file:

- **`datc/maps/` ships unwritable by the web user**, which kills `map.php`'s json board data and
  leaves every case stuck on "Loading order..." with only a console `ReferenceError` to show for
  it. `chmod 777 datc/maps` — directory permissions are not tracked by git.
- **The suite needs a browser.** Each case is two requests and the first one's work is done by
  JavaScript (order entry → `ajax.php` → self-navigation to `datc.php?DATCResults=…`).
- **The second request carries no `testID`** and falls back to the lowest `NotPassed` case, so
  running out of order — or continuing past a failure — scores later cases against a stale one.
  Two attempts here reported 15–18 bogus failures before the ordering was corrected.

## Verification

```
grep -n "define(\"VERSION\"" global/definitions.php
docker compose exec mariadb mysql -uwebdiplomacy -p webdiplomacy \
  -e "SELECT * FROM wD_Misc WHERE name='Version';"
ls install/ | grep -E '^[0-9]+-[0-9]+$'
```

The first two must agree. The third shows the available upgrade scripts the procedure refers to.

Baseline the adjudicator by running the batch tests at <http://127.0.0.1:43000/datc.php> and
saving the result summary alongside the procedure.

## Findings recorded while writing this

- **`install/gamemaster-entrypoint.sh` never applies `install/*/update.sql`.** It has no version
  logic at all: its only database branch is `if dbInstalled; then ... else installDB; fi`, where
  `dbInstalled()` is `SHOW TABLES | grep -q 'w[Dd]_[Uu]ser'` and `installDB()` loads
  `install/FullInstall/fullInstall.sql` into an **empty** database. A database that has a
  `wD_Users` table is treated as done whatever version it records — so restarting the stack after
  pulling code migrates nothing and leaves a bricked site bricked. The same test drives the
  one-minute watchdog loop at the end of the script.
- **Update scripts set the version row first, then alter tables** (`install/1.82-1.83/update.sql`
  begins `UPDATE wD_Misc SET value='183'`). A script that fails half-way therefore leaves the site
  *unbricked* against a partly-migrated schema — worse than bricked. Hence one script at a time,
  exit status read each time.
- **The deviation surface is nine `LOCAL DEVIATION` markers in two files**: seven in
  `docker-compose.yml` (issues 002, 006, 011) and two in `phpdocker/nginx/nginx.conf` (issues 003,
  011). Tabulated in `UPGRADE.md` §2.2 with the regenerating grep.

## Notes / gotchas

- DATC covers **Classic rules only**. It will not catch a variant-specific adjudication
  regression; that is what the per-variant acceptance checklist is for. Say so in the document
  so nobody trusts a green DATC run too far.
