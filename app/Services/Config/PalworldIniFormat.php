<?php

namespace App\Services\Config;

/**
 * PalWorldSettings.ini, which is an INI file with one useful line in it:
 *
 *   [/Script/Pal.PalGameWorldSettings]
 *   OptionSettings=(Difficulty=None,DayTimeSpeedRate=1.000000,ServerName="A Server",...)
 *
 * Every setting a player cares about lives inside that single parenthesised
 * tuple, comma separated, with string values in double quotes. Palworld's
 * parser is unforgiving about it: a stray comma, quote or bracket inside a
 * value ends the tuple early and the game drops the entire file back to
 * defaults without printing anything. That is why the tuple is rebuilt from
 * its own original segments here rather than re-serialised. A key nobody
 * touched comes back as the exact bytes it went in as, "1.000000" included,
 * and a key from a newer build that this panel has never heard of is carried
 * through untouched.
 *
 * Keys are addressed by their plain name, because there is only one tuple.
 * Anything outside the OptionSettings line is left to the ordinary INI rules,
 * so "Section.Key" still works for the rest of the file.
 */
class PalworldIniFormat extends IniFormat
{
    /** The one line everything lives on. */
    private const TUPLE = '/^([ \t]*OptionSettings[ \t]*=[ \t]*\()(.*)(\)[ \t]*)$/';

    public function label(): string
    {
        return 'Palworld INI';
    }

    public function parse(string $raw): array
    {
        $doc = new LineDocument($raw);
        $out = parent::parse($raw);

        // The OptionSettings line itself is an ordinary INI key as far as the
        // parent is concerned, and exposing a 2 KiB blob as an editable value
        // is not useful, so it is dropped in favour of its contents.
        foreach ($out as $address => $value) {
            if (str_ends_with($address, 'OptionSettings')) {
                unset($out[$address]);
            }
        }

        $found = $this->tupleLine($doc);
        if ($found === null) {
            return $out;
        }

        foreach ($this->segments($found['inner']) as $segment) {
            if ($segment['key'] !== null) {
                $out[$segment['key']] = $segment['value'];
            }
        }

        return $out;
    }

    public function apply(string $raw, array $values, array &$skipped = []): string
    {
        $doc = new LineDocument($raw);
        $found = $this->tupleLine($doc);

        if ($found === null) {
            // No tuple means no place to put any of this. Saying so beats
            // writing a file the game will silently refuse.
            $skipped = array_map('strval', array_keys($values));

            return $raw;
        }

        $pending = $values;
        $rebuilt = [];

        foreach ($this->segments($found['inner']) as $segment) {
            if ($segment['key'] === null || ! array_key_exists($segment['key'], $pending)) {
                // Untouched: the original bytes go straight back.
                $rebuilt[] = $segment['raw'];

                continue;
            }

            $value = (string) $pending[$segment['key']];
            unset($pending[$segment['key']]);

            $rebuilt[] = $segment['key'].'='.$this->encode($value, $segment['quoted']);
        }

        // A key an older build never shipped is appended to the tuple, which is
        // exactly what the template's startup script does with sed.
        foreach ($pending as $key => $value) {
            $rebuilt[] = $key.'='.$this->encode((string) $value, ! is_numeric($value) && ! in_array($value, ['True', 'False'], true));
        }

        $doc->lines[$found['line']] = $found['prefix'].implode(',', $rebuilt).$found['suffix'];
        $skipped = [];

        return $doc->render();
    }

    /** @return array{line:int,prefix:string,inner:string,suffix:string}|null */
    private function tupleLine(LineDocument $doc): ?array
    {
        foreach ($doc->lines as $i => $line) {
            if (preg_match(self::TUPLE, $line, $m)) {
                return ['line' => $i, 'prefix' => $m[1], 'inner' => $m[2], 'suffix' => $m[3]];
            }
        }

        return null;
    }

    /**
     * Split the tuple on commas that are not inside a quoted value or a nested
     * bracket. Each segment keeps its original text so an untouched one can be
     * put back byte for byte.
     *
     * @return array<int,array{raw:string,key:?string,value:string,quoted:bool}>
     */
    private function segments(string $inner): array
    {
        if (trim($inner) === '') {
            return [];
        }

        $out = [];
        $buffer = '';
        $depth = 0;
        $quoted = false;
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];

            if ($char === '"') {
                $quoted = ! $quoted;
            } elseif (! $quoted && $char === '(') {
                $depth++;
            } elseif (! $quoted && $char === ')') {
                $depth--;
            } elseif (! $quoted && $depth === 0 && $char === ',') {
                $out[] = $this->segment($buffer);
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        $out[] = $this->segment($buffer);

        return $out;
    }

    /** @return array{raw:string,key:?string,value:string,quoted:bool} */
    private function segment(string $raw): array
    {
        $at = strpos($raw, '=');

        if ($at === false) {
            return ['raw' => $raw, 'key' => null, 'value' => '', 'quoted' => false];
        }

        $key = trim(substr($raw, 0, $at));
        $value = substr($raw, $at + 1);
        $isQuoted = strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"');

        return [
            'raw' => $raw,
            'key' => $key === '' ? null : $key,
            'value' => $isQuoted ? substr($value, 1, -1) : $value,
            'quoted' => $isQuoted,
        ];
    }

    /**
     * Strip the four characters that would end the tuple early and put the
     * quotes back on if the value had them. Validation rejects these before we
     * get here; this is the second lock on the door, because the cost of one
     * slipping through is a server that boots on default settings and a
     * customer who cannot see why.
     */
    private function encode(string $value, bool $quoted): string
    {
        $value = str_replace(['"', ',', '(', ')'], '', $value);

        return $quoted ? '"'.$value.'"' : $value;
    }
}
