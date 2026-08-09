<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\FileEntryResource;
use App\Models\AuditLog;
use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Http\Request;

/**
 * Files, the one client feature that is not entirely JSON.
 *
 * Listings and the tree operations answer in the usual envelope. Content does
 * not: a download streams and an upload takes a raw body, because base64 inside
 * JSON would triple a four gigabyte modpack in memory on the way through, which
 * is most of why anybody uses a file API.
 *
 * Every path is resolved by the node, not here. The daemon re-checks
 * containment on its own side and does not trust its caller to have sanitised
 * anything, so a bug up here cannot become arbitrary filesystem access down
 * there.
 */
class FileController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'file.read');

        $entries = NodeClient::for($server->node)->listFiles($server, (string) $request->query('path', '/'));

        return [
            'object' => 'list',
            'data' => FileEntryResource::collection(collect($entries))->resolve(),
            'meta' => ['path' => (string) $request->query('path', '/')],
        ];
    }

    /** Streams the bytes. No envelope: this is a file. */
    public function download(Request $request, Server $server)
    {
        $this->guard($server, 'file.read');

        $path = (string) $request->query('path', '');
        abort_if($path === '', 422, 'Name a file to download.');

        $contents = NodeClient::for($server->node)->readFile($server, $path);
        abort_if($contents === null, 404, 'No such file.');

        return response($contents, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
        ]);
    }

    /** Takes a raw body, and the node charges it against the disk limit. */
    public function upload(Request $request, Server $server)
    {
        $this->guard($server, 'file.create');
        $this->refuseIfSuspended($server);

        $path = (string) $request->query('path', '');
        abort_if($path === '', 422, 'Name where the file should go.');

        $result = NodeClient::for($server->node)->upload(
            $server,
            $path,
            $request->getContent(true),
            (int) ($server->node->upload_size ?? 4096) * 1024 * 1024,
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['error'] ?? 'The node refused the upload.',
            ], 422);
        }

        AuditLog::record('file.upload', 'Uploaded '.$path.' to "'.$server->name.'" over the API', $server, $server->id);

        return response()->json(['object' => 'file', 'attributes' => ['path' => $path, 'bytes' => $result['bytes'] ?? null]], 201);
    }

    public function write(Request $request, Server $server)
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present', 'string'],
        ]);

        $this->guard($server, 'file.update');
        $this->refuseIfSuspended($server);

        return NodeClient::for($server->node)->writeFile($server, $data['path'], $data['content'])
            ? $this->done()
            : response()->json(['message' => 'The node refused the write.'], 502);
    }

    public function mkdir(Request $request, Server $server)
    {
        $data = $request->validate(['path' => ['required', 'string']]);
        $this->guard($server, 'file.create');

        return NodeClient::for($server->node)->makeDir($server, $data['path'])
            ? $this->done()
            : response()->json(['message' => 'The node refused that.'], 502);
    }

    public function rename(Request $request, Server $server)
    {
        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);
        $this->guard($server, 'file.update');

        return NodeClient::for($server->node)->renameFile($server, $data['from'], $data['to'])
            ? $this->done()
            : response()->json(['message' => 'The node refused the rename.'], 502);
    }

    public function destroy(Request $request, Server $server)
    {
        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['string'],
        ]);
        $this->guard($server, 'file.delete');

        $ok = NodeClient::for($server->node)->deleteFiles($server, $data['paths']);
        AuditLog::record('file.delete', 'Deleted '.count($data['paths']).' path(s) on "'.$server->name.'" over the API', $server, $server->id);

        return $ok ? $this->done() : response()->json(['message' => 'The node refused the delete.'], 502);
    }

    /**
     * Compress files, and unpack them.
     *
     * file.archive has been a permission with nothing implementing it since the
     * beginning: an administrator could grant it and it did nothing at all.
     */
    public function archive(Request $request, Server $server)
    {
        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['string'],
            'target' => ['nullable', 'string'],
        ]);

        $this->guard($server, 'file.archive');
        $this->refuseIfSuspended($server);

        $target = $data['target'] ?: 'archive-'.now()->format('Ymd-His').'.tar.gz';

        if (! NodeClient::for($server->node)->archive($server, $data['paths'], $target)) {
            return response()->json(['message' => 'The node could not build that archive.'], 502);
        }

        AuditLog::record('file.archive', 'Compressed '.count($data['paths']).' path(s) on "'.$server->name.'" over the API', $server, $server->id);

        return response()->json(['object' => 'file', 'attributes' => ['path' => $target]], 201);
    }

    public function extract(Request $request, Server $server)
    {
        $data = $request->validate(['path' => ['required', 'string']]);

        $this->guard($server, 'file.archive');
        $this->refuseIfSuspended($server);

        if (! NodeClient::for($server->node)->extract($server, $data['path'])) {
            return response()->json([
                'message' => 'The node could not open that archive. It has to be a .zip, .tar or .tar.gz, and nothing inside it may point outside the server.',
            ], 502);
        }

        AuditLog::record('file.archive', 'Extracted '.$data['path'].' on "'.$server->name.'" over the API', $server, $server->id);

        return $this->done();
    }
}
