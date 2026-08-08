@props(['label' => null, 'code' => null, 'empty' => 'Not set.', 'tall' => false])
{{-- A read-only block of shell or config with a copy button.

     x-copy-field is the right control for one short value on one line: an
     address, a token, an enrol command. A startup script is two thousand
     characters of multi-line shell, which an <input> cannot show at all, so it
     gets a capped pane that scrolls on its own instead of setting the height of
     the whole page.

     The copy button reads the pane's own text rather than a value duplicated
     into an attribute, so the script is not sent to the browser twice. --}}
@php $code = trim((string) $code); @endphp

<style>
    .gm-code-bar { display: flex; align-items: center; justify-content: space-between; gap: .75rem;
                   padding: .5rem .625rem .5rem .875rem; border-bottom: 1px solid rgba(255, 255, 255, .08); }
    .gm-code-title { font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
                     color: #94a3b8; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gm-code-copy { display: inline-flex; align-items: center; gap: .375rem; flex: 0 0 auto; padding: .25rem .625rem;
                    border-radius: .375rem; font-size: .75rem; font-weight: 500; color: #cbd5e1; cursor: pointer;
                    background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .08);
                    transition: background .15s, color .15s, border-color .15s; }
    .gm-code-copy:hover { background: rgba(255, 255, 255, .12); color: #fff; border-color: rgba(255, 255, 255, .28); }
    .gm-code-copy svg { width: .875rem; height: .875rem; }
    /* overflow-wrap:anywhere, not just white-space:pre-wrap. A 60 character
       path or an unbroken ${VAR:-default} run has no space to break at, and one
       such line is enough to set a horizontal floor for the entire page. */
    .gm-code-pre { margin: 0; padding: .75rem .875rem; max-height: 17rem; overflow-y: auto; overflow-x: hidden;
                   white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; tab-size: 2; }
    .gm-code-pre.is-tall { max-height: 26rem; }
</style>

@if ($code === '')
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500']) }}>
        {{ $empty }}
    </div>
@else
    <div {{ $attributes->merge(['class' => 'console-pane overflow-hidden']) }} x-data="copyPane">
        <div class="gm-code-bar">
            <span class="gm-code-title">{{ $label ?: 'Command' }}</span>
            <button type="button" class="gm-code-copy" @click="copy()">
                <x-icon name="copy" x-show="! copied" />
                <x-icon name="check" x-show="copied" x-cloak class="text-emerald-400" />
                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
            </button>
        </div>
        <pre class="gm-code-pre vx-scroll {{ $tall ? 'is-tall' : '' }}" x-ref="pane">{{ $code }}</pre>
    </div>
@endif
