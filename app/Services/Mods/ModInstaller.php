<?php

namespace App\Services\Mods;

use App\Models\Mod;
use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Support\Str;

/**
 * Installing, updating, disabling and removing a real file on a real node.
 *
 * The Mods tab is only worth having if these four verbs do the thing their
 * labels say. A panel that records "installed" in a table and leaves the
 * customer to drag a jar into the file manager has moved the work rather than
 * done it, and a panel that deletes a jar when somebody meant "turn this off
 * for one boot" has lost their configuration with it.
 *
 * So, in order:
 *
 *   install  resolves a project to the newest version this server can run,
 *            downloads the primary file, verifies the checksum Modrinth
 *            published, and writes it into plugins/ or mods/ depending on what
 *            the chosen version's own loaders say.
 *
 *   update   does the same and replaces the file, removing the old one when the
 *            filename changed, which it usually does because the version is in
 *            the name.
 *
 *   disable  renames to .disabled. Paper, Spigot and Fabric all skip a file
 *            with that suffix, so this is the convention the servers already
 *            understand, and it is reversible. Deleting is not.
 *
 *   remove   deletes the file and the row.
 *
 * TRANSFER, and this is temporary. NodeClient::writeFile sends the content as a
 * JSON string field, which cannot carry a jar: json_encode refuses malformed
 * UTF-8 and the call fails silently, verified against the dev node. The file
 * therefore goes through NodeClient::upload, the raw streaming endpoint, which
 * hands the open handle to the socket. The download is still buffered to a
 * temporary file on the panel first, because the checksum has to be verified
 * before a single byte reaches the node, and that buffer is what
 * config('mods.max_bytes') bounds.
 *
 * Nothing here throws. Every method answers ['ok' => bool, 'error' => ?string]
 * and a node that is down produces a sentence, not a 500.
 */
class ModInstaller
{
    public function __construct(private readonly Modrinth $modrinth) {}

    /**
     * Install a Modrinth project onto a server.
     *
     * @return array{ok:bool,error?:string,mod?:Mod,message?:string}
     */
    public function install(Server $server, ModTarget $target, string $projectId): array
    {
        if (! $target->supported()) {
            return ['ok' => false, 'error' => self::unsupported($target)];
        }

        $project = $this->modrinth->project($projectId);

        if ($project === null) {
            return ['ok' => false, 'error' => 'Modrinth did not answer for that project, so nothing was installed.'];
        }

        $slug = (string) ($project['slug'] ?? $projectId);

        $already = $server->mods()
            ->where(fn ($q) => $q->where('remote_id', $project['id'])->orWhere('slug', $slug))
            ->exists();

        if ($already) {
            return ['ok' => false, 'error' => ($project['title'] ?? $slug).' is already installed.'];
        }

        $version = $this->modrinth->latestVersion((string) $project['id'], $target);

        if ($version === null) {
            return ['ok' => false, 'error' => self::noVersion($project, $target)];
        }

        $placed = $this->place($server, $target, $version);

        if (! $placed['ok']) {
            return $placed;
        }

        $mod = Mod::create([
            'server_id' => $server->id,
            'source' => 'modrinth',
            'remote_id' => (string) $project['id'],
            'name' => (string) ($project['title'] ?? $slug),
            'slug' => $slug,
            'author' => $this->modrinth->author((string) $project['id']),
            'summary' => Str::limit((string) ($project['description'] ?? ''), 480),
            'version' => (string) ($version['version_number'] ?? $version['id']),
            'latest_version' => (string) ($version['version_number'] ?? $version['id']),
            'path' => $placed['path'],
            'bytes' => $placed['bytes'],
            'enabled' => true,
            'installed_at' => now(),
            'checked_at' => now(),
        ]);

        return [
            'ok' => true,
            'mod' => $mod,
            'message' => $mod->name.' '.$mod->version.' installed to '.$placed['path'].
                '. Restart the server to load it.',
        ];
    }

    /**
     * Fetch the newest compatible version and replace the installed file.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public function update(Server $server, ModTarget $target, Mod $mod): array
    {
        if ($mod->source !== 'modrinth' || ! $mod->remote_id) {
            return ['ok' => false, 'error' => $mod->name.' was not installed from Modrinth, so it cannot be updated here.'];
        }

        if (! $target->supported()) {
            return ['ok' => false, 'error' => self::unsupported($target)];
        }

        $version = $this->modrinth->latestVersion($mod->remote_id, $target);

        if ($version === null) {
            return ['ok' => false, 'error' => 'Modrinth did not answer for '.$mod->name.', so nothing was changed.'];
        }

        $number = (string) ($version['version_number'] ?? $version['id']);
        $wasDisabled = ! $mod->enabled;

        if ($number === $mod->version) {
            $mod->update(['latest_version' => $number, 'checked_at' => now()]);

            return ['ok' => true, 'message' => $mod->name.' is already on the newest version for this server.'];
        }

        $placed = $this->place($server, $target, $version, disabled: $wasDisabled);

        if (! $placed['ok']) {
            return $placed;
        }

        $from = $mod->version;
        $old = $mod->path;

        // Only after the new file is safely on disk. Removing first would leave
        // a server with no plugin at all if the download turned out to be bad.
        if ($old && $old !== $placed['path']) {
            NodeClient::for($server->node)->deleteFiles($server, [$old]);
        }

        $mod->update([
            'version' => $number,
            'latest_version' => $number,
            'path' => $placed['path'],
            'bytes' => $placed['bytes'],
            'installed_at' => now(),
            'checked_at' => now(),
        ]);

        return ['ok' => true, 'message' => $mod->name.' updated from '.$from.' to '.$number.'. Restart the server to load it.'];
    }

    /**
     * Turn a mod on or off by renaming its file.
     *
     * `.disabled` is not an invention here. Bukkit, Paper and Fabric all ignore
     * a file that does not end in .jar, and .disabled is the suffix the
     * community settled on, so the file stays exactly where it was and the
     * decision is one rename away from being reversed.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public function setEnabled(Server $server, Mod $mod, bool $enabled): array
    {
        if ($mod->enabled === $enabled) {
            return ['ok' => true, 'message' => $mod->name.($enabled ? ' is already enabled.' : ' is already disabled.')];
        }

        $from = (string) $mod->path;
        $to = $enabled ? self::withoutDisabled($from) : self::withDisabled($from);

        if ($from === '' || $from === $to) {
            // Nothing on disk to rename, which is the case for rows seeded
            // before the installer existed. The flag still flips so the list
            // is not stuck, and the message says the file was not touched.
            $mod->update(['enabled' => $enabled]);

            return ['ok' => true, 'message' => $mod->name.($enabled ? ' enabled.' : ' disabled.').' No file was recorded for it, so nothing was renamed.'];
        }

        if (! NodeClient::for($server->node)->renameFile($server, $from, $to)) {
            return ['ok' => false, 'error' => 'The node did not rename '.basename($from).', so '.$mod->name.' was left as it was.'];
        }

        $mod->update(['enabled' => $enabled, 'path' => $to]);

        return [
            'ok' => true,
            'message' => $mod->name.($enabled ? ' enabled' : ' disabled').' as '.basename($to).'. Restart the server to apply it.',
        ];
    }

    /**
     * Delete the file and the row.
     *
     * @return array{ok:bool,error?:string,message?:string}
     */
    public function remove(Server $server, Mod $mod): array
    {
        $name = $mod->name;
        $path = (string) $mod->path;

        if ($path !== '' && ! NodeClient::for($server->node)->deleteFiles($server, [$path])) {
            return ['ok' => false, 'error' => 'The node did not delete '.basename($path).', so '.$name.' is still installed.'];
        }

        $mod->delete();

        return ['ok' => true, 'message' => $name.' removed. Restart the server to unload it.'];
    }

    /**
     * Refresh what Modrinth says the newest compatible version is, without
     * touching a file. This is what puts "Update Ready" on the list.
     *
     * @return int How many rows were found to have an update waiting.
     */
    public function refresh(Server $server, ModTarget $target): int
    {
        if (! $target->supported()) {
            return 0;
        }

        $waiting = 0;

        foreach ($server->mods()->where('source', 'modrinth')->whereNotNull('remote_id')->get() as $mod) {
            $version = $this->modrinth->latestVersion((string) $mod->remote_id, $target);

            if ($version === null) {
                continue;
            }

            $number = (string) ($version['version_number'] ?? $version['id']);
            $mod->update(['latest_version' => $number, 'checked_at' => now()]);

            if ($number !== $mod->version) {
                $waiting++;
            }
        }

        return $waiting;
    }

    // ---------------------------------------------------------------- inside

    /**
     * Download one version's primary file, verify it, and put it on the node.
     *
     * @param  array<string,mixed>  $version
     * @return array{ok:bool,error?:string,path?:string,bytes?:int}
     */
    private function place(Server $server, ModTarget $target, array $version, bool $disabled = false): array
    {
        $file = Modrinth::primaryFile($version);

        if ($file === null) {
            return ['ok' => false, 'error' => 'That version has no downloadable file on Modrinth.'];
        }

        $loaders = array_values(array_filter((array) ($version['loaders'] ?? []), 'is_string'));
        $directory = $target->directoryFor($loaders);

        if ($directory === null) {
            // A hybrid server with a file whose loaders say nothing useful.
            // Refusing beats writing a jar into a directory chosen by coin toss.
            return ['ok' => false, 'error' => 'This server runs both plugins and mods, and that file does not say which it is, so it was not installed.'];
        }

        $maxBytes = self::maxBytes();
        $download = $this->modrinth->download($file, $maxBytes);

        if (! $download['ok']) {
            return ['ok' => false, 'error' => (string) $download['error']];
        }

        $temporary = (string) $download['path'];
        $path = '/'.$directory.'/'.self::safeFilename($file['filename']).($disabled ? '.disabled' : '');

        $client = NodeClient::for($server->node);
        $client->makeDir($server, '/'.$directory);

        $handle = @fopen($temporary, 'rb');

        if ($handle === false) {
            @unlink($temporary);

            return ['ok' => false, 'error' => 'The panel could not read the file it just downloaded.'];
        }

        $result = $client->upload($server, $path, $handle, $maxBytes, (int) $download['bytes']);

        if (is_resource($handle)) {
            fclose($handle);
        }

        @unlink($temporary);

        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'The node refused the file.')];
        }

        return ['ok' => true, 'path' => $path, 'bytes' => (int) ($result['bytes'] ?? $download['bytes'])];
    }

    /** The ceiling on a catalogue install, in bytes. */
    public static function maxBytes(): int
    {
        return max(1, (int) config('mods.max_bytes', 64 * 1024 * 1024));
    }

    /**
     * A filename that cannot leave the directory it was meant for.
     *
     * The name comes from a third party and ends up in a path, so it is reduced
     * to its basename and anything that is not a plain filename character is
     * flattened. A jar called ../../server.properties is not a hypothetical.
     */
    private static function safeFilename(string $filename): string
    {
        $name = basename(str_replace('\\', '/', trim($filename)));
        $name = preg_replace('/[^A-Za-z0-9._+\-]/', '_', $name) ?? '';
        $name = ltrim($name, '.');

        return $name === '' ? 'mod.jar' : Str::limit($name, 180, '');
    }

    private static function withDisabled(string $path): string
    {
        return str_ends_with($path, '.disabled') ? $path : $path.'.disabled';
    }

    private static function withoutDisabled(string $path): string
    {
        return str_ends_with($path, '.disabled') ? substr($path, 0, -strlen('.disabled')) : $path;
    }

    private static function unsupported(ModTarget $target): string
    {
        if (! $target->usesModrinth()) {
            return 'This template does not list Modrinth as a mod source, so nothing can be installed from it.';
        }

        return 'GameMGR cannot tell which mod loader this server runs, so it will not guess where a file belongs. Set the server type on the Startup tab first.';
    }

    /** @param array<string,mixed> $project */
    private static function noVersion(array $project, ModTarget $target): string
    {
        $name = (string) ($project['title'] ?? 'That project');

        return $target->versionKnown()
            ? $name.' has no release for '.$target->loaderLabel.' on Minecraft '.$target->gameVersion.'.'
            : $name.' has no release for '.$target->loaderLabel.'.';
    }
}
