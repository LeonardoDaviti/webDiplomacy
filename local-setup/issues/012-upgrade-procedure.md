---
id: 012
title: Write the upgrade procedure
label: ready-for-agent
phase: P4
depends-on: [002, 011]
---

# 012 — Upgrade procedure

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

- [ ] The procedure exists, covering pre-flight, upgrade, post-flight, rollback and variant
      fallout.
- [ ] It names the version-mismatch bricking behaviour explicitly as the reason for the
      sequence.
- [ ] It requires and describes a verified backup before any schema change.
- [ ] It requires DATC batch tests against a recorded baseline afterwards.
- [ ] A DATC **baseline has actually been recorded** on the current install, so there is
      something to compare against.
- [ ] It is linked from the runbook.

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

## Notes / gotchas

- DATC covers **Classic rules only**. It will not catch a variant-specific adjudication
  regression; that is what the per-variant acceptance checklist is for. Say so in the document
  so nobody trusts a green DATC run too far.
