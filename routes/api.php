<?php

use App\Models\Node;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Two APIs, same split as Pterodactyl so tooling written against that ports
 * across with a base-URL change.
 *
 *   /api/application/*  admin scope: nodes, templates, every server
 *   /api/client/*       client scope: only servers the token owner can reach
 *
 * Plus /api/node/* which the daemons themselves call, authenticated by their
 * own per-node key rather than a user token.
 */

Route::prefix('application')->name('api.app.')->middleware('api.token')->group(function () {
    Route::get('me', fn (Request $r) => $r->user()->only(['id', 'name', 'email', 'role']));

    Route::get('nodes', fn () => Node::with('location')->withCount('servers')->get());
    Route::get('nodes/{node}', fn (Node $node) => $node->load('location', 'allocations'));

    Route::get('servers', fn () => Server::with(['owner:id,name,email', 'node:id,name', 'template:id,name,runtime', 'allocation'])->get());
    Route::get('servers/{server}', fn (Server $server) => $server->load(['owner:id,name,email', 'node', 'template', 'allocation', 'subusers.user:id,name,email']));
});

Route::prefix('client')->name('api.client.')->middleware('api.token')->group(function () {
    Route::get('me', fn (Request $r) => $r->user()->only(['id', 'name', 'email']));

    Route::get('servers', fn (Request $r) => $r->user()->accessibleServers()
        ->with(['node:id,name', 'template:id,name,runtime', 'allocation'])
        ->get()
        ->map(fn (Server $s) => [
            'identifier' => $s->uuid_short,
            'name' => $s->name,
            'state' => $s->statusLabel(),
            'address' => $s->address(),
            'limits' => ['memory' => $s->memory, 'disk' => $s->disk, 'cpu' => $s->cpu],
            'players' => $s->cached_players,
        ]));
});

/*
 * Node daemon API. Daemons dial out to this. Enrollment exchanges a short-lived
 * single-use token for a long-lived credential; everything else uses that
 * credential.
 */
Route::prefix('node')->name('api.node.')->group(function () {
    Route::post('enroll', [\App\Http\Controllers\Api\NodeApiController::class, 'enroll']);

    // Backward compatibility: the endpoint was spelled /api/node/enrol until the
    // rename to US English. Daemons built before that still POST to the old
    // path, and a node that cannot enroll is a node that cannot be controlled,
    // so the old spelling stays registered against the same action. Safe to
    // delete once every node in the field reports an agent version at or above
    // the first release that ships the "enroll" spelling.
    Route::post('enrol', [\App\Http\Controllers\Api\NodeApiController::class, 'enroll'])
        ->name('enroll.legacy');

    Route::middleware('agent.auth')->group(function () {
        Route::post('heartbeat', [\App\Http\Controllers\Api\NodeApiController::class, 'heartbeat']);
        Route::get('servers', [\App\Http\Controllers\Api\NodeApiController::class, 'servers']);
        Route::post('servers/{uuid}/state', [\App\Http\Controllers\Api\NodeApiController::class, 'state']);
    });
});
