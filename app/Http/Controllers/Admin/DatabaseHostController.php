<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DatabaseHost;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A database host is a MySQL or MariaDB server that per-server game databases
 * get carved out of. The privileged credentials live here and are never shown
 * to a client: they only ever see the account created for them.
 */
class DatabaseHostController extends Controller
{
    public function index()
    {
        return view('admin.database-hosts.index', [
            'title' => 'Database Hosts',
            'hosts' => DatabaseHost::withCount('databases')->with('node')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.database-hosts.form', [
            'title' => 'New Database Host',
            'host' => new DatabaseHost(['port' => 3306]),
            'nodes' => Node::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        DatabaseHost::create($this->validated($request));

        return redirect()->route('admin.database-hosts.index')->with('status', 'Database host added.');
    }

    public function edit(DatabaseHost $host)
    {
        return view('admin.database-hosts.form', [
            'title' => 'Edit '.$host->name,
            'host' => $host,
            'nodes' => Node::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, DatabaseHost $host)
    {
        $data = $this->validated($request, $host);
        // An empty password field means "leave it alone", not "set it to empty".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $host->update($data);

        return redirect()->route('admin.database-hosts.index')->with('status', 'Database host updated.');
    }

    public function destroy(DatabaseHost $host)
    {
        if ($host->databases()->exists()) {
            return back()->with('error', 'Servers still hold databases on that host. Delete those first.');
        }

        $host->delete();

        return back()->with('status', 'Database host removed.');
    }

    private function validated(Request $request, ?DatabaseHost $host = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:64'],
            'password' => [$host ? 'nullable' : 'required', 'string', 'max:255'],
            'linked_ip' => ['nullable', 'string', 'max:255'],
            'node_id' => ['nullable', 'exists:nodes,id'],
            'max_databases' => ['required', 'integer', 'min:0'],
        ]);
    }
}
