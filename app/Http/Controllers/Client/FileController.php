<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Http\Request;

/**
 * The file manager. Browses the server's data directory through the node
 * daemon; the panel never touches the node's filesystem itself.
 */
class FileController extends ServerController
{
    /** Paths a template marks as untouchable, plus the obvious traversal guard. */
    private function assertSafe(Server $server, string $path): string
    {
        $path = '/'.ltrim(str_replace('\\', '/', $path), '/');

        // A single normalisation pass is not enough: "....//" collapses to
        // "../" the second time around, which is the classic bypass.
        while (str_contains($path, '..')) {
            $path = str_replace('..', '', $path);
        }
        $path = preg_replace('#/+#', '/', $path);

        foreach ($server->template?->file_denylist ?? [] as $blocked) {
            if (str_starts_with($path, '/'.ltrim($blocked, '/'))) {
                abort(403, 'That path is protected by this template.');
            }
        }

        return $path === '' ? '/' : $path;
    }

    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'file.read');

        $path = $this->assertSafe($server, (string) $request->query('path', '/'));

        return view('server.files', [
            'title' => $server->name.' Files',
            'server' => $server->load('node'),
            'path' => $path,
            'entries' => NodeClient::for($server->node)->listFiles($server, $path),
            'crumbs' => $this->crumbs($path),
        ]);
    }

    public function edit(Request $request, Server $server)
    {
        $this->guard($server, 'file.read');

        $path = $this->assertSafe($server, (string) $request->query('path', '/'));
        $content = NodeClient::for($server->node)->readFile($server, $path);

        if ($content === null) {
            return redirect()->route('server.files', [$server, 'path' => dirname($path)])
                ->with('error', 'That file could not be read.');
        }

        return view('server.file-edit', [
            'title' => basename($path),
            'server' => $server->load('node'),
            'path' => $path,
            'content' => $content,
            'crumbs' => $this->crumbs($path),
            'readOnly' => ! auth()->user()->can('check', [$server, 'file.update']),
        ]);
    }

    public function save(Request $request, Server $server)
    {
        $this->guard($server, 'file.update');

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
        ]);

        $path = $this->assertSafe($server, $data['path']);

        if (! NodeClient::for($server->node)->writeFile($server, $path, (string) ($data['content'] ?? ''))) {
            return back()->with('error', 'The node refused the write.');
        }

        $this->log($server, 'file.write', 'Edited '.$path);

        return redirect()->route('server.files.edit', [$server, 'path' => $path])->with('status', 'Saved.');
    }

    public function mkdir(Request $request, Server $server)
    {
        $this->guard($server, 'file.create');

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/'],
        ]);

        $full = $this->assertSafe($server, rtrim($data['path'], '/').'/'.$data['name']);

        if (! NodeClient::for($server->node)->makeDir($server, $full)) {
            return back()->with('error', 'The node refused to create that folder.');
        }

        $this->log($server, 'file.mkdir', 'Created folder '.$full);

        return back()->with('status', 'Folder created.');
    }

    public function rename(Request $request, Server $server)
    {
        $this->guard($server, 'file.update');

        $data = $request->validate([
            'from' => ['required', 'string', 'max:1000'],
            'to' => ['required', 'string', 'max:1000'],
        ]);

        $from = $this->assertSafe($server, $data['from']);
        $to = $this->assertSafe($server, $data['to']);

        if (! NodeClient::for($server->node)->renameFile($server, $from, $to)) {
            return back()->with('error', 'The node refused the rename.');
        }

        $this->log($server, 'file.rename', 'Renamed '.$from.' to '.$to);

        return back()->with('status', 'Renamed.');
    }

    public function destroy(Request $request, Server $server)
    {
        $this->guard($server, 'file.delete');

        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['string', 'max:1000'],
        ]);

        $paths = array_map(fn ($p) => $this->assertSafe($server, $p), $data['paths']);

        if (! NodeClient::for($server->node)->deleteFiles($server, $paths)) {
            return back()->with('error', 'The node refused the delete.');
        }

        $this->log($server, 'file.delete', 'Deleted '.count($paths).' '.\Illuminate\Support\Str::plural('item', count($paths)));

        return back()->with('status', count($paths).' '.\Illuminate\Support\Str::plural('item', count($paths)).' deleted.');
    }

    /** Breadcrumb segments for the current path. */
    private function crumbs(string $path): array
    {
        $out = [['name' => 'Home', 'path' => '/']];
        $walk = '';
        foreach (array_filter(explode('/', $path)) as $segment) {
            $walk .= '/'.$segment;
            $out[] = ['name' => $segment, 'path' => $walk];
        }

        return $out;
    }
}
