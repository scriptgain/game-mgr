package runtime

import (
	"regexp"
	"sort"
)

// Placeholders are written {{VARIABLE}}, which is the convention every
// Pterodactyl egg already uses. Importing their catalogue is the whole point of
// the egg importer, so the daemon has to understand their spelling rather than
// invent its own.
var placeholder = regexp.MustCompile(`\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}`)

// Expand substitutes {{VARIABLE}} in a startup command from the server's
// environment and reports any placeholder it could not fill.
//
// Unresolved placeholders are left EXACTLY as they were rather than replaced
// with an empty string. Blanking them produces a command that looks plausible
// and starts a server listening on the wrong port, or with no world name, which
// is far harder to diagnose than an argument that visibly still reads
// {{SERVER_PORT}}. The caller is expected to log whatever comes back unresolved.
func Expand(command string, env map[string]string) (string, []string) {
	missing := map[string]struct{}{}

	out := placeholder.ReplaceAllStringFunc(command, func(match string) string {
		name := placeholder.FindStringSubmatch(match)[1]
		if v, ok := env[name]; ok {
			return v
		}
		missing[name] = struct{}{}

		return match
	})

	names := make([]string, 0, len(missing))
	for n := range missing {
		names = append(names, n)
	}
	sort.Strings(names)

	return out, names
}
