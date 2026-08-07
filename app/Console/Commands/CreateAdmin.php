<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Create the first admin from the command line.
 *
 * The web /setup wizard is the interactive path, but an unattended install has
 * nobody to fill it in: deploy/install-panel.sh runs this instead so the box is
 * usable the moment the script finishes.
 *
 *   php artisan gamemgr:create-admin --email=you@example.com
 *   php artisan gamemgr:create-admin --email=you@example.com --password=... --name="Allen"
 *
 * Idempotent on purpose, because the installer is re-runnable: an existing
 * account is promoted rather than duplicated, and its password is left alone
 * unless --password or --force says otherwise. Marking setup_complete is part
 * of the job, without it EnsureSetup would still bounce every request to the
 * wizard; and it is only marked once an admin genuinely exists, since
 * setup_complete with no admin locks the panel out entirely.
 */
class CreateAdmin extends Command
{
    protected $signature = 'gamemgr:create-admin
        {--email= : email address for the admin account}
        {--password= : password to set; generated and printed when omitted}
        {--name= : display name (defaults to the mailbox part of the email)}
        {--force : reset the password of an account that already exists}';

    protected $description = 'Create (or promote) the first GameMGR admin account';

    public function handle(): int
    {
        $email = trim((string) ($this->option('email') ?: ''));
        if ($email === '' && $this->input->isInteractive()) {
            $email = trim((string) $this->ask('Admin email address'));
        }
        if ($email === '') {
            $this->error('An email address is required: --email=you@example.com');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        // Only generate when we are actually going to set a password, so a
        // re-run against an existing account never prints a password that was
        // not applied.
        $given = (string) ($this->option('password') ?: '');
        $setPassword = $given !== '' || ! $existing || $this->option('force');
        $generated = $given === '' && $setPassword;
        $password = $generated ? $this->generatePassword() : $given;

        $name = trim((string) ($this->option('name') ?: '')) ?: Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@')));

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
        if ($setPassword) {
            $rules['password'] = ['required', 'string', 'min:8'];
        }
        if (! $existing) {
            $rules['email'][] = Rule::unique('users', 'email');
        }

        $v = Validator::make(compact('name', 'email', 'password'), $rules);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // The first admin is the root admin: the account that cannot be demoted
        // or deleted. Later runs must not mint a second one.
        $root = User::where('root_admin', true)->doesntExist();

        if ($existing) {
            // The model casts password as 'hashed', so a plain value is hashed
            // on save. Hashing here as well would double-hash it.
            $attrs = ['name' => $name, 'role' => 'admin', 'suspended' => false];
            if ($setPassword) {
                $attrs['password'] = $password;
            }
            $existing->fill($attrs);
            $existing->forceFill([
                'root_admin' => $root ? true : $existing->root_admin,
                'password_changed_at' => $setPassword ? now() : $existing->password_changed_at,
            ])->save();
            $user = $existing;
            $verb = 'Promoted existing account';
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
            ]);
            $user->forceFill(['root_admin' => $root, 'password_changed_at' => now()])->save();
            $verb = 'Created admin';
        }

        // Close the first-run wizard, but only now that an admin exists.
        Setting::put('setup_complete', '1');

        $this->info("{$verb}: {$user->email}" . ($user->root_admin ? ' (root admin)' : ''));
        if ($generated) {
            $this->line('Password: ' . $password);
        } elseif (! $setPassword) {
            $this->line('Password unchanged; re-run with --force to reset it.');
        }

        return self::SUCCESS;
    }

    /**
     * Alphanumeric on purpose. This value is echoed by the installer and pasted
     * into shell summaries and .env-adjacent files, where quoting mistakes are
     * silent, so it carries nothing a shell or dotenv parser would reinterpret.
     */
    private function generatePassword(int $length = 20): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
