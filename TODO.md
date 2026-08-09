# GameMGR TODO

Internal. Not shipped: `deploy/build-release.sh` excludes it from the release
tarball. Last updated 2026-08-09.

There is **no live GameMGR box any more**. gamemgr001 was destroyed on
2026-08-09 once self-update had been proven twice unattended and ARK was done,
and both of its Cloudflare records were deleted with it. Everything runs on the
local dev stack at :8940 (`~/dev/gamemgr-docker-dev`, `make health`).

The rsync-over-SSH deploy Allen authorised was for that host specifically and
died with it. Anything new needs its own decision. History and what the box
proved: `~/servers/gamemgr001.scriptgain.com/CHANGELOG.md`.

Loose end, not a GameMGR one: the cross-zone azcomputer Cloudflare token that
lived on that box was never rotated. It can edit every zone on the account.

---

## Next up

Nothing blocking. Every defect found is fixed and every claim is tested. The
list below is all deferred work, each item wanting its own plan.

**gamemgr001 is destroyed** and nothing is blocked on hardware. Self-update was
the last thing that needed a real machine and it is proven: the box walked 1.0.0
to 1.1.0 to 1.1.1 on its own scheduler, checksum-verified, archiving each
previous install.

---

## Deferred, with reasons

| Item | Effort | Why not yet |
|---|---|---|
| Hosted Cloud product | XL | The panel is free. The paid thing is hosted GameMGR, and it does not exist. The site should say "Cloud, coming soon" and nothing else until it does. Not a gating problem: `Edition` already gates templates |
| ARK Workshop placement | L | Items land in `steamapps/workshop/content/...`, but ARK wants them unpacked into `ShooterGame/Content/Mods` with `.z` expanded. Needs a real ARK server, a 15 GB download. CS2 and Garry's Mod work today |
| Reverse-mode transport | L | Schema, UI and config all exist; the tunnel does not. Real networking, 1-2 days |
| Billing and ordering | XL | Needs a product plan first |
| Modpacks, plugin dependency resolution | — | Deliberately out of scope in the approved mod plan |

---

## Traps worth knowing before you touch anything

- **A long-running `queue:work` holds the old class in memory.** Editing a class
  a job uses does nothing until `docker compose restart queue`. Cost an hour
  twice in one night.
- **`docker compose` is not on the CLI in this shell.** The plugin is at
  `/usr/libexec/docker/cli-plugins/`. `bin/health.sh` uses it and reports a
  **false green** (0 routes swept) without it. Use a `DOCKER_CONFIG` shim.
- **Testing migration needs two real daemons**, not two node rows pointing at
  one. The dev stack has `node2` on 8943 with its own root for this.
- **The node form posts `runtimes` as a map** (`runtimes[docker] => "1"`), not a
  list. A list fails with "pick at least one runtime", which sends you looking
  in the wrong place entirely.
- **A template's `startup` is copied onto the server at create time**, so fixing
  a template does nothing for servers that already exist. Item 1 above.

---

## Done recently, so nobody redoes it

**2026-08-08 / 09.** All proved on hardware unless noted.

- Telemetry: settings page showing the exact payload, hourly send, asked-once
  banner. scriptgain.com now **accepts** it (204); it was 302 earlier that day
- Connection names live: zone `play.scriptgain.com`, node `lax1`, grey wildcard.
  Deleting the record at Cloudflare leaves the IP working and the hourly sync
  restores it
- `dns_label` can no longer wipe itself, and node edits are audited. This had
  already killed every name on the node once
- Mumble and TeamSpeak both boot. Both templates were bypassing their image
  entrypoints, so Mumble ran as root with every panel variable discarded and
  TeamSpeak would never have accepted its licence
- Migration between two nodes: **could never have worked**. The archive was
  fetched after the row had moved to the target, so the download link pointed at
  the machine that did not have it. Fixed, with a regression test
- The daemon publishes every allocation, not just the primary. TeamSpeak's
  10011 and 30033 were reserved, listed and firewalled but never mapped
- Mod catalogues: Modrinth, Hangar, SpigotMC, CurseForge, and Steam Workshop by
  id. ViaVersion (Hangar) and WorldEdit (CurseForge) installed onto the live
  Minecraft server, both checksum-verified
- The checksum refusal is tested by breaking it on purpose: wrong bytes, no
  published hash, wrong host, oversized
- Status pages are embeddable: `/status/{slug}.json`, `/status/{slug}/embed`,
  and `public/js/status-widget.js`
- `/api-docs`: a real reference generated from the OpenAPI document, with a test
  that fails if the page misses an endpoint the document declares
- `/settings/firewall` and `/settings/audit` were complete and unreachable;
  they are in the settings menu now
- **A Docker container is rebuilt when its spec changes.** Start used to launch
  whatever container already existed, so editing a startup, an image or a
  variable did nothing until a Reinstall. Proved on gamemgr001: changed Mumble's
  max users, pressed Start, container came back with the new value. Any
  container built before this heals itself on its next start, which also repairs
  the ones still publishing a single port
- **A real Workshop fetch ran on hardware**: steamcmd pulled a CS2 item onto
  gamemgr001 in 10.5s. The game does NOT need installing first
- The account page went from 1931px to 1293px, and power actions no longer log
  "Stoped the server"
- **Self-update works, proven twice unattended**, 1.0.0 to 1.1.0 to 1.1.1. The
  release channel is live on scriptgain.com
- **The API reference documents request bodies**, generated from the validation
  rules rather than from a hand-written copy of them: 33 of 44 write endpoints,
  with the other 11 named in a test as genuinely taking none. Doing it found
  three real bugs, all shipped fixed: `POST /api/application/servers` 500'd when
  `node_id` was omitted, which is the documented way to say "put it wherever it
  fits" and which every existing test happened to avoid; the backup and
  reinstall endpoints read fields they never validated; and the startup endpoint
  validated nothing at all
- A CloudPanel vhost matches `.gz` as static, so the release tarball route
  returned nginx's 404 and never reached PHP: 1.0.0 was undownloadable for its
  whole life. Verify the ARTIFACT, hashed and cache-busted, never just the
  manifest

Current: **331 tests**, **100 routes clean** in `make health`. Panel version **1.1.1**.
