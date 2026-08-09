<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\MountResource;
use App\Models\AuditLog;
use App\Models\Mount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mount over the API. The rules match the admin screen's, because an API that
 * validates more loosely than the form is an API that writes rows the panel
 * would have refused.
 */
class MountApiController extends ApiController
{
    public function index(Request $request)
    {
        $query = Mount::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('id');

        return $this->paginate($request, $query, MountResource::class);
    }

    public function show(Mount $mount)
    {
        return $this->one($mount, MountResource::class);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $mount = Mount::create($data);

        AuditLog::record('mounts.create', 'Created "'.($mount->name ?? '').'" over the API', $mount);

        return response()->json($this->one($mount, MountResource::class), 201);
    }

    public function update(Request $request, Mount $mount)
    {
        $data = $this->validated($request, $mount);
        $mount->update($data);

        AuditLog::record('mounts.update', 'Updated "'.($mount->name ?? '').'" over the API', $mount);

        return $this->one($mount->fresh(), MountResource::class);
    }

    public function destroy(Mount $mount)
    {
        $name = $mount->name ?? '';
        $mount->delete();

        AuditLog::record('mounts.delete', 'Deleted "'.$name.'" over the API');

        return $this->done();
    }

    private function validated(Request $request, ?Mount $model): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', 'max:255'],
            'read_only' => ['nullable', 'boolean'],
            'user_mountable' => ['nullable', 'boolean'],
        ]);

        // These columns are NOT NULL with no database default, so an absent
        // array or flag has to become empty rather than null. The form always
        // posts them; an API caller reasonably does not.
        $data['read_only'] = (bool) ($data['read_only'] ?? false);
        $data['user_mountable'] = (bool) ($data['user_mountable'] ?? false);

        return $data;
    }
}
