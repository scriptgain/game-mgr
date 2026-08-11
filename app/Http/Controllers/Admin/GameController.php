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
    public function index(Request $request)
    {
        // Searched, filtered and paged on the server.
        //
        // This used to be ->get() with no paging, which was fine at six games
        // and shipped a 2.2 MB page at a hundred and ninety two. Filtering in
        // the browser does not help: the cost is in sending them all.
        $games = Game::withCount('templates')
            ->when($request->string('q')->trim()->value(), function ($query, string $term) {
                $like = '%'.$term.'%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('description', 'like', $like));
            })
            ->when($request->string('category')->trim()->value(), fn ($query, string $c) => $query->where('category', $c))
            ->orderBy('name')
            ->paginate(config('gamemgr.rows_per_page', 24))
            ->withQueryString();

        return view('admin.games.index', [
            'title' => 'Games',
            'games' => $games,
            // Straight off the rows, so the filter can only ever offer a value
            // that actually matches something.
            'categories' => Game::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'total' => Game::count(),
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
