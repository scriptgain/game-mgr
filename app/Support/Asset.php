<?php

namespace App\Support;

/**
 * Cache-busting for the panel's own static files.
 *
 * The fleet serves these straight off nginx with an ETag and no Cache-Control,
 * which lets a browser reuse its copy indefinitely without revalidating. That
 * is fine until a deploy changes the file: the URL is identical, so the browser
 * never learns, and the panel behaves like the old code on a box running the
 * new code. Appending the file's modification time makes the URL change exactly
 * when the content does.
 */
class Asset
{
    public static function version(string $path): string
    {
        static $cache = [];

        if (isset($cache[$path])) {
            return $cache[$path];
        }

        $full = public_path($path);
        // Falls back to the app version rather than throwing: a missing asset
        // is a deployment problem, not a reason to 500 every page.
        $stamp = @filemtime($full) ?: (@file_get_contents(base_path('VERSION')) ?: 'dev');

        return $cache[$path] = substr((string) $stamp, -10);
    }
}
