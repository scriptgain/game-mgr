<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannel;
use App\Models\Webhook;
use App\Support\Edition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Outbound webhooks, for wiring GameMGR into billing or provisioning. Payloads
 * are signed with the secret so a receiver can tell a real call from anyone who
 * guessed the URL.
 */
class WebhookController extends Controller
{
    public function index()
    {
        return view('admin.webhooks.index', [
            'title' => 'Webhooks',
            'webhooks' => Webhook::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.webhooks.form', [
            'title' => 'New Webhook',
            'webhook' => new Webhook(['is_active' => true, 'events' => []]),
        ]);
    }

    public function store(Request $request)
    {
        if (! Edition::allows('webhooks')) {
            $needs = Edition::cheapestWith('webhooks');

            return back()->withInput()->with('error', 'Webhooks are not included in the '.Edition::label().' edition.'
                .($needs ? ' They are included from '.Edition::label($needs).' upwards.' : ''));
        }

        $webhook = Webhook::create($this->validated($request));

        return redirect()->route('admin.webhooks.index')
            ->with('status', 'Webhook created.')
            ->with('webhook_secret', $webhook->secret);
    }

    public function edit(Webhook $webhook)
    {
        return view('admin.webhooks.form', ['title' => 'Edit '.$webhook->name, 'webhook' => $webhook]);
    }

    public function update(Request $request, Webhook $webhook)
    {
        $webhook->update($this->validated($request));

        return redirect()->route('admin.webhooks.index')->with('status', 'Webhook updated.');
    }

    public function destroy(Webhook $webhook)
    {
        $webhook->delete();

        return back()->with('status', 'Webhook deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
            'rotate_secret' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['rotate_secret'])) {
            $data['secret'] = Str::random(48);
        }
        unset($data['rotate_secret']);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['events'] = array_values(array_intersect($data['events'] ?? [], array_keys(NotificationChannel::EVENTS)));

        return $data;
    }
}
