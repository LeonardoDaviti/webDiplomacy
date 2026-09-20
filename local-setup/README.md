# local-setup

This folder is the spec and issue tracker for the **private LAN instance** of webDiplomacy
run by the fork owner. It contains no application code and is never loaded by PHP; it exists
so that work on the deployed instance is planned, tracked and reproducible.

Everything here describes the *deployment*, not upstream webDiplomacy. Upstream changes belong
upstream.

## Contents

| File | Purpose |
| --- | --- |
| `SPEC.md` | The corrected requirements spec for the LAN instance. Start here. |
| `RUNBOOK.md` | **Operations manual**: start/stop, health checks, backup and restore, adding a variant, accounts, LAN access, known gotchas, git workflow. Read this when something is broken or you have forgotten how. |
| `scripts/` | `db-backup.sh` and `db-restore.sh` (issue 002), used by the runbook. |
| `variant-registry.md` | Every variant known to this install: name, `$id`, `$mapID`, source, status. |
| `issues/NNN-slug.md` | One work item per file. |
| `credentials/` | Account passwords and secrets. **Gitignored** (see `.gitignore`). |

## Branch rule

**`master` is never committed to.** It tracks upstream `kestasjk/webDiplomacy` and must stay
byte-identical to it so upstream can be merged without conflict.

All deployment work — this folder, `config.php` changes that are committed, compose overrides,
ported variants — happens on the **`local`** branch. To take upstream changes:

```
git checkout master && git pull        # fast-forward only
git checkout local && git merge master
```

The full upgrade sequence — including the `install/*/update.sql` walk and the re-acceptance
checks — is in [`RUNBOOK.md`](RUNBOOK.md) § 9, *Git workflow*.

If a change is genuinely a fix to upstream code, make it on a topic branch off `master` and
offer it upstream; do not smuggle it into `local` only.

## Issue numbering

Issues are `issues/NNN-slug.md`, zero-padded to three digits, allocated in creation order and
never reused. The slug is lowercase, hyphen-separated, and describes the deliverable
(`003-accounts.md`, not `003-fix.md`). A new issue takes the next free number; renumbering an
existing issue is forbidden because other issues reference it by number.

Each issue file begins with YAML front matter:

```yaml
---
id: 003
title: Create the ten accounts and block public registration
label: ready-for-agent
phase: P1
depends-on: [001]
---
```

and contains, at minimum, a **Done when** section (checkable conditions) and a
**Verification** section (the exact command or URL that proves it).

## Label vocabulary

Exactly one label per issue, from this list:

| Label | Meaning |
| --- | --- |
| `ready-for-agent` | Fully specified. An agent may pick it up and do the work unsupervised. |
| `needs-human` | Requires a decision, a judgement call, physical access, or a second device. An agent may prepare and report, but must stop before the human step. |
| `blocked` | Cannot start until a named dependency in `depends-on` is `done`. Whoever closes the dependency flips this to `ready-for-agent` or `needs-human`. |
| `done` | Finished and verified. The verification output is pasted into the issue. |

An issue may be *re-opened* by flipping `done` back to `ready-for-agent` with a note saying why.

## Working rule for agents

1. Read `SPEC.md` first, then the issue.
2. Do not modify files outside the deliverable the issue names.
3. Do not commit to `master`, ever.
4. When finished, edit the issue: set `label: done`, tick the Done-when boxes, paste the
   verification output.
5. If a fact in `SPEC.md` turns out to be wrong, correct `SPEC.md` in the same change and say
   so in the issue. The spec is the source of truth and must not be allowed to rot.
