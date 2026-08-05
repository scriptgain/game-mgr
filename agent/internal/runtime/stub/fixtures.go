package stub

import (
	"fmt"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

// bootLog returns a boot sequence shaped like the real thing for the runtime in
// question. Console output is the first thing anyone looks at, so a stub that
// prints "hello world" makes the whole panel feel fake.
func bootLog(s runtime.Server) []string {
	switch s.Runtime {
	case "steamcmd":
		return []string{
			"[gamemgr] starting server via steamcmd runtime",
			fmt.Sprintf("[steamcmd] validating app %d", s.SteamAppID),
			"[steamcmd] Success! App fully installed.",
			"Setting breakpad minidump AppID",
			"Loading map",
			"Server is hibernating",
			fmt.Sprintf("Connection to Steam servers successful, public IP is %s.", s.IP),
			fmt.Sprintf("VAC secure mode is activated. Listening on %s:%d", s.IP, s.Port),
			"[gamemgr] server marked as running",
		}
	case "linuxgsm":
		return []string{
			"[gamemgr] starting server via linuxgsm runtime",
			fmt.Sprintf("[ .... ] Starting %s: %s", s.LGSMShortname, s.Name),
			"[  OK  ] Checking for update: SteamCMD",
			"[  OK  ] Starting tmux session",
			fmt.Sprintf("[  OK  ] Started %s", s.LGSMShortname),
			fmt.Sprintf("Server listening on %s:%d", s.IP, s.Port),
			"[gamemgr] server marked as running",
		}
	default:
		return []string{
			"[gamemgr] starting server via docker runtime",
			fmt.Sprintf("[gamemgr] container image %s", s.Image),
			"[gamemgr] " + s.Startup,
			"Starting net.minecraft.server.Main",
			"[main/INFO]: Environment: authHost='https://authserver.mojang.com'",
			"[main/INFO]: Loaded 7 recipes",
			"[Server thread/INFO]: Starting minecraft server version 1.21.4",
			fmt.Sprintf("[Server thread/INFO]: Default game type: SURVIVAL"),
			"[Server thread/INFO]: Generating keypair",
			fmt.Sprintf("[Server thread/INFO]: Starting Minecraft server on %s:%d", s.IP, s.Port),
			"[Server thread/INFO]: Using epoll channel type",
			"[Server thread/INFO]: Preparing level \"world\"",
			"[Server thread/INFO]: Preparing start region for dimension minecraft:overworld",
			"[Server thread/INFO]: Time elapsed: 2187 ms",
			"[Server thread/INFO]: Done (4.512s)! For help, type \"help\"",
			"[gamemgr] server marked as running",
		}
	}
}

// idleLine is the chatter that keeps arriving once a server is up.
func idleLine(s runtime.Server, i int) string {
	ts := time.Now().Format("15:04:05")
	lines := []string{
		"[%s] [Server thread/INFO]: Saving the game (this may take a moment!)",
		"[%s] [Server thread/INFO]: Saved the game",
		"[%s] [Server thread/INFO]: Roundabout joined the game",
		"[%s] [Server thread/INFO]: <Roundabout> anyone got spare iron",
		"[%s] [Server thread/INFO]: Keeping entity minecraft:item alive",
		"[%s] [Server thread/INFO]: Roundabout left the game",
		"[%s] [Server thread/WARN]: Can't keep up! Is the server overloaded? Running 2043ms behind",
		"[%s] [Server thread/INFO]: Autosave complete",
	}
	if s.Runtime == "steamcmd" || s.Runtime == "linuxgsm" {
		lines = []string{
			"[%s] Client connected: 76561198000000001",
			"[%s] Saving world state",
			"[%s] World saved in 412ms",
			"[%s] Client disconnected: 76561198000000001 (timed out)",
			"[%s] Steam auth ticket validated",
			"[%s] Server tick average 29.7ms",
		}
	}
	return fmt.Sprintf(lines[i%len(lines)], ts)
}

// seedFiles builds a small but recognisable tree so the file manager has
// something worth browsing: the layout matches what the runtime would produce.
func seedFiles(s runtime.Server) map[string]*file {
	now := time.Now().Add(-36 * time.Hour)
	f := func(body string) *file {
		return &file{body: []byte(body), mode: "0644", mod: now}
	}
	dir := func() *file { return &file{dir: true, mode: "0755", mod: now} }

	switch s.Runtime {
	case "steamcmd", "linuxgsm":
		return map[string]*file{
			"/serverfiles":                     dir(),
			"/serverfiles/cfg":                 dir(),
			"/serverfiles/cfg/server.cfg":      f("hostname \"" + s.Name + "\"\nsv_password \"\"\nmaxplayers 24\nsv_region 0\n"),
			"/serverfiles/cfg/banned_user.cfg": f("// banid <minutes> <steamid>\n"),
			"/log":                             dir(),
			"/log/console":                     dir(),
			"/log/console/console.log":         f("[boot] server started\n[boot] map loaded\n"),
			"/lgsm-config":                     dir(),
			"/lgsm-config/common.cfg":          f("# LinuxGSM common settings\nupdateonstart=\"on\"\n"),
			"/start.sh":                        {body: []byte("#!/bin/bash\nexec ./srcds_run -game csgo\n"), mode: "0755", mod: now},
		}
	default:
		return map[string]*file{
			"/plugins":                 dir(),
			"/plugins/EssentialsX.jar": {body: make([]byte, 1_842_112), mode: "0644", mod: now},
			"/plugins/LuckPerms.jar":   {body: make([]byte, 5_120_004), mode: "0644", mod: now},
			"/world":                   dir(),
			"/world/level.dat":         {body: make([]byte, 16_384), mode: "0644", mod: now},
			"/world/region":            dir(),
			"/world/region/r.0.0.mca":  {body: make([]byte, 4_231_168), mode: "0644", mod: now},
			"/logs":                    dir(),
			"/logs/latest.log":         f("[12:04:02] [Server thread/INFO]: Done (4.512s)!\n"),
			"/server.properties":       f(serverProperties(s)),
			"/eula.txt":                f("# By changing the setting below to TRUE you are indicating your agreement to the EULA.\neula=true\n"),
			"/ops.json":                f("[\n  {\n    \"uuid\": \"6a1f0b3c-1111-4c2a-9b0e-2f5d8a0c1234\",\n    \"name\": \"Roundabout\",\n    \"level\": 4\n  }\n]\n"),
			"/whitelist.json":          f("[]\n"),
			"/server.jar":              {body: make([]byte, 48_234_496), mode: "0644", mod: now},
		}
	}
}

func serverProperties(s runtime.Server) string {
	return fmt.Sprintf(`#Minecraft server properties
server-port=%d
server-ip=%s
motd=%s
max-players=20
gamemode=survival
difficulty=normal
online-mode=true
white-list=false
view-distance=10
simulation-distance=10
enable-rcon=true
rcon.port=%d
`, s.Port, s.IP, s.Name, s.Port+10)
}
