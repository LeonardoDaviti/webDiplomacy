---
id: 009
title: Harvest community variants in supervised waves
label: needs-human
phase: P4
depends-on: [007, 008]
---

# 009 — Variant harvest

Breadth is the point: trying a new map should be a ten-minute job. This issue is the machinery
for that, run in waves, **supervised** — every wave installs third-party PHP that the site will
execute, so a human signs off each batch.

## Waves, recomputed

The draft's waves assumed an empty tree. **Fourteen variants already ship here and nine are
already enabled.** The draft's Chaos, Build Anywhere, Cold War, France vs Austria and Germany vs
Italy are all already present. Corrected:

### Wave 0 — zero harvest, config only. Do this first.

Five variants are in the tree but not registered in the config array. Enabling them is one
config entry each plus the admin variant-info refresh:

| Variant | `$id` | `$mapID` |
| --- | ---: | ---: |
| FleetRome | 3 | 1 |
| CustomStart | 4 | 1 |
| BuildAnywhere | 5 | 1 |
| Colonial | 12 | 12 |
| Zeus5 | 70 | 70 |

This is the cheapest possible end-to-end test of the add-a-variant pipeline on code that is
already known good. If wave 0 does not work, nothing harvested will.

Note the three that share **map ID 1** with Classic. Derivatives sharing a parent's map is
correct and must not be "fixed".

### Wave 1 — the named wants

Westeros (issue 007) and the two-player roster (issue 008). Already carved out; not repeated
here.

### Wave 2 — supervised harvest

Community variants from the vDiplomacy family, in batches of five or so. Prefer maps someone has
actually asked for over completeness.

### Wave 3 — the deferred list

Everything that failed triage. Revisited only on demand. Expect most failures to be PHP 8.4
breakage in the variant's own classes.

## The twenty-minute triage rule

Per variant, a hard budget of **twenty minutes** from first look to working starting position.
When it expires, the variant goes on the deferred list with a one-line note of what broke, and
you move to the next one. No exceptions, no "just one more thing".

This is the rule that makes breadth achievable. A deferred variant costs nothing; an afternoon
lost to one stubborn map costs five other maps.

## Per-variant procedure

1. **Security review** (as in issue 007): read the variant definition, the installer, and every
   file in `classes/`. Reject anything making network calls, touching files outside its own
   directory, calling `eval`/`exec`/`system`/`shell_exec`/`passthru`, touching user or session
   tables, or executing an obfuscated string.
2. Check its `$id` and `$mapID` against `variant-registry.md`. On collision, **renumber into the
   900 block** and record the original ID in Notes. Never displace an incumbent.
3. Place the folder; create a writable `cache/` directory (gitignored, will not arrive with the
   download).
4. Confirm it ships `resources/style.css` (**not** `variant.css`) and dark-mode resources.
5. Register the ID in the config array; first load auto-installs.
6. Admin panel: variant-info update, then variant wipe to clear caches.
7. Run the five-item acceptance checklist from `SPEC.md`.
8. **Update the registry in the same change** — never afterwards.

## Done when

- [ ] Wave 0's five variants are enabled and each is `playable` in the registry.
- [ ] Wave 2 has been run in at least one supervised batch, with a human sign-off recorded.
- [ ] Every variant attempted has a registry row with a status — no variant is undocumented.
- [ ] The deferred table lists every failure with what broke.
- [ ] No ID collisions: every `$id` in the config array is unique and matches the registry.
- [ ] A human has signed off each batch before it was enabled.

## Verification

```
# every enabled ID appears once
grep -o '[0-9]\+=>' config.php | sort | uniq -d     # must print nothing
curl -s http://127.0.0.1:43000/gamecreate.php | grep -c '<option'
docker compose logs --tail=200 php-fpm | grep -iE 'fatal|deprecated'
```

Then per variant, in a browser, the five acceptance items.

## Notes / gotchas

- **Every harvested variant is legacy-board only.** Do not spend triage time wondering why a new
  map does not render on the modern board.
- The variant API is still version 1 and the base class has not changed in three years. When a
  port fails, look at PHP 8.4 compatibility and the autoloader, not the API.
- Preserve each variant's author attribution into the registry. Do not strip it.
