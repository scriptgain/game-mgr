<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Where alerts go. Discord, Slack, a generic webhook, or email.
 */
class ChannelController extends Controller
{
    public function index()
    {
        return view('admin.channels.index', [
            'title' => 'Notification Channels',
            'channels' => NotificationChannel::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.channels.form', [
            'title' => 'New Channel',
            'channel' => new NotificationChannel(['type' => 'discord', 'is_active' => true, 'events' => array_keys(NotificationChannel::EVENTS)]),
        ]);
    }

    public function store(Request $request)
    {
        NotificationChannel::create($this->validated($request));

        return redirect()->route('admin.channels.index')->with('status', 'Channel created.');
    }

    public function edit(NotificationChannel $channel)
    {
        return view('admin.channels.form', ['title' => 'Edit '.$channel->name, 'channel' => $channel]);
    }

    public function update(Request $request, NotificationChannel $channel)
    {
        $data = $this->validated($request, $channel);
        if (blank($data['target'] ?? null)) {
            unset($data['target']);
        }
        $channel->update($data);

        return redirect()->route('admin.channels.index')->with('status', 'Channel updated.');
    }

    public function destroy(NotificationChannel $channel)
    {
        $channel->delete();

        return back()->with('status', 'Channel deleted.');
    }

    /** Send a real message, because a channel nobody has tested is decoration. */
    public function test(NotificationChannel $channel)
    {
        $text = config('brand.name').' test message. If you are reading this, the "'.$channel->name.'" channel works.';

        try {
            match ($channel->type) {
                'discord' => Http::timeout(8)->post($channel->target, ['content' => $text]),
                'slack' => Http::timeout(8)->post($channel->target, ['text' => $text]),
                'webhook' => Http::timeout(8)->post($channel->target, ['event' => 'test', 'message' => $text]),
                'email' => Mail::raw($text, fn ($m) => $m->to($channel->target)->subject(config('brand.name').' Test Alert')),
                default => null,
            };
        } catch (\Throwable $e) {
            return back()->with('error', 'That channel did not accept the message: '.$e->getMessage());
        }

        $channel->update(['last_used_at' => now()]);

        return back()->with('status', 'Test message sent to '.$channel->name.'.');
    }

    private function validated(Request $request, ?NotificationChannel $channel = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:discord,slack,webhook,email'],
            'target' => [$channel ? 'nullable' : 'required', 'string', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['events'] = array_values(array_intersect($data['events'] ?? [], array_keys(NotificationChannel::EVENTS)));

        return $data;
    }
}
