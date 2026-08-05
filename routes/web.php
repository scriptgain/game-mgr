<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BulkActionController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\Client;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

// First-run setup. GameMGR is free, so this is one step: create the admin.
Route::prefix('setup')->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('setup.index');
    Route::post('/admin', [SetupController::class, 'storeAdmin'])->name('setup.admin');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

// One-click signed magic-login link (short-lived; the signature is the credential).
Route::get('/magic/{user}', [AuthController::class, 'magic'])->name('magic-login')->middleware('signed');

Route::get('/2fa', [AuthController::class, 'challenge'])->name('2fa.challenge');
Route::post('/2fa', [AuthController::class, 'challengeVerify'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Brand favicon, accent-tinted from DB-driven branding (public: browsers fetch
// it pre-login). Extension-less on purpose, because some nginx setups serve
// *.svg as a static file and 404 before the request ever reaches PHP.
Route::get('/brand/favicon', [FaviconController::class, 'svg'])->name('favicon.svg');
Route::get('/brand/favicon-png', [FaviconController::class, 'faviconPng'])->name('favicon.png');
Route::get('/brand/favicon-apple', [FaviconController::class, 'appleIcon'])->name('favicon.apple');

Route::view('/docs', 'docs')->name('docs');

// Public, opt-in status page for a single server. Deliberately outside auth.
Route::get('/status/{slug}', [Client\StatusPageController::class, 'show'])->name('status.show');

Route::middleware(['auth', 'security.policy'])->group(function () {

    // Dashboard. Admins get the fleet, clients get their own servers.
    Route::get('/', DashboardController::class)->name('dashboard');

    // ---------------------------------------------------------------- client
    // Everything a server owner or subuser touches. Authorised per action by
    // ServerPolicy, never by which menu the link came from.
    Route::prefix('server/{server}')->name('server.')->group(function () {
        Route::get('/', [Client\ConsoleController::class, 'show'])->name('console');
        Route::post('/power', [Client\ConsoleController::class, 'power'])->name('power');
        Route::post('/command', [Client\ConsoleController::class, 'command'])->name('command');
        Route::get('/stats', [Client\ConsoleController::class, 'stats'])->name('stats');

        Route::get('/files', [Client\FileController::class, 'index'])->name('files');
        Route::get('/files/edit', [Client\FileController::class, 'edit'])->name('files.edit');
        Route::post('/files/save', [Client\FileController::class, 'save'])->name('files.save');
        Route::post('/files/mkdir', [Client\FileController::class, 'mkdir'])->name('files.mkdir');
        Route::post('/files/rename', [Client\FileController::class, 'rename'])->name('files.rename');
        Route::delete('/files', [Client\FileController::class, 'destroy'])->name('files.destroy');

        Route::get('/databases', [Client\DatabaseController::class, 'index'])->name('databases');
        Route::post('/databases', [Client\DatabaseController::class, 'store'])->name('databases.store');
        Route::delete('/databases/{database}', [Client\DatabaseController::class, 'destroy'])->name('databases.destroy');

        Route::get('/backups', [Client\BackupController::class, 'index'])->name('backups');
        Route::post('/backups', [Client\BackupController::class, 'store'])->name('backups.store');
        Route::post('/backups/{backup}/lock', [Client\BackupController::class, 'lock'])->name('backups.lock');
        Route::post('/backups/{backup}/restore', [Client\BackupController::class, 'restore'])->name('backups.restore');
        Route::delete('/backups/{backup}', [Client\BackupController::class, 'destroy'])->name('backups.destroy');

        Route::get('/schedules', [Client\ScheduleController::class, 'index'])->name('schedules');
        Route::get('/schedules/create', [Client\ScheduleController::class, 'create'])->name('schedules.create');
        Route::post('/schedules', [Client\ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('/schedules/{schedule}', [Client\ScheduleController::class, 'edit'])->name('schedules.edit');
        Route::put('/schedules/{schedule}', [Client\ScheduleController::class, 'update'])->name('schedules.update');
        Route::post('/schedules/{schedule}/run', [Client\ScheduleController::class, 'run'])->name('schedules.run');
        Route::delete('/schedules/{schedule}', [Client\ScheduleController::class, 'destroy'])->name('schedules.destroy');

        Route::get('/players', [Client\PlayerController::class, 'index'])->name('players');
        Route::post('/players/{player}/kick', [Client\PlayerController::class, 'kick'])->name('players.kick');
        Route::post('/players/{player}/ban', [Client\PlayerController::class, 'ban'])->name('players.ban');
        Route::post('/players/{player}/unban', [Client\PlayerController::class, 'unban'])->name('players.unban');
        Route::post('/players/{player}/whitelist', [Client\PlayerController::class, 'whitelist'])->name('players.whitelist');
        Route::post('/players/{player}/op', [Client\PlayerController::class, 'op'])->name('players.op');

        Route::get('/mods', [Client\ModController::class, 'index'])->name('mods');
        Route::get('/mods/browse', [Client\ModController::class, 'browse'])->name('mods.browse');
        Route::post('/mods', [Client\ModController::class, 'store'])->name('mods.store');
        Route::post('/mods/{mod}/update', [Client\ModController::class, 'update'])->name('mods.update');
        Route::post('/mods/{mod}/toggle', [Client\ModController::class, 'toggle'])->name('mods.toggle');
        Route::delete('/mods/{mod}', [Client\ModController::class, 'destroy'])->name('mods.destroy');

        Route::get('/worlds', [Client\WorldController::class, 'index'])->name('worlds');
        Route::post('/worlds/{world}/activate', [Client\WorldController::class, 'activate'])->name('worlds.activate');
        Route::delete('/worlds/{world}', [Client\WorldController::class, 'destroy'])->name('worlds.destroy');

        Route::get('/network', [Client\NetworkController::class, 'index'])->name('network');
        Route::post('/network', [Client\NetworkController::class, 'store'])->name('network.store');
        Route::post('/network/{allocation}/primary', [Client\NetworkController::class, 'makePrimary'])->name('network.primary');
        Route::delete('/network/{allocation}', [Client\NetworkController::class, 'destroy'])->name('network.destroy');

        Route::get('/users', [Client\SubuserController::class, 'index'])->name('users');
        Route::get('/users/create', [Client\SubuserController::class, 'create'])->name('users.create');
        Route::post('/users', [Client\SubuserController::class, 'store'])->name('users.store');
        Route::get('/users/{subuser}', [Client\SubuserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{subuser}', [Client\SubuserController::class, 'update'])->name('users.update');
        Route::delete('/users/{subuser}', [Client\SubuserController::class, 'destroy'])->name('users.destroy');

        Route::get('/startup', [Client\StartupController::class, 'index'])->name('startup');
        Route::put('/startup', [Client\StartupController::class, 'update'])->name('startup.update');

        Route::get('/metrics', [Client\MetricController::class, 'index'])->name('metrics');
        Route::get('/metrics/series', [Client\MetricController::class, 'series'])->name('metrics.series');

        Route::get('/activity', [Client\ActivityController::class, 'index'])->name('activity');

        // One endpoint for every bulk action on this server's tabs. Authorised
        // by the same ServerPolicy permission the single-item action uses.
        Route::post('/bulk/{resource}', [BulkActionController::class, 'server'])->name('bulk');

        Route::get('/settings', [Client\SettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [Client\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/reinstall', [Client\SettingsController::class, 'reinstall'])->name('settings.reinstall');
        Route::put('/settings/status-page', [Client\SettingsController::class, 'statusPage'])->name('settings.status-page');
    });

    // --------------------------------------------------------------- account
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::put('/', [AccountController::class, 'update'])->name('update');
        Route::get('/api', [ApiTokenController::class, 'index'])->name('api.index');
        Route::post('/api', [ApiTokenController::class, 'store'])->name('api.store');
        Route::delete('/api/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api.destroy');
    });

    // ----------------------------------------------------------------- admin
    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
        // One endpoint for every admin bulk action, with an explicit registry of
        // what may be acted on. See BulkActionController.
        Route::post('bulk/{resource}', [BulkActionController::class, 'admin'])->name('bulk');

        Route::resource('locations', Admin\LocationController::class)->except(['show']);

        // Declared before the resource so "import" is not read as a template id.
        Route::get('templates/import', [Admin\TemplateImportController::class, 'show'])->name('templates.import');
        Route::post('templates/import', [Admin\TemplateImportController::class, 'store'])->name('templates.import.store');

        Route::get('nodes/{node}/enrol', [Admin\NodeController::class, 'enrol'])->name('nodes.enrol');
        Route::post('nodes/{node}/enrol', [Admin\NodeController::class, 'regenerateEnrol'])->name('nodes.enrol.regenerate');
        Route::get('nodes/{node}/allocations', [Admin\NodeController::class, 'allocations'])->name('nodes.allocations');
        Route::post('nodes/{node}/allocations', [Admin\NodeController::class, 'storeAllocations'])->name('nodes.allocations.store');
        Route::delete('nodes/{node}/allocations/{allocation}', [Admin\NodeController::class, 'destroyAllocation'])->name('nodes.allocations.destroy');
        Route::get('nodes/{node}/metrics', [Admin\NodeController::class, 'metrics'])->name('nodes.metrics');
        Route::post('nodes/{node}/check', [Admin\NodeController::class, 'check'])->name('nodes.check');
        Route::resource('nodes', Admin\NodeController::class);

        Route::resource('games', Admin\GameController::class)->except(['show']);

        Route::get('templates/{template}/variables', [Admin\TemplateController::class, 'variables'])->name('templates.variables');
        Route::post('templates/{template}/variables', [Admin\TemplateController::class, 'storeVariable'])->name('templates.variables.store');
        Route::delete('templates/{template}/variables/{variable}', [Admin\TemplateController::class, 'destroyVariable'])->name('templates.variables.destroy');
        Route::resource('templates', Admin\TemplateController::class);

        Route::post('servers/{server}/suspend', [Admin\ServerController::class, 'suspend'])->name('servers.suspend');
        Route::post('servers/{server}/unsuspend', [Admin\ServerController::class, 'unsuspend'])->name('servers.unsuspend');
        Route::post('servers/{server}/reinstall', [Admin\ServerController::class, 'reinstall'])->name('servers.reinstall');
        Route::resource('servers', Admin\ServerController::class);

        Route::resource('blueprints', Admin\BlueprintController::class)->except(['show']);
        Route::resource('mounts', Admin\MountController::class)->except(['show']);
        Route::resource('database-hosts', Admin\DatabaseHostController::class)->except(['show'])->parameters(['database-hosts' => 'host']);
        Route::resource('watchdog', Admin\WatchdogController::class)->except(['show'])->parameters(['watchdog' => 'rule']);
        Route::post('channels/{channel}/test', [Admin\ChannelController::class, 'test'])->name('channels.test');
        Route::resource('channels', Admin\ChannelController::class)->except(['show'])->parameters(['channels' => 'channel']);
        Route::resource('webhooks', Admin\WebhookController::class)->except(['show'])->parameters(['webhooks' => 'webhook']);

        Route::get('alerts', [Admin\AlertController::class, 'index'])->name('alerts.index');
        Route::post('alerts/ack-all', [Admin\AlertController::class, 'acknowledgeAll'])->name('alerts.ack-all');
        Route::post('alerts/{alert}/ack', [Admin\AlertController::class, 'acknowledge'])->name('alerts.ack');

        Route::resource('users', Admin\UserController::class)->except(['show']);
    });

    // -------------------------------------------------------------- settings
    Route::prefix('settings')->name('settings.')->middleware('can:admin')->group(function () {
        Route::get('/', fn () => redirect()->route('settings.general.edit'))->name('index');
        Route::get('general', [GeneralSettingsController::class, 'edit'])->name('general.edit');
        Route::put('general', [GeneralSettingsController::class, 'update'])->name('general.update');
        Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('branding', [BrandingController::class, 'update'])->name('branding.update');
        Route::get('notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
        Route::put('notifications', [NotificationController::class, 'update'])->name('notifications.update');
        Route::post('notifications/test', [NotificationController::class, 'test'])->name('notifications.test');
        Route::get('integrations', [IntegrationController::class, 'edit'])->name('integrations.edit');
        Route::put('integrations', [IntegrationController::class, 'update'])->name('integrations.update');
        Route::post('integrations/test', [IntegrationController::class, 'test'])->name('integrations.test');

        Route::get('firewall', [FirewallController::class, 'index'])->name('firewall.index');
        Route::put('firewall', [FirewallController::class, 'update'])->name('firewall.update');
        Route::post('firewall/bans', [FirewallController::class, 'ban'])->name('firewall.ban');
        Route::delete('firewall/bans/{bannedIp}', [FirewallController::class, 'unban'])->name('firewall.unban');
        Route::delete('firewall/sessions/{id}', [FirewallController::class, 'revokeSession'])->name('firewall.session.revoke');
        Route::post('firewall/sessions/bulk', [FirewallController::class, 'bulkSessions'])->name('firewall.sessions.bulk');
        Route::post('firewall/bulk', [FirewallController::class, 'bulk'])->name('firewall.bulk');

        Route::get('updates', [UpdateController::class, 'show'])->name('updates.show');
        Route::post('updates/check', [UpdateController::class, 'check'])->name('updates.check');
        Route::post('updates/apply', [UpdateController::class, 'apply'])->name('updates.apply');
        Route::post('updates/auto', [UpdateController::class, 'toggleAuto'])->name('updates.auto');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::delete('audit/selected', [AuditLogController::class, 'destroySelected'])->name('audit.destroy-selected');
        Route::delete('audit/all', [AuditLogController::class, 'destroyAll'])->name('audit.destroy-all');

        // Admin shortcut into the same user management the admin area exposes,
        // so the Settings menu is complete on its own.
        Route::get('users', fn () => redirect()->route('admin.users.index'))->name('users.index');
    });

    // Personal security settings, available to every account, not just admins.
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');
    Route::get('settings/2fa', [TwoFactorController::class, 'show'])->name('settings.2fa.show');
    Route::post('settings/2fa/enable', [TwoFactorController::class, 'enable'])->name('settings.2fa.enable');
    Route::post('settings/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('settings.2fa.confirm');
    Route::delete('settings/2fa', [TwoFactorController::class, 'disable'])->name('settings.2fa.disable');
});
