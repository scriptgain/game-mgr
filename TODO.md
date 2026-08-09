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

### 1. Recreate a Docker container whose spec changed  ·  M  ·  ~2 hrs
`agent/internal/runtime/docker/docker.go`

Start builds the right container spec and then starts whatever container already
exists, so a container is frozen at whatever it was created with. Consequences
already hit: the Mumble startup fix needed a Reinstall before it took, and every
container built before 2026-08-09 still publishes only its primary port.

Compare `Config.Cmd`, `Config.Image`, `Config.Env` and `HostConfig.PortBindings`
against the desired spec on Start; if they differ, remove and recreate. Data is
in the bind mount so nothing is lost. Only when stopped, and never delete before
the replacement is known good.

Only Docker has this. The native runtimes rewrite their launcher every start.

### 2. Prove a real Workshop fetch on hardware  ·  M  ·  ~1 hr
Only ever tested against a faked node; steamcmd has never actually run.

`InstallWorkshopItem` calls `EnsureDir` then `workshop_download_item`, so **the
game does not need installing first** and there is no 30 GB download.

1. `systemctl stop gamemgr-queue` on gamemgr001 so the CS2 app install queues
   and never runs
2. Create a CS2 server (template 12, app 730) through the panel
3. Install a Workshop item by id from the Mods tab
4. Check `/var/lib/gamemgr/volumes/<uuid>/steamapps/workshop/content/730/<id>`
   and that the mods row has `verified = false`
5. Delete the server, start the queue worker

### 3. Publish a release so self-update works  ·  M  ·  ~2-3 hrs
`UpdateService` polls `https://scriptgain.com/releases/gamemgr/latest.json`,
which has always been a **404**. Self-update has never run once, which is a
headline feature of a free self-hosted panel that does not exist.

`deploy/build-release.sh` already emits the exact manifest it reads. Follow the
DeskMGR `/download` pattern on scriptgain.com rather than inventing a mechanism.

**Split:** I prepare and commit; **Allen presses Deploy in GitMGR** (repo 40,
branch master). No SSH to cp1.

Before release 2: a tarball with `vendor/` is tens of megabytes and does not
belong in the storefront repo forever. StorageMGR is the obvious home.

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

Current: **311 tests**, **101 routes clean** in `make health`.
