<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\WatchdogRuleResource;
use App\Models\AuditLog;
use App\Models\WatchdogRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * WatchdogRule over the API. The rules match the admin screen's, because an API that
 * validates more loosely than the form is an API that writes rows the panel
 * would have refused.
 */
class WatchdogRuleApiController extends ApiController
{
    public function index(Request $request)
    {
        $query = WatchdogRule::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('id');

        return $this->paginate($request, $query, WatchdogRuleResource::class);
    }

    public function show(WatchdogRule $rule)
    {
        return $this->one($rule, WatchdogRuleResource::class);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $rule = WatchdogRule::create($data);

        AuditLog::record('watchdog_rules.create', 'Created "'.($rule->name ?? '').'" over the API', $rule);

        return response()->json($this->one($rule, WatchdogRuleResource::class), 201);
    }

    public function update(Request $request, WatchdogRule $rule)
    {
        $data = $this->validated($request, $rule);
        $rule->update($data);

        AuditLog::record('watchdog_rules.update', 'Updated "'.($rule->name ?? '').'" over the API', $rule);

        return $this->one($rule->fresh(), WatchdogRuleResource::class);
    }

    public function destroy(WatchdogRule $rule)
    {
        $name = $rule->name ?? '';
        $rule->delete();

        AuditLog::record('watchdog_rules.delete', 'Deleted "'.$name.'" over the API');

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
        $model = $subject instanceof WatchdogRule ? $subject : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'server_id' => ['nullable', 'exists:servers,id'],
            'trigger' => ['required', 'in:crash,offline,log_pattern,memory,players_zero,tick_rate'],
            'pattern' => ['nullable', 'string', 'max:255'],
            'threshold' => ['required', 'integer', 'min:0'],
            'grace_seconds' => ['required', 'integer', 'between:0,86400'],
            'action' => ['required', 'in:alert,restart,stop,reinstall'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['exists:notification_channels,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function validated(Request $request, ?WatchdogRule $model): array
    {
        $data = $request->validate(static::rules($model ? 'update' : 'store', $model));

        // These columns are NOT NULL with no database default, so an absent
        // array or flag has to become empty rather than null. The form always
        // posts them; an API caller reasonably does not.
        $data['channels'] = $data['channels'] ?? [];
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
