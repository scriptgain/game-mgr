<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Check the catalogue without installing any of it.
 *
 * Two hundred and fifty nine templates is terabytes and days to install, and
 * most of what goes wrong does not need an install to find. SA-MP is the
 * example: its script reads $VERSION, its template declares a variable called
 * "Version", so the download URL lost its version number and 404'd. That is a
 * string comparison, not a download.
 *
 * So this reads each template the way its own script would and reports what
 * does not line up. It proves nothing about whether a game actually boots; it
 * finds the templates that cannot possibly work, which is a different and much
 * cheaper question.
 */
class AuditCatalogue extends Command
{
    protected $signature = 'gamemgr:audit-catalogue
        {--urls : Also check that download URLs in install scripts still answer (slow, network)}
        {--images : Also check that docker images still exist in their registry (slow, network)}
        {--broken : List only templates with problems}';

    protected $description = 'Statically check every template for problems that would make an install fail';

    /** Names the panel or the runtime supplies, so a script reading them is fine. */
    private const PROVIDED = [
        'SERVER_MEMORY', 'SERVER_IP', 'SERVER_PORT', 'SERVER_QUERY_PORT', 'SERVER_RCON_PORT',
        'P_SERVER_UUID', 'P_SERVER_ALLOCATION_LIMIT', 'P_SERVER_LOCATION',
        'STEAM_USER', 'STEAM_PASS', 'STEAM_AUTH', 'STEAM_GUARD_CODE',
        'HOME', 'PATH', 'PWD', 'USER', 'SHELL', 'TZ', 'LD_LIBRARY_PATH', 'LD_PRELOAD',
        'TERM', 'LANG', 'HOSTNAME', 'UID', 'GID', 'PUID', 'PGID', 'IFS', 'RANDOM',

        // Pterodactyl conventions. Every steamcmd egg reads these, always with a
        // default, and no panel has ever set one. They accounted for most of the
        // 156 templates that warned before this list existed.
        'SRCDS_BETAID', 'SRCDS_BETAPASS', 'INSTALL_FLAGS', 'WINDOWS_INSTALL',
        'EXTRA_FLAGS', 'DL_PATH', 'DOWNLOAD_LINK', 'DOWNLOAD_URL', 'MATCH',
        'VSTRING', 'GITHUB_PACKAGE', 'VERSION_CHECK', 'AUTO_UPDATE',
    ];

    /**
     * A name that is not an input at all.
     *
     * Environment variables are upper case by convention and every template in
     * the catalogue follows it, so anything lower case is a shell local. Single
     * characters likewise: the TF2 template warned about $P, which is a variable
     * written two lines above its own use.
     */
    private function isShellLocal(string $name): bool
    {
        return mb_strlen($name) < 2 || $name !== mb_strtoupper($name);
    }

    public function handle(): int
    {
        $templates = Template::with(['variables', 'game'])->orderBy('name')->get();
        $this->info("Auditing {$templates->count()} templates");
        $this->newLine();

        $rows = [];
        $counts = ['ok' => 0, 'warn' => 0, 'broken' => 0];

        foreach ($templates as $template) {
            $problems = array_merge(
                $this->checkImage($template),
                $this->checkUndeclaredVariables($template),
                $this->checkStartup($template),
            );

            if ($this->option('urls')) {
                $problems = array_merge($problems, $this->checkUrls($template));
            }
            if ($this->option('images')) {
                $problems = array_merge($problems, $this->checkImageExists($template));
            }

            $severity = $this->severityOf($problems);
            $counts[$severity]++;

            if ($problems === [] && $this->option('broken')) {
                continue;
            }
            if ($problems === []) {
                continue;
            }

            $rows[] = [
                mb_substr(($template->game?->name ?? '?').' / '.$template->name, 0, 42),
                $severity,
                mb_substr(implode('; ', $problems), 0, 90),
            ];
        }

        if ($rows) {
            $this->table(['Template', 'Severity', 'Problem'], $rows);
        }

        $this->newLine();
        $this->info(sprintf(
            '%d clean, %d with warnings, %d that cannot work as written.',
            $counts['ok'], $counts['warn'], $counts['broken']
        ));

        if (! $this->option('urls') || ! $this->option('images')) {
            $this->line('Add --urls and --images for the network checks, which are slower and catch dead downloads.');
        }

        return self::SUCCESS;
    }

    private function severityOf(array $problems): string
    {
        if ($problems === []) {
            return 'ok';
        }

        foreach ($problems as $p) {
            if (str_starts_with($p, 'BROKEN')) {
                return 'broken';
            }
        }

        return 'warn';
    }

    /** A Docker template with no image cannot start at all. */
    private function checkImage(Template $template): array
    {
        if ($template->runtime !== 'docker') {
            return [];
        }

        return $template->defaultImage()
            ? []
            : ['BROKEN: docker runtime with no image'];
    }

    /**
     * Variables the install script reads that the template does not declare.
     *
     * This is the SA-MP bug, and it is silent: an unset variable expands to
     * nothing, the URL it was part of becomes malformed, curl fetches an error
     * page, tar fails, and the script exits 0 anyway.
     */
    private function checkUndeclaredVariables(Template $template): array
    {
        $script = (string) $template->script_install;
        if (trim($script) === '') {
            return [];
        }

        // Kept in the ORIGINAL case. Uppercasing both sides is how the first
        // version of this check missed SA-MP: the whole bug is that the script
        // reads $VERSION while the template declares "Version", and normalising
        // case makes the two look identical.
        $declared = $template->variables->pluck('env_variable')->map(fn ($v) => (string) $v)->all();
        $declaredUpper = array_map('mb_strtoupper', $declared);

        // ${VAR}, ${VAR:-default} and bare $VAR. Anything with a :- default is
        // captured separately: an unset variable with a default is deliberate,
        // not a fault, and most of the catalogue's noise is exactly that.
        preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)\s*:?[-=]/', $script, $withDefault);
        preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', $script, $braced);
        preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $script, $bare);

        $defaulted = array_map('mb_strtoupper', $withDefault[1] ?? []);
        $read = array_unique(array_merge($braced[1] ?? [], $bare[1] ?? []));

        $missing = [];
        $nearMiss = [];
        foreach ($read as $name) {
            $upper = mb_strtoupper($name);

            if (in_array($name, $declared, true) || in_array($upper, self::PROVIDED, true)) {
                continue;
            }
            if ($this->isShellLocal($name)) {
                continue;
            }

            // Declared, but not with this spelling. This is the SA-MP bug and
            // it is always real: the shell is case sensitive and the variable
            // the script reads will be empty.
            if (in_array($upper, $declaredUpper, true)) {
                $nearMiss[] = $name.' (declared as '.$declared[array_search($upper, $declaredUpper, true)].')';

                continue;
            }

            // Assigned inside the script itself, so it is not an input at all.
            if (preg_match('/(^|\n)\s*(export\s+|local\s+)?'.preg_quote($name, '/').'=/', $script)) {
                continue;
            }
            // Read with a default, so unset is handled by the script.
            if (in_array($upper, $defaulted, true)) {
                continue;
            }
            $missing[] = $name;
        }

        $out = [];
        if ($nearMiss) {
            $out[] = 'BROKEN: script reads $'.implode(', $', $nearMiss);
        }
        if ($missing) {
            $out[] = 'script reads undeclared '.implode(', ', array_slice($missing, 0, 4));
        }

        return $out;
    }

    /** A startup command reading a variable nobody sets starts the wrong server. */
    private function checkStartup(Template $template): array
    {
        $startup = (string) $template->startup;
        if (trim($startup) === '') {
            return ['BROKEN: no startup command'];
        }

        $declared = $template->variables->pluck('env_variable')
            ->map(fn ($v) => mb_strtoupper((string) $v))->all();

        preg_match_all('/\{\{\s*([A-Z_][A-Z0-9_]*)\s*\}\}/i', $startup, $braces);
        preg_match_all('/\$\{?([A-Z_][A-Z0-9_]*)/i', $startup, $dollars);
        $read = array_unique(array_merge($braces[1] ?? [], $dollars[1] ?? []));

        $missing = [];
        foreach ($read as $name) {
            $upper = mb_strtoupper($name);
            if (in_array($upper, self::PROVIDED, true) || in_array($upper, $declared, true)) {
                continue;
            }
            if ($this->isShellLocal($name)) {
                continue;
            }
            // Assigned by the startup command itself, which is how a multi
            // statement startup sets up its own arguments.
            if (preg_match('/(^|\n)\s*'.preg_quote($name, '/').'=/', $startup)) {
                continue;
            }
            $missing[] = $name;
        }

        return $missing
            ? ['startup reads undeclared '.implode(', ', array_slice($missing, 0, 4))]
            : [];
    }

    /**
     * Does anything the script downloads still exist?
     *
     * Four things made the first version of this useless, and it reported MTA as
     * broken, which is the one template that has actually been installed and
     * run. Each is fixed below and each was confirmed by hand.
     */
    private function checkUrls(Template $template): array
    {
        $script = (string) $template->script_install;
        preg_match_all('#https?://[^\s"\'`\\){}|]+#i', $script, $m);

        $urls = [];
        foreach (array_unique($m[0] ?? []) as $url) {
            // Still holding a shell variable: what it resolves to depends on
            // values we do not have here.
            if (str_contains($url, '$')) {
                continue;
            }

            $host = (string) parse_url($url, PHP_URL_HOST);
            $path = (string) parse_url($url, PHP_URL_PATH);

            // An API root is not a download. The script appends a path built at
            // runtime, so the bare host 404s or 401s and means nothing.
            // api.curseforge.com and api.modrinth.com both did.
            if (str_starts_with($host, 'api.')) {
                continue;
            }

            // Needs a real path. The regex once captured a bare
            // "wizards.cdn.spacestation14.com/" and then reported the template
            // dead because a hostname with no file on it 404s.
            if (trim($path, '/') === '') {
                continue;
            }

            $urls[] = $url;
        }

        if (! $urls) {
            return [];
        }

        $dead = [];
        foreach ($urls as $url) {
            if (! $this->urlAnswers($url)) {
                $dead[] = (string) parse_url($url, PHP_URL_HOST);
            }
        }

        if (! $dead) {
            return [];
        }

        // ALL of them, or it is not broken.
        //
        // MTA names four URLs: three answer and one is a version pinned nightly
        // that has rotated away. The script uses a working one and the install
        // succeeds. Flagging on any single failure is what produced a confident
        // "broken" on a template running right now.
        if (count($dead) === count($urls)) {
            return ['BROKEN: every download in this script is gone ('.implode(', ', array_unique($dead)).')'];
        }

        return [count($dead).' of '.count($urls).' downloads dead ('.implode(', ', array_unique($dead)).')'];
    }

    /**
     * Is there a file at the other end of this URL?
     *
     * A ranged GET, not a HEAD. A download is a GET and plenty of hosts do not
     * implement HEAD at all: changelogs-live.fivem.net answers 405 to HEAD and
     * 200 to GET, which had FiveM and RedM both reported dead.
     *
     * 401 and 403 count as alive. Authentication required is not the same as
     * gone, and a script that supplies a key will get through where this cannot.
     */
    private function urlAnswers(string $url): bool
    {
        try {
            $status = Http::timeout(15)
                ->withHeaders(['Range' => 'bytes=0-0'])
                ->withOptions(['allow_redirects' => true])
                ->get($url)
                ->status();
        } catch (\Throwable) {
            return false;
        }

        return $status < 400 || in_array($status, [401, 403], true);
    }

    /** Does the image still exist, without pulling a gigabyte to find out? */
    private function checkImageExists(Template $template): array
    {
        $image = $template->defaultImage();
        if (! $image || $template->runtime !== 'docker') {
            return [];
        }

        // Only ghcr, which is most of the catalogue and needs no token for a
        // public manifest. Docker Hub wants an auth dance that is not worth it
        // for a check.
        if (! str_starts_with($image, 'ghcr.io/')) {
            return [];
        }

        [$path, $tag] = array_pad(explode(':', mb_substr($image, strlen('ghcr.io/')), 2), 2, 'latest');

        try {
            $token = Http::timeout(12)->get("https://ghcr.io/token?scope=repository:{$path}:pull")->json('token');
            $status = Http::timeout(12)->withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.oci.image.index.v1+json, application/vnd.docker.distribution.manifest.list.v2+json'])
                ->get("https://ghcr.io/v2/{$path}/manifests/{$tag}")->status();
        } catch (\Throwable) {
            return [];
        }

        return $status >= 400 ? ["BROKEN: image {$image} is gone (HTTP {$status})"] : [];
    }
}
