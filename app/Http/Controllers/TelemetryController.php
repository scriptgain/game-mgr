<?php

namespace App\Http\Controllers;

use App\Services\Telemetry;
use Illuminate\Http\Request;

/**
 * The page that makes telemetry auditable.
 *
 * It shows the switch, what would go, and the exact JSON of what did go. That
 * last one is the whole point: telemetry somebody can read is telemetry they
 * accept, and telemetry they discover is the kind they write posts about.
 */
class TelemetryController extends Controller
{
    public function edit()
    {
        $last = Telemetry::lastSent();

        return view('settings.telemetry', [
            'enabled' => Telemetry::enabled(),
            // Both as text, because the view's job is to show them and a view
            // that encodes JSON is a view doing work.
            'payloadJson' => $this->pretty(Telemetry::payload()),
            'lastJson' => $last ? $this->pretty($last) : '',
            'lastAt' => Telemetry::lastSentAt(),
            'endpoint' => Telemetry::ENDPOINT,
        ]);
    }

    private function pretty(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function update(Request $request)
    {
        Telemetry::setEnabled($request->boolean('telemetry_enabled'));

        return redirect()->route('settings.telemetry.edit')->with(
            'status',
            Telemetry::enabled()
                ? 'Telemetry is on. Counts only, and this page always shows the last thing that went.'
                : 'Telemetry is off. Nothing at all will be sent.'
        );
    }

    /** Send now, so somebody can see what goes rather than waiting a day. */
    public function send()
    {
        if (! Telemetry::enabled()) {
            return back()->with('warning', 'Telemetry is off, so nothing was sent.');
        }

        // The return value is not reported. Whether scriptgain.com answered is
        // not the operator's problem, and the payload below is recorded either
        // way, which is the part they actually came here to see.
        Telemetry::send(true);

        return redirect()->route('settings.telemetry.edit')
            ->with('status', 'Sent. Below is exactly what went.');
    }
}
