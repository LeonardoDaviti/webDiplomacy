---
id: 007
title: Install the Westeros variant
label: ready-for-agent
phase: P3
depends-on: [001, 002]
---

# 007 — Westeros

A Game of Thrones map is one of the four things this whole project exists for. Source:
`mcoirad/gameofthrones-diplomacy`, a webDiplomacy variant package of the same shape as the ones
already in the tree.

## Security review — do this first, and do not skip it

A variant is **PHP that the site executes**. Installing one from the internet is running someone
else's code on the machine that holds the game database. Before the site is ever pointed at it,
read:

- the **variant definition file** — it should declare IDs, names, countries and class overrides
  and little else;
- the **installer** — it should write map, territory and starting-unit rows and nothing more;
- every file in the variant's **`classes/`** directory.

Stop and report if any of them: make outbound network calls; read or write files outside the
variant's own directory; call `eval`, `exec`, `system`, `shell_exec`, `passthru` or
`unserialize` on anything it did not construct; touch user, session, or admin tables; or
obfuscate a string it then executes. None of that is normal in a variant.

## Steps

1. Fetch the variant package. Review it as above and record the outcome in this issue.
2. **Install the dependency variant first.** This port requires a prerequisite variant (the
   "got2" base) to be present before the main variant will install; installing the main one
   first will fail or install incompletely.
3. Allocate IDs from the **900 block** (see `variant-registry.md`) if the upstream IDs collide
   with anything already reserved. Record the original ID in the registry's Notes column.
4. Place the folders under the variants directory. Ensure each has its `cache/` directory,
   created and writable — it is gitignored and will not arrive with the download.
5. Confirm each ships the mandatory `resources/style.css` (**not** `variant.css`) and its
   dark-mode resources.
6. Register the IDs in the site config's variant array. First load auto-installs them.
7. In the admin panel, run the **variant-info update** action, and the **variant-wipe** action to
   clear caches. New variants do not appear until you do.
8. Add both rows to `variant-registry.md` in the same change.
9. Run the acceptance checklist.

## Done when

- [ ] The security review is done and its outcome recorded in this file.
- [ ] The dependency variant is installed before the main one.
- [ ] Both variants have rows in `variant-registry.md` with IDs, source and author preserved.
- [ ] Both appear on the New Game form.
- [ ] Acceptance checklist items 1–5 from `SPEC.md` all pass.
- [ ] A two-player Westeros game has been created and its spring phase adjudicated.
- [ ] Registry status is `playable`.

## Verification

```
curl -s http://127.0.0.1:43000/gamecreate.php | grep -i -o 'Westeros[^<]*'
docker compose logs --tail=50 php-fpm     # no PHP 8.4 deprecations/fatals from the variant
```

Then, in a browser: create a Westeros game, compare the starting position against the upstream
variant's own map image unit by unit, submit a spring move including a bounce and a support,
confirm it adjudicates, and confirm supply-centre counts after autumn.

## Notes / gotchas

- **Legacy board only.** The React board hard-codes Classic geometry and whitelists three
  variants; Westeros is not one and never will be without the fork described in issue 010. This
  is expected — do not treat it as a broken install.
- The likely failure mode is **PHP 8.4 incompatibility in the variant's own classes**, not API
  drift: the variant API is still version 1 and the base class has not changed in three years.
  Read the PHP log, not the variant API docs.
- This is a fan work derived from A Song of Ice and Fire. Private LAN use is fine; publishing
  the map or the site is not. See `SPEC.md` → Further Notes.
