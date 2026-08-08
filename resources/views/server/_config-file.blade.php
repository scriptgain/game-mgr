{{-- One config file's worth of the Config tab.

     Its own partial because it is rendered two ways: inside a tab pane when a
     template has several files, and on its own when it has one, which is the
     common case. Duplicating this to get both would guarantee the two drift.

     $file      App\Support\ConfigFile
     $info      ['exists' => bool, 'values' => [key => value], 'supported' => bool]
     $server, $isAdmin, $canEdit --}}
@php
    $sections = $file->visibleSections($isAdmin);
@endphp

@if (! $info['supported'])
    <x-card :title="$file->label" icon="file">
        <x-empty-state icon="warning" title="Unsupported Format"
                       :description="'This template declares '.$file->path.' as a format this panel has no parser for. Edit it in the file manager instead.'" />
    </x-card>

@elseif (! $info['exists'])
    {{-- The normal case before a first boot. An empty form here would create a
         file holding nothing but the handful of keys the panel knows about,
         which most games read as a config they cannot use. --}}
    <x-card :title="$file->label" icon="file" :subtitle="$file->path">
        <x-empty-state icon="file" title="Not Written Yet"
                       description="{{ $file->label }} does not exist on this server yet. Games write their configuration the first time they start, so start the server once and this form fills itself in from the file the game wrote.">
            <x-slot:action>
                <x-button href="{{ route('server.console', $server) }}" variant="secondary" size="sm">
                    Go to the Console
                </x-button>
            </x-slot:action>
        </x-empty-state>
    </x-card>

@elseif ($sections === [])
    <x-card :title="$file->label" icon="file" :subtitle="$file->path">
        <x-empty-state icon="lock" title="Nothing To Configure"
                       description="This template exposes no settings in this file that you are allowed to see." />
    </x-card>

@else
    @foreach ($sections as $section => $settings)
        <x-card :title="$section === '' ? $file->label : $section" icon="sliders"
                :subtitle="$section === '' ? $file->path : null">
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($settings as $setting)
                    @php
                        $locked = ! $canEdit || (! $isAdmin && ! $setting->user_editable);
                        $current = $info['values'][$setting->key()] ?? $setting->default_value;
                    @endphp
                    @include('admin.servers._variable', [
                        'variable' => $setting,
                        'group' => 'settings',
                        'value' => old('settings.'.$setting->id, $current),
                        'locked' => $locked,
                    ])
                @endforeach
            </div>
        </x-card>
    @endforeach
@endif
