<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;

class AlertController extends Controller
{
    public function index()
    {
        return view('admin.alerts.index', [
            'title' => 'Alerts',
            'open' => Alert::with(['server', 'node', 'rule'])->whereNull('acknowledged_at')->latest('id')->get(),
            'recent' => Alert::with(['server', 'node'])->whereNotNull('acknowledged_at')->latest('acknowledged_at')->limit(25)->get(),
        ]);
    }

    public function acknowledge(Alert $alert)
    {
        $alert->update(['acknowledged_at' => now(), 'acknowledged_by' => auth()->id()]);

        return back()->with('status', 'Alert acknowledged.');
    }

    public function acknowledgeAll()
    {
        $n = Alert::whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'acknowledged_by' => auth()->id()]);

        return back()->with('status', $n.' '.\Illuminate\Support\Str::plural('alert', $n).' acknowledged.');
    }
}
