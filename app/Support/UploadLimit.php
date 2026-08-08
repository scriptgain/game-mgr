<?php

namespace App\Support;

use App\Models\Node;

/**
 * How large a file the file manager will actually accept, and why.
 *
 * There are two limits and they are set by different people. The node's
 * upload_size is what the operator chose in the panel. PHP's own
 * upload_max_filesize and post_max_size are what the box the panel runs on will
 * physically receive, and they default to 2M and 8M, which is smaller than any
 * sensible node cap.
 *
 * The panel must not advertise a limit it cannot honour. When PHP is the
 * smaller of the two it becomes the effective limit and the operator is told
 * exactly which two ini settings to raise, because the failure mode otherwise
 * is a browser that uploads a 100 MB file and gets an empty POST back with no
 * explanation: past post_max_size, PHP discards the entire request body, so
 * $_FILES is empty, validation reports a missing file, and nothing anywhere
 * mentions a size.
 */
class UploadLimit
{
    /**
     * The largest single file this panel can receive, in bytes, whatever the
     * node would allow. Zero means PHP is unlimited, which is legal and rare.
     */
    public static function phpBytes(): int
    {
        $upload = self::iniBytes('upload_max_filesize');
        $post = self::iniBytes('post_max_size');

        // Zero is "no limit" for either setting, so it must not win a min().
        $limits = array_filter([$upload, $post], fn ($v) => $v > 0);

        return $limits === [] ? 0 : (int) min($limits);
    }

    /** What the operator set on this node, in bytes. */
    public static function nodeBytes(?Node $node): int
    {
        return (int) max(1, $node?->upload_size ?? 256) * 1024 * 1024;
    }

    /** The limit the browser and the controller both enforce. */
    public static function effectiveBytes(?Node $node): int
    {
        $node = self::nodeBytes($node);
        $php = self::phpBytes();

        return $php > 0 ? (int) min($node, $php) : $node;
    }

    /**
     * A plain sentence for the operator when PHP is the reason the limit is
     * lower than the node's, or null when the node's own setting is the one
     * doing the limiting.
     */
    public static function shortfall(?Node $node): ?string
    {
        $php = self::phpBytes();
        if ($php <= 0 || $php >= self::nodeBytes($node)) {
            return null;
        }

        return 'This node allows '.Format::bytes(self::nodeBytes($node)).
            ', but PHP on the panel accepts '.Format::bytes($php).
            '. Raise upload_max_filesize and post_max_size in php.ini to lift it.';
    }

    /**
     * Whether the request that just arrived was larger than post_max_size, in
     * which case PHP has already thrown the body away and nothing else in the
     * request can be believed.
     *
     * Detected from CONTENT_LENGTH, which survives: the header is read before
     * the body is discarded.
     */
    public static function bodyWasDiscarded(int $contentLength): bool
    {
        $post = self::iniBytes('post_max_size');

        return $post > 0 && $contentLength > $post;
    }

    /** A php.ini shorthand size such as "8M" or "512K" as bytes. */
    public static function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));
        if ($raw === '' || $raw === '-1') {
            return 0;
        }

        $value = (int) $raw;
        $suffix = strtolower(substr($raw, -1));

        return match ($suffix) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
