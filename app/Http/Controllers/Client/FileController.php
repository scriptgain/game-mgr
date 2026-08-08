<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Services\NodeClient;
use App\Support\Format;
use App\Support\UploadLimit;
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

        $server->load('node');

        return view('server.files', [
            'title' => $server->name.' Files',
            'server' => $server,
            'path' => $path,
            'entries' => NodeClient::for($server->node)->listFiles($server, $path),
            'crumbs' => $this->crumbs($path),
            // The browser refuses an oversized file before it starts sending,
            // so nobody watches a progress bar crawl to a rejection.
            'uploadLimit' => UploadLimit::effectiveBytes($server->node),
            'uploadShortfall' => UploadLimit::shortfall($server->node),
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

    /**
     * Create an empty file and drop straight into the editor.
     *
     * Straight into the editor because an empty file is never the thing anybody
     * wanted; typing in it is. Two refusals rather than one: a name that already
     * exists would otherwise truncate somebody's config file to nothing, and a
     * name containing a separator is a path, and this creates a file in the
     * folder being looked at.
     */
    public function create(Request $request, Server $server)
    {
        $this->guard($server, 'file.create');

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Checked here rather than by a validation rule so the answer lands in
        // the page's own error banner. A rule error would go to $errors, which
        // on this page is only rendered inside a modal that is closed by the
        // time the redirect arrives, so the refusal would be invisible.
        $name = trim($data['name']);
        if ($name === '' || preg_match('#[/\\\\]#', $name) === 1) {
            return back()->withInput()
                ->with('error', 'A file name cannot contain a slash. Open the folder you want it in first.');
        }
        if ($name === '.' || $name === '..') {
            return back()->withInput()->with('error', 'That is not a usable file name.');
        }

        $directory = $this->assertSafe($server, $data['path']);
        $full = $this->assertSafe($server, rtrim($directory, '/').'/'.$name);

        $client = NodeClient::for($server->node);

        foreach ($client->listFiles($server, $directory) as $entry) {
            if (($entry['name'] ?? '') === $name) {
                return back()->withInput()->with('error', $name.' already exists in this folder.');
            }
        }

        if (! $client->writeFile($server, $full, '')) {
            return back()->withInput()->with('error', 'The node refused to create that file.');
        }

        $this->log($server, 'file.create', 'Created '.$full);

        return redirect()->route('server.files.edit', [$server, 'path' => $full])
            ->with('status', 'File created. It is empty until you save something into it.');
    }

    /**
     * Receive an uploaded file and stream it to the node.
     *
     * Answers JSON because the browser sends this over XHR to get a progress
     * bar: an upload that appears to do nothing for two minutes reads as broken.
     *
     * The node's own upload_size is the cap, floored by whatever PHP on this box
     * will physically accept. Advertising a limit the panel cannot honour is
     * worse than a low limit, because past post_max_size PHP throws the request
     * body away and the failure arrives as "no file was sent".
     */
    public function upload(Request $request, Server $server)
    {
        $this->guard($server, 'file.create');

        $server->load('node');
        $limit = UploadLimit::effectiveBytes($server->node);

        // Checked before validation, because if PHP discarded the body then
        // there is no file to validate and the honest answer is about size.
        if (UploadLimit::bodyWasDiscarded((int) $request->server('CONTENT_LENGTH', 0))) {
            return response()->json([
                'ok' => false,
                'error' => 'That upload was larger than this panel accepts ('.Format::bytes($limit).').'
                    .' '.(UploadLimit::shortfall($server->node) ?? ''),
            ], 413);
        }

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1000'],
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        $name = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));

        if ($name === '' || $name === '.' || $name === '..') {
            return response()->json(['ok' => false, 'error' => 'That file has no usable name.'], 422);
        }
        if ($file->getSize() > $limit) {
            return response()->json([
                'ok' => false,
                'error' => $name.' is '.Format::bytes($file->getSize()).', over the '.Format::bytes($limit).' limit for this node.',
            ], 413);
        }

        $full = $this->assertSafe($server, rtrim($data['path'], '/').'/'.$name);

        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return response()->json(['ok' => false, 'error' => 'That upload could not be read back off disk.'], 500);
        }

        try {
            $result = NodeClient::for($server->node)->upload($server, $full, $handle, $limit, (int) $file->getSize());
        } finally {
            fclose($handle);
        }

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'The node refused the upload.'], 502);
        }

        $this->log($server, 'file.upload', 'Uploaded '.$full, ['bytes' => $result['bytes'] ?? null]);

        return response()->json([
            'ok' => true,
            'name' => $name,
            'path' => $full,
            'bytes' => $result['bytes'] ?? $file->getSize(),
        ]);
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
