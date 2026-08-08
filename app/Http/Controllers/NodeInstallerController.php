<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Serves the node installer as plain text at GET /install/node.
 *
 * This is the other half of the one-liner on the node Enroll screen:
 *
 *   curl -fsSL {panel}/install/node | sudo bash -s -- --panel {panel} --token {t}
 *
 * Unauthenticated on purpose. A brand-new box has no session and no credential
 * yet, and the script itself grants nothing: the enroll token in the command is
 * the credential, and it is single use and short lived.
 */
class NodeInstallerController extends Controller
{
    /** The line in the script whose default this substitutes into. */
    private const PANEL_PLACEHOLDER = 'PANEL="${PANEL:-}"';

    public function node(Request $request)
    {
        $path = base_path('deploy/install-node.sh');

        if (! is_readable($path)) {
            return response(
                "# The node installer is missing from this install.\n"
                ."# Expected it at deploy/install-node.sh.\n"
                ."echo 'GameMGR: deploy/install-node.sh is missing on the panel.' >&2; exit 1\n",
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $script = (string) file_get_contents($path);

        $script = str_replace(
            self::PANEL_PLACEHOLDER,
            'PANEL="${PANEL:-'.$this->panelUrl($request).'}"',
            $script,
        );

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            // Piping into a shell must never get a cached copy of an older
            // installer, and nosniff stops a browser rendering it as anything
            // other than the text it is.
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="install-node.sh"',
        ]);
    }

    /**
     * The URL the node should call back on. This is the address the panel was
     * actually reached at, not config('app.url'), because a fresh install is
     * first reached by bare IP over http and APP_URL is still whatever the
     * .env shipped with. Falls back to APP_URL if the host looks nothing like
     * a host, so a junk Host header cannot inject anything into the script.
     */
    private function panelUrl(Request $request): string
    {
        $candidate = rtrim($request->getSchemeAndHttpHost(), '/');

        if (preg_match('#^https?://[A-Za-z0-9._-]+(:\d{1,5})?$#', $candidate) === 1) {
            return $candidate;
        }

        $fallback = rtrim((string) config('app.url'), '/');

        return preg_match('#^https?://[A-Za-z0-9._-]+(:\d{1,5})?$#', $fallback) === 1 ? $fallback : '';
    }
}
