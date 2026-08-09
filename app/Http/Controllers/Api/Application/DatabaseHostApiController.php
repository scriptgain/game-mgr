<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DatabaseHostResource;
use App\Models\AuditLog;
use App\Models\DatabaseHost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * DatabaseHost over the API. The rules match the admin screen's, because an API that
 * validates more loosely than the form is an API that writes rows the panel
 * would have refused.
 */
class DatabaseHostApiController extends ApiController
{
    public function index(Request $request)
    {
        $query = DatabaseHost::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('id');

        return $this->paginate($request, $query, DatabaseHostResource::class);
    }

    public function show(DatabaseHost $host)
    {
        return $this->one($host, DatabaseHostResource::class);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $host = DatabaseHost::create($data);

        AuditLog::record('database_hosts.create', 'Created "'.($host->name ?? '').'" over the API', $host);

        return response()->json($this->one($host, DatabaseHostResource::class), 201);
    }

    public function update(Request $request, DatabaseHost $host)
    {
        $data = $this->validated($request, $host);
        $host->update($data);

        AuditLog::record('database_hosts.update', 'Updated "'.($host->name ?? '').'" over the API', $host);

        return $this->one($host->fresh(), DatabaseHostResource::class);
    }

    public function destroy(DatabaseHost $host)
    {
        $name = $host->name ?? '';
        $host->delete();

        AuditLog::record('database_hosts.delete', 'Deleted "'.$name.'" over the API');

        return $this->done();
    }

    /**
     * The request body, in one place so the API reference can describe it.
     *
     * Static and public because two callers need it: validation here, and
     * the OpenAPI document, which would otherwise have to parse this file.
     * $subject carries the record being updated, for the rules that have to
     * ignore it.
     *
     * @return array<string,mixed>
     */
    public static function rules(string $action = 'store', mixed $subject = null): array
    {
        $model = $subject instanceof DatabaseHost ? $subject : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:64'],
            'password' => [$model ? 'nullable' : 'required', 'string', 'max:255'],
            'linked_ip' => ['nullable', 'string', 'max:255'],
            'node_id' => ['nullable', 'exists:nodes,id'],
            'max_databases' => ['required', 'integer', 'min:0'],
        ];
    }

    private function validated(Request $request, ?DatabaseHost $model): array
    {
        $data = $request->validate(static::rules($model ? 'update' : 'store', $model));

        // password is write only. It is never returned by the resource, and a
        // blank one on update leaves the stored value alone rather than wiping
        // it, so a caller patching one field does not clear the credential.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
