---
id: 002
title: Give the database a volume, a nightly dump, and a proven restore
label: ready-for-agent
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

- [ ] The database's data directory is on a named volume, declared in a compose override on
      `local`.
- [ ] `docker compose down` followed by `up` preserves all data.
- [ ] Recreating the database container alone preserves all data.
- [ ] A dump script exists, writes outside the repo tree, and prunes old dumps.
- [ ] The dump runs automatically once a day and a scheduled run has been observed to succeed.
- [ ] A full destroy-and-restore cycle has been performed and the restored site is intact.
- [ ] The restore procedure is written down (it feeds issue 011's runbook).

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
