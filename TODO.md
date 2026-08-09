# GameMGR TODO

Internal. Not shipped: `deploy/build-release.sh` excludes it from the release
tarball. Last updated 2026-08-09.

Live test box: **gamemgr001.scriptgain.com** (45.63.49.152, SSH on port **22**,
not 5410). Running Minecraft and Mumble. Connection names live under
`play.scriptgain.com`. Full detail in `~/server-list.txt` and
`~/servers/gamemgr001.scriptgain.com/CHANGELOG.md`.

Deploys to that box are **rsync over SSH**, which Allen authorised for it
specifically: it was installed from a release tarball, is not a git checkout,
and has no GitMGR entry. Back up first (`/root/gamemgr-backups/`). No php-fpm
restart is needed; opcache there revalidates every 2 seconds.

---

## Next up

### 1. Publish 1.1.0 so self-update can be proven  ·  S  ·  ~15 min, mostly waiting

**The feed itself is LIVE.** `https://scriptgain.com/releases/gamemgr/latest.json`
exists and serves 1.0.0. A scriptgain session built `GameMgrReleaseController`
(per-version paths, `SHA256SUMS`, release notes) and deployed it, so the 404 that
made self-update impossible on every install ever made is gone.

What is left is publishing **1.1.0**, which is built and staged in the scriptgain
repo at `storage/app/dist/gamemgr/1.1.0/` (tarball, SHA256SUMS, NOTES.md).
`storage/app/.gitignore` is `*`, so it cannot ride a git deploy: it has to reach
cp1 the same way 1.0.0 did. **Handed to the scriptgain session.**

gamemgr001 is deliberately left on **1.0.0** so that the moment 1.1.0 is
published, its Updates page shows the banner and Update Now becomes the first
real end-to-end proof that self-update works. That is the last thing keeping
that box alive.

---

## Deferred, with reasons

| Item | Effort | Why not yet |
|---|---|---|
| Tiering enforcement (free / Basic / Pro / Plus) | M | Allen's call. `Edition` already gates templates; the pricing decisions do not exist |
| ARK Workshop placement | L | Items land in `steamapps/workshop/content/...`, but ARK wants them unpacked into `ShooterGame/Content/Mods` with `.z` expanded. Needs a real ARK server, a 15 GB download. CS2 and Garry's Mod work today |
| Request bodies in the API docs | L | `/api-docs` documents paths and query parameters, not bodies. Means extracting schemas from ~40 controllers' validation rules. The page says so out loud |
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

Current: **311 tests**, **101 routes clean** in `make health`. Panel version **1.1.0**.
