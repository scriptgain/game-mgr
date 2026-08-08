<?php

namespace App\Services\Config;

/**
 * Plain INI: "[Section]" headers, "key=value" lines, ";" and "#" comments.
 *
 * Keys are addressed as "Section.Key", or bare for anything above the first
 * header. Values are handed back exactly as they appear after the "=", with
 * only surrounding whitespace trimmed, and written back the same way: no
 * quoting, no type guessing, no reformatting. An INI file is whatever the game
 * that wrote it says it is, and second guessing that is how a config editor
 * turns a working file into a broken one.
 *
 * A line that is edited keeps its own spelling of the key and its own spacing
 * around the "=". A trailing comment on an edited line does not survive,
 * because ";" is a legal character in an INI value and there is no way to tell
 * the two apart without knowing the game.
 */
class IniFormat implements ConfigFormat
{
    public function label(): string
    {
        return 'INI';
    }

    public function parse(string $raw): array
    {
        $out = [];

        foreach ($this->scan(new LineDocument($raw)) as $entry) {
            $out[$entry['address']] = $entry['value'];
        }

        return $out;
    }

    public function apply(string $raw, array $values, array &$skipped = []): string
    {
        $doc = new LineDocument($raw);
        $pending = $values;

        foreach ($this->scan($doc) as $entry) {
            if (! array_key_exists($entry['address'], $pending)) {
                continue;
            }

            $value = (string) $pending[$entry['address']];
            unset($pending[$entry['address']]);

            $doc->lines[$entry['line']] = $entry['prefix'].$value;
        }

        $skipped = [];

        foreach ($pending as $address => $value) {
            if (! $this->insert($doc, (string) $address, (string) $value)) {
                $skipped[] = (string) $address;
            }
        }

        return $doc->render();
    }

    /**
     * Every key line in the document.
     *
     * @return array<int,array{line:int,section:string,key:string,address:string,prefix:string,value:string}>
     */
    protected function scan(LineDocument $doc): array
    {
        $out = [];
        $section = '';

        foreach ($doc->lines as $i => $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed[0] === ';' || $trimmed[0] === '#') {
                continue;
            }

            if (preg_match('/^\[(.*)\]$/', $trimmed, $m)) {
                $section = trim($m[1]);

                continue;
            }

            if (! preg_match('/^([ \t]*)([^=\[\]]+?)([ \t]*=[ \t]*)(.*)$/s', $line, $m)) {
                continue;
            }

            $key = $m[2];
            $out[] = [
                'line' => $i,
                'section' => $section,
                'key' => $key,
                'address' => $section === '' ? $key : $section.'.'.$key,
                'prefix' => $m[1].$m[2].$m[3],
                'value' => rtrim($m[4]),
            ];
        }

        return $out;
    }

    /** Put a key the file never had into the right section, or say we cannot. */
    private function insert(LineDocument $doc, string $address, string $value): bool
    {
        $dot = strrpos($address, '.');
        $section = $dot === false ? '' : substr($address, 0, $dot);
        $key = $dot === false ? $address : substr($address, $dot + 1);

        if ($section === '') {
            // Above the first header, so it goes above the first header.
            $at = 0;
            foreach ($doc->lines as $i => $line) {
                if (preg_match('/^\s*\[.*\]\s*$/', $line)) {
                    $at = $i;

                    break;
                }
                $at = $i + 1;
            }
            $doc->insert($at, $key.'='.$value);

            return true;
        }

        $inSection = false;
        $lastOfSection = null;

        foreach ($doc->lines as $i => $line) {
            $trimmed = trim($line);

            if (preg_match('/^\[(.*)\]$/', $trimmed, $m)) {
                if ($inSection) {
                    break;
                }
                $inSection = trim($m[1]) === $section;
                if ($inSection) {
                    $lastOfSection = $i;
                }

                continue;
            }

            if ($inSection && $trimmed !== '') {
                $lastOfSection = $i;
            }
        }

        if ($lastOfSection === null) {
            $doc->append('['.$section.']');
            $doc->append($key.'='.$value);

            return true;
        }

        $doc->insert($lastOfSection + 1, $key.'='.$value);

        return true;
    }
}
