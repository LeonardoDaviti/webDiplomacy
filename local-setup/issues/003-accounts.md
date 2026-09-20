---
id: 003
title: Create the ten accounts, promote the admin, and block public registration
label: done
phase: P1
depends-on: [001, 002]
---

# 003 — Accounts and registration lockdown

Ten hand-made accounts, one of them admin, and no way for anyone else to sign up.

## Context

The documented "visit the gamemaster endpoint with the secret and be promoted to admin" route
**does not work on this stack**: it only promotes while the site has never processed a turn, and
the event-server loop processes within seconds of first start. The window is closed before a
human can reach it. Use SQL instead — the install notes document this as the alternative.

There is **no configuration flag that disables registration**. The site's panic switch would do
it but also stops game processing, which is unacceptable. So registration is blocked in the
web-server configuration, before the request reaches PHP.

There is no "e-mail verified" flag in this schema, so nothing needs faking.

## Steps

1. Register the owner's account through the normal signup form — **while registration still
   works**. Do this first; order matters.
2. With exactly one account in the database, promote it:
   ```sql
   UPDATE wD_Users SET type='User,Moderator,Admin' WHERE type='User';
   ```
   Confirm it affected exactly one row. If it affected more, you created accounts too early —
   restore from issue 002's backup and start over rather than un-promoting by hand.
3. Log in as the owner and confirm the admin control panel is reachable.
4. For each of the nine remaining accounts: use the panel's **create-user** action (it does not
   set a password), then its **password-reset** action, which prints a freshly generated random
   password on the page. Use the change-email action if an address is wanted.
   - Alternative for guests: the panel's registration-link action prints a tokenised URL that
     can be handed over directly.
5. Record every username and password in `local-setup/credentials/` — which is **gitignored**.
   Never put a credential in a tracked file, an issue, or a commit message.
6. Block registration in the nginx configuration: refuse the registration script and its
   directory form before PHP sees them. Return a hard refusal, not a redirect.
7. Confirm the block from a logged-out browser.

## Done when

- [x] Exactly ten user accounts exist.
- [x] The owner's account has type `User,Moderator,Admin` and the admin panel loads.
- [x] The other nine accounts each have a known working password and can log in.
- [x] All ten credentials are written in `local-setup/credentials/`.
- [x] `git status` does not list anything under `local-setup/credentials/`.
- [x] The registration URL and its directory form both refuse, logged out, with 403 or 404.
- [x] Game processing still works after the block (confirm the status page's last-run is still
      advancing — this is what distinguishes the nginx block from the panic switch).

## Verification

```
# exactly one admin, ten users
docker compose exec mariadb mysql -uwebdiplomacy -p webdiplomacy \
  -e "SELECT COUNT(*) AS users FROM wD_Users; SELECT username,type FROM wD_Users WHERE type LIKE '%Admin%';"

# registration refused
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:43000/register.php
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:43000/register/
```

Both curls must print 403 or 404. Then confirm processing is unaffected:

```
curl -s http://127.0.0.1:43000/status.php | grep -i -A2 gamemaster
```

## Notes / gotchas

- Registration is protected by a map puzzle; there is no reCAPTCHA and no SMS step in this
  codebase, and the OAuth and SMS integrations are inert. Nothing to configure there.
- Fingerprinting in this codebase is passive and makes no external calls. Nothing to disable.
- Password-reset and registration links build their URL from the request host but hard-code an
  `https://` scheme. On this plain-HTTP install those links come out wrong and must be
  hand-edited to `http://`. Expect this; it is not a bug you introduced.
- To read a reset mail, the mailer's debug flag (set in 001) prints it to the page. The dev
  profile's mail catcher also works and is easier when several messages are in flight.

---

## Status — done 2026-09-20

Ten human accounts exist with known passwords, public registration returns 404 at nginx, and
game processing is unaffected. Credentials are in `local-setup/credentials/accounts.md`
(gitignored), which also carries the add-an-account recipes.

### The accounts

`admin` (userID 12, `User,Moderator,Admin`, created by issue 001) plus `player2` … `player10`
(userIDs 13–21, type `User`, e-mails `playerN@dip.lan`). `wD_Users` now holds 21 rows: these ten
and the eleven `Guest`/`System`/`Bot` rows the installer ships.

Steps 1–3 of the plan above did not apply as written: the owner's account already existed and
was already promoted, so nothing was registered through the form and no `UPDATE … WHERE
type='User'` was run. Confirmed in SQL that `admin` is the only account matching `%Admin%`, and
`admincp.php` returns 200 with the title `Admin CP - webDiplomacy` for its session.

### How the nine were made — deviation from the admin-CP route

The plan's step 4 (panel **create-user**, then **password-reset**) was not used. Reading
`AdminActionsRestricted::createUser` and `AdminActionsSeniorMod::resetPass` shows why it is the
worse route here:

- `createUser` inserts `email = username` — literally the username, not an address — and sets
  **no password column at all**. So it needs `changeEmail` afterwards to get `playerN@dip.lan`.
- `resetPass` generates `base64_encode(rand(1000000,2000000))`. The password cannot be chosen;
  you get a random string and have to scrape it off the response.

That is three admin form round-trips per account for a worse result. Instead the nine were made
the way issue 001 made `admin`: mint an e-mail token from `Config::$secret` with
`libAuth::email_token`'s formula, then POST it to `register.php` with `userForm[username]`,
`userForm[password]` and `userForm[passwordcheck]`. One request per account, chosen password,
chosen e-mail, and it runs the real `register/processUserForm.php` — so each account also gets
its welcome notice and its user-options row, which `createUser` skips. The anti-bot map puzzle
is only enforced on the *other* branch of `register.php` (the one that mails the token), so
holding a valid token skips it. All three routes are written up in the credentials file.

### Verification

- SQL: ten rows of type `User`, exactly one `%Admin%`, all e-mails as intended.
- Login: POSTed `loginuser`/`loginpass` to `logon.php` for all ten, kept the jar, then fetched
  `index.php` with it. Each returned its own `profile.php?userID=N` header link — admin→12,
  player2→13 … player10→21. Cookies set are `wD_Code`, `wD-Key` and `wD_Sess_User-<id>`.
- Registration closed: `/register.php` → 404, `/register/` → 404, and
  `/register/processUserForm.php` → 404 too (the `^~` prefix stops regex location matching, so
  the `\.php$` block never sees it). A hard 404, not a redirect.
- Nothing else moved: `/` 200, `/index.php` 200, `/game/` 200, `/logon.php` 200. `/events`
  answers `403 Missing auth parameter` from the SSE server itself — i.e. still proxied; a 404
  would have meant the location block was shadowed.
- `status.php` still shows **Game Processing ✅ Now since last process** and **Gamemaster Called
  ✅ Now since last call** after the restart, which is the check that distinguishes this from the
  panic switch. The only warnings are the two issue 001 already recorded as permanent here.

### The nginx change

`phpdocker/nginx/nginx.conf` gained two blocks, marked `LOCAL DEVIATION (issue 003)`:

```nginx
location = /register.php { return 404; }
location ^~ /register/   { return 404; }
```

They sit above the `\.env|gamebackups|errorlogs` block. `location =` is an exact match and `^~`
is a prefix match that suppresses regex locations, so both beat the `~ \.php(/|$)` handler
regardless of order in the file.

**To reopen registration:** comment out those two blocks in
`phpdocker/nginx/nginx.conf`, then `docker compose restart webserver`. If nginx does not come
back — it exits when the `sse` upstream is unresolvable, per issue 001 — `docker compose start
webserver`. Verify with `curl -o /dev/null -w '%{http_code}' http://localhost:43000/register.php`
returning 200.

**To add an eleventh account:** see "Adding an eleventh account" in
`local-setup/credentials/accounts.md`. Three routes are documented — the token+`register.php`
POST used here (needs the nginx block briefly commented out), the admin CP's
`createUser`+`changeEmail`+`resetPass` (works with registration still closed), and raw SQL with
`md5(Config::$salt . md5($password))`.

### Gotchas met

- The `https://`-hard-coded reset/registration URLs warned about above never came up: the token
  is built by hand, so no generated URL is involved.
- `git status` lists only `phpdocker/nginx/nginx.conf` and the issue file.
  `local-setup/credentials/accounts.md` is ignored by `local-setup/.gitignore:2` and `config.php`
  by `.gitignore:1`; both were checked with `git check-ignore -v`.
