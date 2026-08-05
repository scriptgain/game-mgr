<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Games. This is Pterodactyl's "Nest", renamed to the word people already use.
 */
class GameController extends Controller
{
    public function index()
    {
        return view('admin.games.index', [
            'title' => 'Games',
            'games' => Game::withCount(['templates'])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.games.form', ['title' => 'New Game', 'game' => new Game]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        Game::create($data);

        return redirect()->route('admin.games.index')->with('status', 'Game created.');
    }

    public function edit(Game $game)
    {
        return view('admin.games.form', ['title' => 'Edit Game', 'game' => $game]);
    }

    public function update(Request $request, Game $game)
    {
        $game->update($this->validated($request, $game));

        return redirect()->route('admin.games.index')->with('status', 'Game updated.');
    }

    public function destroy(Game $game)
    {
        if ($game->templates()->exists()) {
            return back()->with('error', 'That game still has templates. Delete them first.');
        }

        $game->delete();

        return back()->with('status', 'Game deleted.');
    }

    private function validated(Request $request, ?Game $game = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
            'cover_color' => ['nullable', 'string', 'max:16'],
        ]);
    }
}
