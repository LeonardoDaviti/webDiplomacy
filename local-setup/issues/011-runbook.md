---
id: 011
title: Write the operations runbook
label: ready-for-agent
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

- [ ] `local-setup/RUNBOOK.md` exists with all seven sections.
- [ ] Every command in it has been run and its real output pasted or paraphrased accurately.
- [ ] The two failure modes above are documented with their diagnosis.
- [ ] The restore procedure matches what was actually done in issue 002.
- [ ] Someone other than the author could follow it without asking a question.

## Verification

```
test -f local-setup/RUNBOOK.md && grep -c '^## ' local-setup/RUNBOOK.md   # >= 7
```

Real verification: follow the restart section from a cold stack and the health-check section,
and confirm the documented output is what you actually get.
