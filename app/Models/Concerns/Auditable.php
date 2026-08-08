<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create/delete audit entries for a model.
 * The human label comes from the model's `name`, else its key.
 */
trait Auditable
{
    /**
     * Suppresses the generic entries while the callback runs.
     *
     * Seeding a fresh install created five blueprints and wrote five audit
     * entries about it, which then filled the operator's activity feed on a
     * panel nobody had used yet. Shipped reference data is not something a
     * person did.
     */
    protected static bool $auditingSuspended = false;

    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = static::$auditingSuspended;
        static::$auditingSuspended = true;

        try {
            return $callback();
        } finally {
            static::$auditingSuspended = $previous;
        }
    }

    public static function bootAuditable(): void
    {
        static::created(function (Model $m) {
            // A model whose controller writes its own richer entry does not
            // want this one as well: "Created server X" and "Server X created"
            // are the same event, and the feed showed both.
            if (static::$auditingSuspended || ($m->auditsOwnCreation ?? false)) {
                return;
            }
            AuditLog::record('created', static::auditLabel($m).' created', $m);
        });

        static::deleted(function (Model $m) {
            if (static::$auditingSuspended) {
                return;
            }
            AuditLog::record('deleted', static::auditLabel($m).' deleted', $m);
        });
    }

    protected static function auditLabel(Model $m): string
    {
        $name = $m->name ?? $m->email ?? $m->getKey();

        return class_basename($m).' "'.$name.'"';
    }
}
