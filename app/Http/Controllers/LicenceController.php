<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Node;
use App\Models\Server;
use App\Services\LicenceClient;
use App\Support\Edition;
use Illuminate\Http\Request;

/**
 * The Licence page: which edition this install is on, what that includes, and
 * where to put a key.
 *
 * Deliberately honest about the free edition. GameMGR is free to run and the
 * free tier is a real product, so this page is not a nag screen: it says what
 * is included, what is not, and leaves it there.
 */
class LicenceController extends Controller
{
    public function edit()
    {
        return view('settings.licence', $this->payload());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'licence_key' => ['nullable', 'string', 'max:255'],
        ]);

        $key = trim((string) ($data['licence_key'] ?? ''));
        LicenceClient::setKey($key ?: null);

        // The key itself never goes in the audit log. It is a credential, and an
        // audit trail is one of the places credentials leak from.
        AuditLog::record('licence.key', $key === ''
            ? 'Licence key removed, so this install is on the free edition.'
            : 'Licence key set.');

        $status = LicenceClient::refresh();

        return redirect()->route('settings.licence.edit')
            ->with($status['ok'] ? 'status' : 'error', $status['message']);
    }

    /** Re-check now, for somebody who has just bought or renewed. */
    public function recheck()
    {
        $status = LicenceClient::refresh();

        return redirect()->route('settings.licence.edit')
            ->with($status['ok'] ? 'status' : 'error', $status['message']);
    }

    private function payload(): array
    {
        $status = LicenceClient::status();
        $current = Edition::current();

        return [
            'title' => 'Licence',
            'status' => $status,
            'current' => $current,
            'editions' => Edition::all(),
            'features' => (array) config('editions.features', []),
            'hasKey' => LicenceClient::key() !== null,
            'usage' => [
                'servers' => Server::count(),
                'nodes' => Node::count(),
            ],
        ];
    }
}
