<?php

namespace App\Services\Config;

/**
 * The flavour of YAML that Bukkit, Spigot and Paper configs are written in:
 * nested mappings of scalars, generously commented, with the comments carrying
 * most of the documentation anybody has for those files.
 *
 * This deliberately does not parse YAML into a structure and dump it back.
 * Every YAML library on earth drops comments on a round trip, and bukkit.yml
 * is roughly half comments, so a "save" through one of those would hand a
 * customer back a file they can no longer read. Instead this walks the
 * document line by line, tracks the indentation stack to know what path each
 * line is on, and rewrites the scalar on exactly the lines being changed.
 * Everything else, comments included, is never looked at twice.
 *
 * Keys are addressed by dotted path, so "spawn-limits.monsters" is the
 * monsters key under the spawn-limits mapping. Sequences are skipped: a list
 * has no scalar to put a form control on, so the honest thing is to leave it
 * to the file manager.
 */
class YamlFormat implements ConfigFormat
{
    public function label(): string
    {
        return 'YAML';
    }

    public function parse(string $raw): array
    {
        $out = [];

        foreach ($this->scan(new LineDocument($raw)) as $entry) {
            if ($entry['leaf']) {
                $out[$entry['path']] = $entry['value'];
            }
        }

        return $out;
    }

    public function apply(string $raw, array $values, array &$skipped = []): string
    {
        $doc = new LineDocument($raw);
        $entries = $this->scan($doc);
        $pending = $values;

        foreach ($entries as $entry) {
            if (! $entry['leaf'] || ! array_key_exists($entry['path'], $pending)) {
                continue;
            }

            $value = (string) $pending[$entry['path']];
            unset($pending[$entry['path']]);

            $doc->lines[$entry['line']] = $entry['indent'].$entry['rawKey'].':'.$entry['gap']
                .$this->encode($value, $entry['quote']).$entry['comment'];
        }

        $skipped = [];

        // Inserting is done from the bottom of the file up, so a line number
        // worked out from the original scan is still correct when we get to it.
        $inserts = [];
        foreach ($pending as $path => $value) {
            $at = $this->placeFor($entries, (string) $path);

            if ($at === null) {
                $skipped[] = (string) $path;

                continue;
            }

            $inserts[] = $at + ['value' => (string) $value, 'path' => (string) $path];
        }

        usort($inserts, fn ($a, $b) => $b['line'] <=> $a['line']);

        foreach ($inserts as $insert) {
            $dot = strrpos($insert['path'], '.');
            $leaf = $dot === false ? $insert['path'] : substr($insert['path'], $dot + 1);
            $doc->insert($insert['line'], $insert['indent'].$leaf.': '.$this->encode($insert['value'], ''));
        }

        return $doc->render();
    }

    /**
     * Every mapping line in the document, with the path it sits on.
     *
     * @return array<int,array{line:int,indent:string,depth:int,rawKey:string,path:string,gap:string,value:string,quote:string,comment:string,leaf:bool}>
     */
    private function scan(LineDocument $doc): array
    {
        $out = [];
        /** @var array<int,array{depth:int,key:string}> $stack */
        $stack = [];

        foreach ($doc->lines as $i => $line) {
            $trimmed = ltrim($line);

            // Blank lines, comments, document markers and sequence items carry
            // no addressable scalar.
            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === '-'
                || str_starts_with($trimmed, '...')) {
                continue;
            }

            if (! preg_match('/^([ ]*)([^\s#:][^:]*):(\s*)(.*)$/', $line, $m)) {
                continue;
            }

            [$indent, $rawKey, $gap, $rest] = [$m[1], $m[2], $m[3], $m[4]];
            $depth = strlen($indent);

            while ($stack !== [] && $stack[count($stack) - 1]['depth'] >= $depth) {
                array_pop($stack);
            }

            $path = implode('.', array_column($stack, 'key'));
            $path = ($path === '' ? '' : $path.'.').trim($rawKey);

            [$value, $quote, $comment] = $this->splitValue($rest);
            $leaf = trim($rest) !== '' && ! str_starts_with(ltrim($rest), '#');

            $out[] = [
                'line' => $i, 'indent' => $indent, 'depth' => $depth, 'rawKey' => $rawKey,
                'path' => $path, 'gap' => $gap === '' ? ' ' : $gap,
                'value' => $value, 'quote' => $quote, 'comment' => $comment, 'leaf' => $leaf,
            ];

            if (! $leaf) {
                $stack[] = ['depth' => $depth, 'key' => trim($rawKey)];
            }
        }

        return $out;
    }

    /**
     * Where a key the file does not have should go: directly after the last
     * line belonging to its parent mapping, at the indentation its siblings
     * use. A path whose parent does not exist gets no guess at all, because
     * inventing three levels of nesting in somebody's bukkit.yml is a worse
     * outcome than telling them the key is not there.
     *
     * @param  array<int,array<string,mixed>>  $entries
     * @return array{line:int,indent:string}|null
     */
    private function placeFor(array $entries, string $path): ?array
    {
        $dot = strrpos($path, '.');

        if ($dot === false) {
            $last = $entries === [] ? -1 : (int) $entries[count($entries) - 1]['line'];

            return ['line' => $last + 1, 'indent' => ''];
        }

        $parent = substr($path, 0, $dot);
        $parentEntry = null;

        foreach ($entries as $entry) {
            if ($entry['path'] === $parent && ! $entry['leaf']) {
                $parentEntry = $entry;
            }
        }

        if ($parentEntry === null) {
            return null;
        }

        $indent = null;
        $after = (int) $parentEntry['line'];

        foreach ($entries as $entry) {
            if ((int) $entry['line'] <= (int) $parentEntry['line']) {
                continue;
            }
            if ((int) $entry['depth'] <= (int) $parentEntry['depth']) {
                break;
            }
            $after = (int) $entry['line'];
            $indent ??= (string) $entry['indent'];
        }

        return ['line' => $after + 1, 'indent' => $indent ?? $parentEntry['indent'].'  '];
    }

    /**
     * Split "value # comment" into its parts. An inline comment in YAML needs
     * whitespace in front of the "#", which is what keeps a colour code like
     * "&a#1" from being read as one.
     *
     * @return array{0:string,1:string,2:string} value, quote character, comment
     */
    private function splitValue(string $rest): array
    {
        if ($rest === '' || str_starts_with(ltrim($rest), '#')) {
            return ['', '', $rest === '' ? '' : ' '.ltrim($rest)];
        }

        $first = $rest[0];

        if ($first === '"' || $first === "'") {
            $length = strlen($rest);
            for ($i = 1; $i < $length; $i++) {
                if ($rest[$i] === '\\' && $first === '"') {
                    $i++;

                    continue;
                }
                if ($rest[$i] !== $first) {
                    continue;
                }
                // '' inside a single quoted scalar is an escaped quote.
                if ($first === "'" && ($rest[$i + 1] ?? '') === "'") {
                    $i++;

                    continue;
                }

                $value = substr($rest, 1, $i - 1);

                return [
                    $first === "'" ? str_replace("''", "'", $value) : stripcslashes($value),
                    $first,
                    substr($rest, $i + 1),
                ];
            }
        }

        if (preg_match('/^(.*?)(\s+#.*)$/', $rest, $m)) {
            return [rtrim($m[1]), '', $m[2]];
        }

        return [rtrim($rest), '', ''];
    }

    /** Write a scalar back, quoting only when YAML would otherwise misread it. */
    private function encode(string $value, string $quote): string
    {
        if ($quote === '"') {
            return '"'.addcslashes($value, "\"\\\n\t").'"';
        }

        $needsQuotes = $value === ''
            || trim($value) !== $value
            || str_contains($value, ': ')
            || str_contains($value, ' #')
            || str_ends_with($value, ':')
            || preg_match('/^[&*!|>%@`{}\[\],?-]/', $value) === 1
            || preg_match('/^(yes|no|on|off|null|~)$/i', $value) === 1;

        if ($quote === "'" || $needsQuotes) {
            return "'".str_replace("'", "''", $value)."'";
        }

        return $value;
    }
}
