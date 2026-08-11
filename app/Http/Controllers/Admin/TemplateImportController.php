<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Game;
use App\Services\TemplateImporter;
use App\Support\Edition;
use Illuminate\Http\Request;

/**
 * Import an existing template definition.
 *
 * Strategically the most important screen in the admin area. The rival panel's
 * real moat is not its code, it is the thousands of community-written template
 * definitions covering every game anyone has asked for. Reading that format
 * means GameMGR starts with the whole catalogue instead of an empty one.
 *
 * The parser lives in TemplateImporter, named after the file format it reads. That
 * name is internal only: nothing the user sees mentions it.
 */
class TemplateImportController extends Controller
{
    public function show()
    {
        return view('admin.templates.import', [
            'title' => 'Import Template',
            'games' => Game::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, TemplateImporter $importer)
    {
        // Importing a definition is what turns GameMGR from "the games we ship" into
        // "any game with a Pterodactyl egg", which is most of the value of the
        // paid editions and the one thing the free edition holds back.
        if (! Edition::allows('templates.import')) {
            $needs = Edition::cheapestWith('templates.import');

            return back()->with('error', sprintf(
                'Importing templates is not included in the %s edition.%s Templates already imported keep working.',
                Edition::label(),
                $needs ? ' It is included from '.Edition::label($needs).' upwards.' : ''
            ));
        }

        $request->validate([
            'json' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:4096'],
            'game_id' => ['nullable', 'exists:games,id'],
        ]);

        $source = null;
        $json = $request->input('json');

        if ($request->hasFile('file')) {
            $json = file_get_contents($request->file('file')->getRealPath());
            $source = $request->file('file')->getClientOriginalName();
        }

        if (blank($json)) {
            return back()->with('error', 'Paste the template JSON or choose a file first.');
        }

        try {
            $template = $importer->importJson($json, $request->input('game_id') ? (int) $request->input('game_id') : null, $source);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', 'That template could not be imported: '.$e->getMessage())->withInput();
        }

        AuditLog::record('template.import', 'Imported template "'.$template->name.'"', $template);

        $message = 'Imported "'.$template->name.'" with '.$template->variables->count().' '
            .\Illuminate\Support\Str::plural('variable', $template->variables->count()).'.';

        return redirect()->route('admin.templates.show', $template)
            ->with('status', $message)
            ->with('import_warnings', $importer->warnings);
    }
}
