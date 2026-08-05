<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fleet-standard tables every ScriptGain panel carries: account columns, API
// tokens, settings, audit trail and the perimeter firewall's bookkeeping.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin sees the whole panel; client only sees servers they own or
            // are a subuser on. root_admin is the account that cannot be
            // deleted or demoted, so an install can never lock itself out.
            $table->string('role')->default('client')->after('email');
            $table->boolean('root_admin')->default(false)->after('role');
            $table->timestamp('password_changed_at')->nullable()->after('password');
            $table->text('two_factor_secret')->nullable()->after('password_changed_at');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->boolean('suspended')->default(false)->after('two_factor_confirmed_at');
            $table->string('timezone')->default('UTC')->after('suspended');
            $table->timestamp('last_login_at')->nullable()->after('timezone');
            $table->index('role');
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique(); // sha256 of the plaintext token
            // application = full admin REST API, client = only this user's servers.
            $table->string('scope')->default('client');
            $table->json('allowed_ips')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Server-scoped entries also power the client-facing Activity tab,
            // so this table is both the admin audit trail and the user log.
            $table->unsignedBigInteger('server_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index('server_id');
        });

        Schema::create('banned_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = permanent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('expires_at');
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('email')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('banned_ips');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('api_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn([
                'role', 'root_admin', 'password_changed_at', 'two_factor_secret',
                'two_factor_confirmed_at', 'suspended', 'timezone', 'last_login_at',
            ]);
        });
    }
};
