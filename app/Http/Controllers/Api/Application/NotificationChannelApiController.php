<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\NotificationChannelResource;
use App\Models\AuditLog;
use App\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * NotificationChannel over the API. The rules match the admin screen's, because an API that
 * validates more loosely than the form is an API that writes rows the panel
 * would have refused.
 */
class NotificationChannelApiController extends ApiController
{
    public function index(Request $request)
    {
        $query = NotificationChannel::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('id');

        return $this->paginate($request, $query, NotificationChannelResource::class);
    }

    public function show(NotificationChannel $channel)
    {
        return $this->one($channel, NotificationChannelResource::class);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $channel = NotificationChannel::create($data);

        AuditLog::record('notification_channels.create', 'Created "'.($channel->name ?? '').'" over the API', $channel);

        return response()->json($this->one($channel, NotificationChannelResource::class), 201);
    }

    public function update(Request $request, NotificationChannel $channel)
    {
        $data = $this->validated($request, $channel);
        $channel->update($data);

        AuditLog::record('notification_channels.update', 'Updated "'.($channel->name ?? '').'" over the API', $channel);

        return $this->one($channel->fresh(), NotificationChannelResource::class);
    }

    public function destroy(NotificationChannel $channel)
    {
        $name = $channel->name ?? '';
        $channel->delete();

        AuditLog::record('notification_channels.delete', 'Deleted "'.$name.'" over the API');

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
        $model = $subject instanceof NotificationChannel ? $subject : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:discord,slack,webhook,email'],
            'target' => [$model ? 'nullable' : 'required', 'string', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function validated(Request $request, ?NotificationChannel $model): array
    {
        $data = $request->validate(static::rules($model ? 'update' : 'store', $model));

        // These columns are NOT NULL with no database default, so an absent
        // array or flag has to become empty rather than null. The form always
        // posts them; an API caller reasonably does not.
        $data['events'] = $data['events'] ?? [];
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        // target is write only. It is never returned by the resource, and a
        // blank one on update leaves the stored value alone rather than wiping
        // it, so a caller patching one field does not clear the credential.
        if (blank($data['target'] ?? null)) {
            unset($data['target']);
        }

        return $data;
    }
}
