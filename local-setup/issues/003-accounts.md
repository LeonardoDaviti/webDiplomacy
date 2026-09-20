---
id: 003
title: Create the ten accounts, promote the admin, and block public registration
label: ready-for-agent
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

- [ ] Exactly ten user accounts exist.
- [ ] The owner's account has type `User,Moderator,Admin` and the admin panel loads.
- [ ] The other nine accounts each have a known working password and can log in.
- [ ] All ten credentials are written in `local-setup/credentials/`.
- [ ] `git status` does not list anything under `local-setup/credentials/`.
- [ ] The registration URL and its directory form both refuse, logged out, with 403 or 404.
- [ ] Game processing still works after the block (confirm the status page's last-run is still
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
