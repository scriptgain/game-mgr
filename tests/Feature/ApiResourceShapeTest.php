<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every resource renders, and every field it names is real.
 *
 * A resource that asks a model for a column it does not have does not throw: it
 * returns null, the JSON looks plausible, and nobody finds out until somebody
 * builds against it. Half the resources here were written against guessed
 * column names and fifteen of them were wrong, so this walks each one against
 * the actual schema instead of trusting the guess.
 */
class ApiResourceShapeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Resource class => the table its model reads, and the attribute paths that
     * must correspond to a real column. Nested and derived fields are left out:
     * those are ours to shape, and only the ones that map straight through can
     * be checked this way.
     */
    private const BACKED_BY = [
        'BackupResource' => ['backups', ['id', 'uuid', 'server_id', 'name', 'bytes', 'checksum', 'is_locked', 'is_successful', 'failure_reason', 'completed_at']],
        'ScheduleResource' => ['schedules', ['id', 'server_id', 'name', 'cron_minute', 'cron_hour', 'cron_day_of_month', 'cron_month', 'cron_day_of_week', 'is_active', 'only_when_online', 'is_processing', 'last_run_at', 'next_run_at']],
        'SubuserResource' => ['subusers', ['id', 'server_id', 'user_id', 'permissions']],
        'ServerDatabaseResource' => ['server_databases', ['id', 'server_id', 'database', 'username', 'database_host_id', 'remote', 'bytes']],
        'BlueprintResource' => ['blueprints', ['id', 'name', 'description', 'template_id', 'limits', 'feature_limits', 'environment']],
        'MountResource' => ['mounts', ['id', 'uuid', 'name', 'description', 'source', 'target', 'read_only', 'user_mountable']],
        'DatabaseHostResource' => ['database_hosts', ['id', 'name', 'host', 'port', 'username', 'linked_ip', 'node_id', 'max_databases']],
        'WebhookResource' => ['webhooks', ['id', 'name', 'url', 'events', 'is_active', 'failure_count', 'last_fired_at']],
        'WatchdogRuleResource' => ['watchdog_rules', ['id', 'server_id', 'name', 'trigger', 'pattern', 'threshold', 'grace_seconds', 'action', 'channels', 'is_active', 'last_fired_at']],
        'NotificationChannelResource' => ['notification_channels', ['id', 'name', 'type', 'events', 'is_active', 'last_used_at']],
        'AlertResource' => ['alerts', ['id', 'server_id', 'node_id', 'watchdog_rule_id', 'severity', 'title', 'detail', 'acknowledged_at']],
        'ModResource' => ['mods', ['id', 'server_id', 'name', 'slug', 'author', 'source', 'remote_id', 'version', 'latest_version', 'enabled', 'path', 'bytes']],
        'WorldResource' => ['worlds', ['id', 'server_id', 'name', 'path', 'seed', 'level_type', 'is_active', 'bytes', 'last_played_at']],
        'PlayerResource' => ['players', ['id', 'server_id', 'identifier', 'name', 'is_online', 'is_banned', 'is_op', 'is_whitelisted', 'playtime_seconds', 'first_seen_at', 'last_seen_at']],
        'StatusPageResource' => ['status_pages', ['id', 'server_id', 'slug', 'headline', 'is_public', 'show_players', 'show_address', 'show_uptime', 'show_version']],
        'ServerMetricResource' => ['server_metrics', ['sampled_at', 'cpu', 'memory', 'disk', 'net_rx', 'net_tx', 'players', 'tick_rate']],
        'NodeMetricResource' => ['node_metrics', ['sampled_at', 'cpu', 'memory', 'disk', 'load', 'server_count', 'running_count']],
        'TemplateVariableResource' => ['template_variables', ['id', 'template_id', 'name', 'description', 'env_variable', 'default_value', 'rules', 'user_viewable', 'user_editable', 'sort']],
        'TemplatePortResource' => ['template_ports', ['id', 'template_id', 'role', 'label', 'protocol', 'source', 'port', 'port_offset', 'required']],
        'AuditLogResource' => ['audit_logs', ['id', 'action', 'description', 'user_id', 'server_id', 'properties', 'ip']],
        'ApiTokenResource' => ['api_tokens', ['id', 'user_id', 'name', 'scope', 'allowed_ips', 'last_used_at', 'expires_at']],
        'ServerVariableResource' => ['server_variables', ['id', 'server_id', 'template_variable_id', 'value']],
        'AllocationResource' => ['allocations', ['id', 'node_id', 'server_id', 'ip', 'ip_alias', 'port', 'protocol', 'role']],
    ];

    /**
     * The check that would have caught fifteen wrong guesses: every column a
     * resource reads has to exist on the table it reads from.
     */
    public function test_every_resource_reads_columns_that_exist(): void
    {
        foreach (self::BACKED_BY as $resource => [$table, $columns]) {
            $this->assertTrue(Schema::hasTable($table), $table.' is missing entirely');

            $actual = Schema::getColumnListing($table);
            foreach ($columns as $column) {
                $this->assertContains(
                    $column,
                    $actual,
                    $resource.' reads '.$table.'.'.$column.', which does not exist. A resource asking for a column that is not there returns null rather than failing, so this is the only thing that catches it.',
                );
            }
        }
    }

    /** Every resource class answers the two things the envelope needs. */
    public function test_every_resource_declares_its_object_name_and_fields(): void
    {
        $files = glob(app_path('Http/Resources/*Resource.php'));
        $this->assertGreaterThan(20, count($files), 'the resource directory looks empty');

        foreach ($files as $file) {
            $class = 'App\\Http\\Resources\\'.basename($file, '.php');

            $this->assertTrue(class_exists($class), $class.' does not autoload');

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            $this->assertTrue($reflection->hasMethod('objectName'), $class.' must name its object');
            $this->assertTrue($reflection->hasMethod('fields'), $class.' must declare its fields');
        }
    }
}
