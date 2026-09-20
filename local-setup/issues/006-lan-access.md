---
id: 006
title: Open the site to the LAN, and only to the LAN
label: ready-for-agent
phase: P2
depends-on: [001, 002, 003]
---

# 006 — LAN access

Make the site reachable from the other devices in the house, at a stable address, and from
nowhere else.

**Do not start before issue 003 is `done`.** Opening the web port while public registration
still works hands an account to anyone on the network.

## Context — what this is *not*

There is **no site-URL setting, no static-host setting, and no local-config override mechanism**
in this codebase. The draft spec invented all three. Everything — the React board's requests and
the event stream, which nginx proxies — is **relative** to whatever host the browser used. So
LAN access is a **port-binding change and nothing else**.

Published ports in the compose file are all bound to `127.0.0.1` deliberately. Docker's
firewall rules sit *in front of* the host firewall's, so removing a localhost prefix genuinely
publishes that port to the network regardless of what the host firewall says.

## Steps

1. In a compose override on the `local` branch, remove the `127.0.0.1:` prefix from the
   **web server's** port binding only.
2. **Leave every other binding alone** — database, database admin UI, cache, mail catcher and
   the event server's own port all stay on localhost. The database's password is the compose
   file's published default; exposing it is the worst single mistake available here.
3. Change the database password from that default anyway, and update the site config to match,
   in the same change.
4. Give the host a stable LAN address — a DHCP reservation on the router keyed to its MAC, in
   preference to a static address on the host.
5. Restrict the host firewall so the web port admits only the LAN subnet.
6. **[HUMAN STEP]** From a *second physical device* on the LAN — a phone or a laptop, not the
   host, not a container — load the site by IP, log in, **submit an order in a live game**, and
   confirm the board **updates live** when the other side moves, without a reload.
7. Record the address and the procedure for issue 011's runbook.

## Done when

- [ ] Only the web server's port is published beyond localhost; everything else is still bound
      to `127.0.0.1`.
- [ ] The database password is no longer the compose default, and the site still starts.
- [ ] The host holds a stable LAN address across a router reboot.
- [ ] The host firewall admits the web port from the LAN subnet only.
- [ ] **[HUMAN]** A second device loaded the site by IP and logged in.
- [ ] **[HUMAN]** An order submitted from that device was accepted.
- [ ] **[HUMAN]** The board updated live on that device when the opposing order came in.
- [ ] The accepted losses below are understood and written into the runbook.

## Verification

Agent-verifiable part — the second list must be empty:

```
docker compose ps --format '{{.Service}}\t{{.Ports}}'
ss -ltnp | grep -E '430(00|01|03|05|06|09)'
```

Expect the web port on `0.0.0.0` and every other published port on `127.0.0.1`.

**[HUMAN STEP]** From a second device on the LAN:

```
http://<host-lan-ip>:43000/
```

Log in, open a game, submit an order, and watch it appear on the other device without a reload.

## Accepted losses on plain HTTP

Both are consequences of having no TLS, are known, and are accepted:

- **Web push notifications stop working.** They require a secure context, and `http://<ip>` is
  not one. Nothing can be configured to bring them back short of a certificate.
- **Registration and password-reset links come out wrong.** They build their URL from the
  request host but hard-code an `https://` scheme. Hand-edit the `https://` to `http://` when
  using such a link. Registration itself is blocked anyway (issue 003), so in practice this only
  affects password resets.
