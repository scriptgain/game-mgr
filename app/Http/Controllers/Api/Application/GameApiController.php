<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\GameResource;
use App\Models\AuditLog;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Game over the API. The rules match the admin screen's, because an API that
 * validates more loosely than the form is an API that writes rows the panel
 * would have refused.
 */
class GameApiController extends ApiController
{
    public function index(Request $request)
    {
        $query = Game::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('id');

        return $this->paginate($request, $query, GameResource::class);
    }

    public function show(Game $game)
    {
        return $this->one($game, GameResource::class);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $game = Game::create($data);

        AuditLog::record('games.create', 'Created "'.($game->name ?? '').'" over the API', $game);

        return response()->json($this->one($game, GameResource::class), 201);
    }

    public function update(Request $request, Game $game)
    {
        $data = $this->validated($request, $game);
        $game->update($data);

        AuditLog::record('games.update', 'Updated "'.($game->name ?? '').'" over the API', $game);

        return $this->one($game->fresh(), GameResource::class);
    }

    public function destroy(Game $game)
    {
        $name = $game->name ?? '';
        $game->delete();

        AuditLog::record('games.delete', 'Deleted "'.$name.'" over the API');

        return $this->done();
    }

    /**
     * The request body, in one place so the API reference can describe it.
     *
     * Static and public because two callers need it: validation here, and
     * the OpenAPI document, which would otherwise have to parse this file.
     * $subject carries the record being updated, for the rules that have to
     * ignore it.
     *
     * @return array<string,mixed>
     */
    public static function rules(string $action = 'store', mixed $subject = null): array
    {
        $model = $subject instanceof Game ? $subject : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
            'cover_color' => ['nullable', 'string', 'max:16'],
            'category' => ['nullable', 'string', 'max:32'],
        ];
    }

    private function validated(Request $request, ?Game $model): array
    {
        $data = $request->validate(static::rules($model ? 'update' : 'store', $model));

        return $data;
    }
}
