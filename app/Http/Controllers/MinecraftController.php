<?php

namespace App\Http\Controllers;

use App\Services\Minecraft\McJars;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two lookups the Minecraft version picker makes while somebody is filling
 * in a form: "which versions does Purpur have" and "which builds does Purpur
 * 1.21.8 have".
 *
 * Only the first list travels with the page. Shipping the version list for
 * every type a template offers would be several hundred kilobytes of JSON to
 * fill a select showing one of them, so the rest is fetched when the operator
 * actually picks a type.
 *
 * Neither action can fail in a way the browser has to handle beyond "no list".
 * A 200 with ok:false is the whole error protocol, and the picker answers it by
 * turning itself back into the free text box the panel had before, which is
 * also what it does if the request never returns at all.
 *
 * The arguments are checked by hand rather than through $request->validate(),
 * and that is deliberate. bootstrap/app.php narrows the handler's JSON
 * rendering to api/* only, so a ValidationException raised here would come back
 * as a 302 to the dashboard: the fetch would follow it, get a page of HTML, and
 * the picker would have to work out that a 200 full of markup meant "bad
 * request". One shape of answer, always, is easier to be sure about.
 */
class MinecraftController extends Controller
{
    public function __construct(private readonly McJars $mcjars) {}

    public function versions(Request $request): JsonResponse
    {
        $type = $this->serverType($request->query('type'));

        if ($type === null) {
            return $this->answer('versions', null);
        }

        return $this->answer('versions', $this->mcjars->versions($type));
    }

    public function builds(Request $request): JsonResponse
    {
        $type = $this->serverType($request->query('type'));
        $version = $this->versionId($request->query('version'));

        if ($type === null || $version === null) {
            return $this->answer('builds', null);
        }

        return $this->answer('builds', $this->mcjars->builds($type, $version));
    }

    /**
     * A server type as MCJars writes them, or null.
     *
     * Whitelisted rather than escaped, because the value is interpolated into
     * an upstream URL path. "../.." is not a server type and must not become
     * one request further up the road.
     */
    private function serverType(mixed $raw): ?string
    {
        $value = is_string($raw) ? mb_strtoupper(trim($raw)) : '';

        return preg_match('/^[A-Z0-9_]{1,32}$/', $value) === 1 ? $value : null;
    }

    /**
     * A version id, which is whatever string the project chose: "1.21.8",
     * "26.2-rc-2", "26.3-snapshot-7", "b1.7.3". Same reasoning, wider alphabet.
     */
    private function versionId(mixed $raw): ?string
    {
        $value = is_string($raw) ? trim($raw) : '';

        return preg_match('/^[A-Za-z0-9._+-]{1,64}$/', $value) === 1 ? $value : null;
    }

    /**
     * null means MCJars could not be reached, or the argument never deserved a
     * request. Either way it is reported as ok:false rather than as an HTTP
     * error: the panel is working perfectly, and a 502 here would put a red
     * console error on a page that is fine.
     */
    private function answer(string $key, ?array $rows): JsonResponse
    {
        if ($rows === null) {
            return response()->json(['ok' => false, $key => []]);
        }

        return response()->json(['ok' => true, $key => $rows]);
    }
}
