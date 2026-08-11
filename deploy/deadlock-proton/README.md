# Deadlock server image

Deadlock has no dedicated server build. You install the game itself (Steam app
**1422450**, roughly 40 GB) with an account that owns it, and run it under
Proton. This image does that, and runs [Deadworks](https://github.com/Deadworks-net/deadworks)
rather than the stock binary, so server-side plugins work.

Built from Deadworks PR #7 (`raimannma:docker-build`), MIT, with three changes
for GameMGR:

- **One root.** Proton, the .NET cache, game files and the Steam sentry all live
  under `/home/steam`. GameMGR's Docker driver binds exactly one host path, and
  anything outside it is re-downloaded on every container recreate.
- **One login attempt.** Upstream retries three times, five seconds apart. On a
  Steam Guard protected account that is three rejected codes in fifteen seconds,
  which earns a rate limit that reads exactly like a bad password.
- **The panel's variable names.** `STEAM_USER` / `STEAM_PASS` /
  `STEAM_GUARD_CODE` as well as upstream's, and the code goes into a 0600
  runscript rather than onto argv, where `/proc` would expose it for the length
  of a 40 GB download.

## The host needs one thing

    sysctl -w vm.max_map_count=2147483642

Without it the server dies during startup with nothing that points at the cause.
GameMGR's node health probe reports it.

## Plugins

Drop a compiled `.dll` into `<server files>/game/bin/win64/managed/plugins/`.
The panel's file manager reaches it. The image's own plugins are copied over the
top on each start, so avoid naming yours after a bundled one.

## Building

    DOCKER_CONFIG=<scratch> docker buildx build --load -t ghcr.io/scriptgain/deadlock-proton:<tag> .

Needs BuildKit; the Dockerfile uses `RUN --mount`. On Allen's workstation the
Desktop buildx plugin segfaults and Docker falls back to the legacy builder
silently, so point `DOCKER_CONFIG` at a directory whose `cli-plugins/` symlinks
`/usr/libexec/docker/cli-plugins/docker-buildx`.
