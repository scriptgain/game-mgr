<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannel;
use App\Models\Server;
use App\Models\WatchdogRule;
use Illuminate\Http\Request;

/**
 * The watchdog is the biggest single gap in Pterodactyl. It will restart a
 * crashed container and that is the end of its interest: it cannot watch a log
 * for a known corruption message, cannot notice a server has been empty for six
 * hours, and cannot tell anybody about either.
 */
class WatchdogController extends Controller
{
    public function index()
    {
        return view('admin.watchdog.index', [
            'title' => 'Watchdog Rules',
            'rules' => WatchdogRule::with('server')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.watchdog.form', [
            'title' => 'New Watchdog Rule',
            'rule' => new WatchdogRule(['trigger' => 'crash', 'action' => 'restart', 'grace_seconds' => 60, 'is_active' => true]),
            'servers' => Server::orderBy('name')->get(),
            'channels' => NotificationChannel::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        WatchdogRule::create($this->validated($request));

        return redirect()->route('admin.watchdog.index')->with('status', 'Rule created.');
    }

    public function edit(WatchdogRule $rule)
    {
        return view('admin.watchdog.form', [
            'title' => 'Edit '.$rule->name,
            'rule' => $rule,
            'servers' => Server::orderBy('name')->get(),
            'channels' => NotificationChannel::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, WatchdogRule $rule)
    {
        $rule->update($this->validated($request));

        return redirect()->route('admin.watchdog.index')->with('status', 'Rule updated.');
    }

    public function destroy(WatchdogRule $rule)
    {
        $rule->delete();

        return back()->with('status', 'Rule deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
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
        ]);

        // A log-pattern rule with no pattern would match nothing and look like
        // a working rule forever, which is worse than an error.
        if (($data['trigger'] ?? null) === 'log_pattern' && blank($data['pattern'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pattern' => 'A log pattern rule needs something to match on.',
            ]);
        }
        if ($data['trigger'] === 'log_pattern' && @preg_match('/'.$data['pattern'].'/', '') === false) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pattern' => 'That is not a valid regular expression.',
            ]);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['channels'] = $data['channels'] ?? [];

        return $data;
    }
}
