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

Route::prefix('application')->name('api.app.')->middleware('api.token:application')->group(function () {
    Route::get('me', fn (Request $r) => $r->user()->only(['id', 'name', 'email', 'role']));

    // Read only. Nothing in a provisioning flow creates a node or a template,
    // and an API that can create a node is an API that can point a customer's
    // server at a machine somebody else controls.
    Route::get('nodes', [App\Http\Controllers\Api\Application\NodeController::class, 'index'])->name('nodes.index');
    Route::get('nodes/{node}', [App\Http\Controllers\Api\Application\NodeController::class, 'show'])->name('nodes.show');
    // Allocations hang off their node: a port only means anything with the
    // machine it is on, and a flat /allocations would let an id alone reach
    // any port on any node.
    Route::get('nodes/{node}/allocations', [App\Http\Controllers\Api\Application\AllocationController::class, 'index'])->name('nodes.allocations.index');
    Route::post('nodes/{node}/allocations', [App\Http\Controllers\Api\Application\AllocationController::class, 'store'])->name('nodes.allocations.store');
    Route::get('nodes/{node}/allocations/{allocation}', [App\Http\Controllers\Api\Application\AllocationController::class, 'show'])->name('nodes.allocations.show');
    Route::delete('nodes/{node}/allocations/{allocation}', [App\Http\Controllers\Api\Application\AllocationController::class, 'destroy'])->name('nodes.allocations.destroy');

    Route::get('locations', [App\Http\Controllers\Api\Application\LocationController::class, 'index'])->name('locations.index');
    Route::get('locations/{location}', [App\Http\Controllers\Api\Application\LocationController::class, 'show'])->name('locations.show');
    Route::get('templates', [App\Http\Controllers\Api\Application\TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/{template}', [App\Http\Controllers\Api\Application\TemplateController::class, 'show'])->name('templates.show');
    Route::get('games', [App\Http\Controllers\Api\Application\GameController::class, 'index'])->name('games.index');
    Route::get('games/{game}', [App\Http\Controllers\Api\Application\GameController::class, 'show'])->name('games.show');

    // Accounts. A billing system creates one of these before it can create a
    // server for it, so this is the first call a provisioning module makes.
    Route::get('users', [App\Http\Controllers\Api\Application\UserController::class, 'index'])->name('users.index');
    Route::post('users', [App\Http\Controllers\Api\Application\UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [App\Http\Controllers\Api\Application\UserController::class, 'show'])->name('users.show');
    Route::patch('users/{user}', [App\Http\Controllers\Api\Application\UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [App\Http\Controllers\Api\Application\UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/sso', [App\Http\Controllers\Api\Application\UserController::class, 'sso'])->name('users.sso');

    // The provisioning lifecycle.
    Route::get('servers', [App\Http\Controllers\Api\Application\ServerController::class, 'index'])->name('servers.index');
    Route::post('servers', [App\Http\Controllers\Api\Application\ServerController::class, 'store'])->name('servers.store');
    Route::get('servers/{server}', [App\Http\Controllers\Api\Application\ServerController::class, 'show'])->name('servers.show');
    Route::patch('servers/{server}/build', [App\Http\Controllers\Api\Application\ServerController::class, 'build'])->name('servers.build');
    Route::post('servers/{server}/suspend', [App\Http\Controllers\Api\Application\ServerController::class, 'suspend'])->name('servers.suspend');
    Route::post('servers/{server}/unsuspend', [App\Http\Controllers\Api\Application\ServerController::class, 'unsuspend'])->name('servers.unsuspend');
    Route::post('servers/{server}/reinstall', [App\Http\Controllers\Api\Application\ServerController::class, 'reinstall'])->name('servers.reinstall');
    Route::delete('servers/{server}', [App\Http\Controllers\Api\Application\ServerController::class, 'destroy'])->name('servers.destroy');
});

Route::prefix('client')->name('api.client.')->middleware('api.token:client')->group(function () {
    Route::get('me', fn (Request $r) => $r->user()->only(['id', 'name', 'username', 'email']));

    Route::get('servers', [App\Http\Controllers\Api\Client\ServerController::class, 'index'])->name('servers.index');
    Route::get('servers/{server}', [App\Http\Controllers\Api\Client\ServerController::class, 'show'])->name('servers.show');
    Route::get('servers/{server}/resources', [App\Http\Controllers\Api\Client\ServerController::class, 'resources'])->name('servers.resources');
    Route::post('servers/{server}/power', [App\Http\Controllers\Api\Client\ServerController::class, 'power'])->name('servers.power');
    Route::post('servers/{server}/command', [App\Http\Controllers\Api\Client\ServerController::class, 'command'])->name('servers.command');
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
        // One call per SFTP connection. The daemon holds no accounts, so this is
        // where an SFTP password is actually checked, against the same policy
        // that guards the web file manager.
        Route::post('sftp/authenticate', [\App\Http\Controllers\Api\NodeApiController::class, 'sftpAuthenticate']);
        Route::get('servers', [\App\Http\Controllers\Api\NodeApiController::class, 'servers']);
        Route::post('servers/{uuid}/state', [\App\Http\Controllers\Api\NodeApiController::class, 'state']);
    });
});
