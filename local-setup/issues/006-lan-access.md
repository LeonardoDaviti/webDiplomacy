---
id: 006
title: Open the site to the LAN, and only to the LAN
label: needs-human
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

---

## Status — agent steps done 2026-09-20, `needs-human` for the second-device test

The site is on the LAN at **<http://10.13.101.254:43000/>**. The port-binding change, the restart
policies and every agent-verifiable check are done. What is left is the human part — steps 6 and
the `[HUMAN]` boxes above — plus two things that need root or a router, written out below so they
are copy-paste.

### The address

| | |
|---|---|
| **Bookmark this** | **<http://10.13.101.254:43000/>** |
| Host | `helicarrier` |
| Interface | `wlp194s0` (wifi), MAC `f8:3d:c6:b7:12:fc` |
| Address | `10.13.101.254/22`, **DHCP** (`nmcli`: `ipv4.method: auto`) — *not* reserved yet |
| Subnet | `10.13.100.0/22` (10.13.100.1 – 10.13.103.254, ~1022 hosts) |
| Gateway | `10.13.100.1` |
| Also on Tailscale | `100.93.151.44` — see the warning below, this is probably what you want |

### Read this before handing the URL out — this is not a house LAN

The wifi this host is on is **`D Block Workspace@stamba`**, a shared coworking network, and it is
a **/22 — up to 1022 other machines**, none of them yours. Issue 006 was written for "the other
devices in the house". That assumption does not hold here, and it changes the risk:

- Everything on that network can now reach the site.
- Registration is 404 (issue 003) and the gamemaster/SSE/session secrets in `config.php` are
  generated, not the sample values — both checked. So nobody walks in and gets an admin account.
- But **all ten accounts have short known passwords** (issue 003, `local-setup/credentials/`),
  and the login form is over plain HTTP on a network full of strangers. Treat those passwords as
  public from now on, and never reuse them anywhere.
- Restricting the firewall "to the LAN subnet" — step 5 above — **buys nothing here**, because
  the LAN subnet *is* the untrusted population. It is still worth doing so the site is not
  exposed if this host later joins a network where the distinction means something.

**The better option, already available: Tailscale.** It is running on this host
(`100.93.151.44`) and the owner's Android device is already in the tailnet. Loading
`http://100.93.151.44:43000/` from a tailnet device gives the same site over an encrypted link,
reachable from anywhere, with no coworking strangers on the path. The second-device test below
works identically against that address. If Tailscale is the route you take, the LAN binding can
be reverted (put the `127.0.0.1:` prefix back on webserver's port in `docker-compose.yml`) —
Tailscale reaches a localhost-bound port only if you also bind to the tailscale IP, so in
practice use `100.93.151.44:43000:80` as the binding instead of `43000:80`.

### [HUMAN] The second-device test — do this exactly

On a **phone or a second laptop**, not this host, not a container:

1. Join the same wifi (`D Block Workspace@stamba`), or the tailnet if you took that route.
2. Open **<http://10.13.101.254:43000/>**. The front page should load. If it does not, the most
   likely cause is **AP client isolation** — many coworking access points block device-to-device
   traffic outright. That is not something this checkout can fix; use the Tailscale address.
3. Log in as **`player2`** (password in `local-setup/credentials/accounts.md`).
4. Open **game 2**, which issue 001's smoke test left **paused**:
   <http://10.13.101.254:43000/board.php?gameID=2>
   - Check the **board renders** — the map, the units, the country panel. This is the React board
     at `/game/`, and it is the part most likely to break on a non-localhost host, so look at it
     properly rather than just seeing a page.
   - Check the **SSE indicator connects** (the live-connection marker on the board, not a
     reload). `/events` answers from the LAN already — it returns `403 Missing auth parameter`
     to an unauthenticated request, which is the SSE server itself replying, i.e. correctly
     proxied — but only a real logged-in board proves the token handshake works over this host.
5. **Then the order test.** Game 2 is paused, so no order can be submitted in it. Create or join a
   game instead: from the second device create a game with a short phase length, join it from this
   host as `admin` (or another `playerN`), fill the remaining seats, and **submit one order from
   the second device**. Confirm it is accepted and that the board on the *other* screen updates
   **without a reload** when the opposing order comes in. That last part is the whole point of
   step 6 — it is what proves SSE, not just HTTP, works off-host.
6. Unpause game 2 afterwards if you want it live again: the **togglePause** action in the game
   admin panel, or re-run the `curl` in `001-smoke-test.md`'s Status section.

### [HUMAN, needs root] The firewall — `ufw` is active but is NOT what controls this port

`sudo -n ufw status` was refused: **there is no passwordless sudo on this host**, so none of the
firewall state could be read and no rule could be applied by the agent. Run these yourself with
`! sudo ...`. Current facts that *were* readable: `ufw` is installed and `systemctl is-active ufw`
returns **active**; `firewalld`, `nftables` and `iptables.service` are all inactive.

**The trap:** `ufw` rules will not restrict port 43000. Docker inserts its own DNAT and filter
rules *ahead of* ufw's chains, so `ufw deny 43000` has no effect on a published container port.
The chain Docker leaves for exactly this purpose is **`DOCKER-USER`**, and it sees the packet
*after* DNAT — so the port to match is the **container's port 80**, not 43000.

Inspect first:

```sh
sudo ufw status verbose
sudo iptables -L DOCKER-USER -n --line-numbers
```

Then, to admit port 43000 from the LAN subnet only — drop anything arriving on the wifi interface
for a container's port 80 that is not from `10.13.100.0/22`:

```sh
sudo iptables -I DOCKER-USER 1 -i wlp194s0 -p tcp --dport 80 ! -s 10.13.100.0/22 -j DROP
```

`-i wlp194s0` keeps this off the docker bridges, off loopback and off `tailscale0`, so
container-to-container traffic, localhost and the tailnet are all unaffected. To verify, and to
remove it again if it goes wrong:

```sh
sudo iptables -L DOCKER-USER -n --line-numbers
sudo iptables -D DOCKER-USER 1
```

**It will not survive a reboot on its own.** Arch has no iptables-persistent by default; either
save and restore it:

```sh
sudo iptables-save | sudo tee /etc/iptables/iptables.rules
sudo systemctl enable iptables
```

or, simpler and less likely to fight with Docker, re-apply the one line from a small
`systemd` unit ordered `After=docker.service`.

Again: on *this* network that rule changes almost nothing, since the hostile population is inside
`10.13.100.0/22`. It matters the moment this laptop is on a home network instead.

### [HUMAN, needs the router] Making the address fixed

`10.13.101.254` is a **DHCP lease** (`ipv4.method: auto` on the `D Block Workspace@stamba`
NetworkManager connection) with about 5h45m left when this was written. It will very likely be
handed back — the same lease usually is — but nothing guarantees it, and a bookmark that breaks
silently is worse than no bookmark.

Prefer a **DHCP reservation on the router**, not a static address on the host: the router stays
the single place that hands out addresses, so nothing can collide, and the host needs no change.
Generically, on any consumer router:

1. Open the router's admin page (usually the gateway address, here `10.13.100.1`) and log in.
2. Find **DHCP** settings — the section may be called *LAN*, *DHCP Server*, *Address
   Reservation*, *Static Leases* or *DHCP Binding* depending on the vendor.
3. Add a reservation mapping the MAC **`f8:3d:c6:b7:12:fc`** to the address **`10.13.101.254`**.
   Most routers list currently-connected devices so you can pick `helicarrier` rather than typing
   the MAC.
4. Make sure the address is inside the router's DHCP range, or outside it and unused — vendors
   differ on which they require.
5. Reconnect the wifi on the host (`nmcli con down "D Block Workspace@stamba" && nmcli con up
   "D Block Workspace@stamba"`) and re-check with `ip -4 addr show wlp194s0`.

**On a coworking network you will not have router access.** In that case either re-check the
address with `ip -4 addr show wlp194s0` whenever the site stops answering, or — much better —
use the Tailscale address `100.93.151.44`, which is permanently assigned to this host by the
tailnet and needs no router at all.

Setting a static address on the host instead (`nmcli con mod "D Block Workspace@stamba"
ipv4.method manual ipv4.addresses 10.13.101.254/22 ipv4.gateway 10.13.100.1 ipv4.dns 10.13.100.1`)
is the fallback if there is no router access *and* no Tailscale, but it risks colliding with
whatever the DHCP server later hands to someone else, and it has to be undone before joining any
other network.

### The two accepted losses on plain HTTP — both confirmed, both permanent without a certificate

1. **Web push notifications do not work.** They require a secure context, and `http://<ip>` is not
   one. Nothing in the config brings them back. The **`/events` SSE stream is unaffected** — that
   is a plain HTTP connection and it is what makes the board update live, so the thing that
   matters most still works.
2. **Registration and password-reset links come out with `https://`.** They take the host from the
   request but hard-code the scheme, so a link mailed to mailhog reads
   `https://10.13.101.254:43000/...` and will not load. **Hand-edit the `https://` to `http://`.**
   Registration is 404 anyway (issue 003), so in practice this only bites on a password reset.

Tailscale does not fix either of these by itself — `http://100.93.151.44:43000/` is still not a
secure context. Tailscale *Serve/Funnel* would give a real HTTPS name and fix both, and is the
only route to that short of a certificate; it is out of scope here.

### What was actually changed

`docker-compose.yml`, both marked in place:

- **`LOCAL DEVIATION (issue 006)`** — webserver's binding `127.0.0.1:43000:80` → `43000:80`, and
  the port table in the file header updated to say so. **Nothing else moved:** mariadb (43003),
  mailhog (43001), redis (43005), sse (43006) and phpmyadmin (43009) are all still on
  `127.0.0.1`, verified below by connecting to each from the LAN address.
- **`LOCAL DEVIATION (issue 006/011)`** — `restart: unless-stopped` added to **webserver** and
  **php-fpm**, which issue 011 recorded as `restart=no` and therefore as the reason the stack did
  not survive a host reboot. `sse` and `redis` already carried `unless-stopped` upstream and were
  not touched. **`mariadb` was deliberately left on upstream's `restart: always`** — step 3 of the
  task asked for `unless-stopped` there, but `always` is the stronger policy (it also brings back
  a container that had been stopped by hand before the reboot), so changing it would only lose
  coverage. A note saying this is in the file. `mailhog` and `phpmyadmin` were left alone.

### Verification — the agent-verifiable part

Ports, from `docker compose ps` after the restart. The web port is on `0.0.0.0` and the second
list the issue asks for is **empty**:

```
mariadb     127.0.0.1:43003->3306/tcp
mailhog     127.0.0.1:43001->8025/tcp
phpmyadmin  127.0.0.1:43009->80/tcp
redis       127.0.0.1:43005->6379/tcp
sse         127.0.0.1:43006->43006/tcp
webserver   0.0.0.0:43000->80/tcp, [::]:43000->80/tcp
```

```
$ ss -ltnp | grep 43000
LISTEN 0 4096   0.0.0.0:43000   0.0.0.0:*
LISTEN 0 4096      [::]:43000      [::]:*
```

Fetched **over the LAN address, not localhost**:

| URL | Code | |
|---|---|---|
| `http://10.13.101.254:43000/` | 200 | |
| `http://10.13.101.254:43000/index.php` | 200 | |
| `http://10.13.101.254:43000/game/` | 200 | the React board |
| `http://10.13.101.254:43000/logon.php` | 200 | |
| `http://10.13.101.254:43000/events` | 403 | `Missing auth parameter` — the SSE server replying, so still proxied |
| `http://10.13.101.254:43000/register.php` | 404 | issue 003 still holds from the LAN |

And the ports that must **not** answer, each tried against `10.13.101.254` — all five
**Connection refused**: 43001, 43003, 43005, 43006, 43009.

`status.php` over the LAN address returned 200 and every row green except the two issue 001
already recorded as permanent here (`Game Backup Archived`, `SSE Server Last Client Connect` —
the latter will clear the moment the human test opens a board). `Game Processing ✅ 0 minutes,
1 seconds`, `Gamemaster Called ✅ 0 minutes, 1 seconds`, `Redis Server Online ✅`, `SSE Server
Online ✅`, `Error Logs ✅ 0 entries`.

### The issue-011 restart proof, which was still pending

Issue 011 recorded that the stack did not survive a host reboot and said so had been read off the
restart policies rather than proven. With the policies now in place, a full stop and start was
performed — `docker compose --profile core --profile dev down` (**no `-v`**; the issue 002 volume
must not be touched) then `up -d`, with a 22-second gap:

- `docker compose ps -a` — mariadb, webserver, php-fpm, sse, redis, mailhog, phpmyadmin all
  **running**. (`game-build` shows `exited`, which is a one-shot build container and correct.)
- `docker compose logs webserver | grep -c 'host not found'` → **0**. The issue 011 nginx
  `resolver` fix holds across a cold start; the old workaround of
  `docker compose start webserver` is not needed.
- `grep -c '^READY' gamemaster-entrypoint.txt` → **1**.
- The database survived: `admin` is still userID 12, `User,Moderator,Admin`, and `wD_Users` still
  has **21** rows — the issue 002 named volume did its job.
- The gamemaster was steady at **59–60 runs a minute, 0 failed** before the cycle and recovered to
  the same afterwards. Recreating `webserver` costs about 11 failed calls, which the sse loop logs
  and then retries out of by itself (`Gamemaster call succeeded again, after 11 failures`).

Note this is a `down`/`up`, not an actual reboot. It proves the containers and the data come back
clean; it does not prove `docker.service` itself is enabled at boot. Check that separately with
`systemctl is-enabled docker`.

### Still open from the step list above

- **Step 3 — the database password is still the compose default** (`mypassword123`, in
  `docker-compose.yml` and `config.php`). It was left alone deliberately: it is outside this
  change's scope and touching `config.php` would collide with the variant install running
  concurrently. It is **not** LAN-exposed — 43003 is still on `127.0.0.1` and was verified refused
  from `10.13.101.254` — so nothing is reachable because of it, but the "Done when" box stays
  unticked until it is changed in `docker-compose.yml` and `config.php` together.
- **Step 5 — the firewall rule**, which needs root; the command is above.
- **Step 4 — the DHCP reservation**, which needs the router; instructions above.
- **Steps 6 and the three `[HUMAN]` boxes** — the second-device test.

Issue 011's runbook section 7 has been rewritten with the real address and this procedure.
