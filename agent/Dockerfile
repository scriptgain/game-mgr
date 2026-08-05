# The GameMGR node daemon.
#
# The build stage produces a single static binary with no runtime dependencies,
# which is what a real node install ships.
FROM golang:1.23-alpine AS build

WORKDIR /src
COPY go.mod ./
RUN go mod download
COPY . ./
RUN CGO_ENABLED=0 go build -trimpath -ldflags "-s -w" -o /out/gamemgr-node ./cmd/node

# The runtime stage is Debian rather than Alpine, and that is not a preference.
# SteamCMD is a 32-bit glibc binary and LinuxGSM explicitly does not support
# musl, so a node that has to run either of them cannot be Alpine. The Go binary
# itself is static and does not care.
FROM debian:bookworm-slim

# The native runtimes need real tooling on the node:
#   tmux          holds every native server's console, and LinuxGSM requires it
#   lib32gcc-s1   steamcmd is 32-bit
#   bash, curl    LinuxGSM is a pile of bash scripts that fetch things
#   tar, xz, file, bsdmainutils, distro-info-data  LinuxGSM's own dependency list
#   procps        so /proc walking has the usual tools alongside it for debugging
RUN dpkg --add-architecture i386 \
    && apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        tmux \
        bash \
        curl \
        wget \
        tar \
        xz-utils \
        file \
        bsdmainutils \
        distro-info-data \
        lsb-release \
        procps \
        netcat-openbsd \
        gzip \
        bzip2 \
        unzip \
        binutils \
        bc \
        jq \
        python3 \
        lib32gcc-s1 \
        lib32stdc++6 \
    && rm -rf /var/lib/apt/lists/*

# SteamCMD comes from Valve rather than the Debian package, which is in non-free
# and needs an interactive licence prompt that has no place in a build.
RUN mkdir -p /opt/steamcmd \
    && curl -fsSL https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz \
       | tar -xz -C /opt/steamcmd \
    && printf '#!/bin/sh\nexec /opt/steamcmd/steamcmd.sh "$@"\n' > /usr/local/bin/steamcmd \
    && chmod +x /usr/local/bin/steamcmd

# LinuxGSM refuses to run as root, and rightly so: it downloads and executes
# game code. A dedicated unprivileged user exists for it, and the daemon drops
# to that user for LinuxGSM work.
RUN useradd -m -s /bin/bash gamemgr \
    && mkdir -p /var/lib/gamemgr/volumes \
    && chown -R gamemgr:gamemgr /var/lib/gamemgr /opt/steamcmd

COPY --from=build /out/gamemgr-node /usr/local/bin/gamemgr-node

EXPOSE 8942
ENTRYPOINT ["/usr/local/bin/gamemgr-node"]
