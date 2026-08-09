<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\WebhookResource;
use App\Models\AuditLog;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Webhook over the API. The rules match the admin screen's, because an API that
 * validates more loosely than the form is an API that writes rows the panel
 * would have refused.
 */
class WebhookApiController extends ApiController
{
    public function index(Request $request)
    {
        $query = Webhook::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('id');

        return $this->paginate($request, $query, WebhookResource::class);
    }

    public function show(Webhook $webhook)
    {
        return $this->one($webhook, WebhookResource::class);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $webhook = Webhook::create($data);

        AuditLog::record('webhooks.create', 'Created "'.($webhook->name ?? '').'" over the API', $webhook);

        return response()->json($this->one($webhook, WebhookResource::class), 201);
    }

    public function update(Request $request, Webhook $webhook)
    {
        $data = $this->validated($request, $webhook);
        $webhook->update($data);

        AuditLog::record('webhooks.update', 'Updated "'.($webhook->name ?? '').'" over the API', $webhook);

        return $this->one($webhook->fresh(), WebhookResource::class);
    }

    public function destroy(Webhook $webhook)
    {
        $name = $webhook->name ?? '';
        $webhook->delete();

        AuditLog::record('webhooks.delete', 'Deleted "'.$name.'" over the API');

        return $this->done();
    }

    private function validated(Request $request, ?Webhook $model): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // These columns are NOT NULL with no database default, so an absent
        // array or flag has to become empty rather than null. The form always
        // posts them; an API caller reasonably does not.
        $data['events'] = $data['events'] ?? [];
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
