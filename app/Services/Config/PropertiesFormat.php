<?php

namespace App\Services\Config;

/**
 * Java .properties, which is what Minecraft's server.properties is.
 *
 * Rules the format actually has, as opposed to the ones people assume:
 *   - "#" and "!" start a comment line.
 *   - The first unescaped "=", ":" or run of whitespace ends the key.
 *   - Backslash escapes survive into the value: \n, \t, \uXXXX and \\ all mean
 *     something. Minecraft writes a coloured MOTD as §c, so a parser that
 *     hands that back raw and then re-escapes it turns the file to noise.
 *
 * Only lines whose key is being changed are ever rewritten, and they are
 * rewritten in the separator the line already used.
 */
class PropertiesFormat implements ConfigFormat
{
    public function label(): string
    {
        return 'Properties';
    }

    public function parse(string $raw): array
    {
        $out = [];

        foreach ((new LineDocument($raw))->lines as $line) {
            $parsed = $this->split($line);
            if ($parsed !== null) {
                $out[$parsed['key']] = $this->unescape($parsed['value']);
            }
        }

        return $out;
    }

    public function apply(string $raw, array $values, array &$skipped = []): string
    {
        $doc = new LineDocument($raw);
        $pending = $values;

        foreach ($doc->lines as $i => $line) {
            $parsed = $this->split($line);
            if ($parsed === null || ! array_key_exists($parsed['key'], $pending)) {
                continue;
            }

            $value = (string) $pending[$parsed['key']];
            unset($pending[$parsed['key']]);

            // Rebuild from the pieces the line already had, so leading
            // whitespace and a "key : value" style separator both survive.
            $doc->lines[$i] = $parsed['indent'].$parsed['rawKey'].$parsed['separator'].$this->escape($value);
        }

        // A key the file never had is appended rather than dropped: the first
        // boot of a server writes a short server.properties and the panel is
        // often asking for something the game only adds later.
        foreach ($pending as $key => $value) {
            $doc->append($this->escape($key, true).'='.$this->escape((string) $value));
        }

        $skipped = [];

        return $doc->render();
    }

    /**
     * Split one line into its parts, or null when the line is a comment, blank
     * or something this parser has no business touching.
     *
     * @return array{indent:string,rawKey:string,key:string,separator:string,value:string}|null
     */
    private function split(string $line): ?array
    {
        if (! preg_match('/^([ \t\f]*)(.*)$/', $line, $m)) {
            return null;
        }

        [$indent, $body] = [$m[1], $m[2]];

        if ($body === '' || $body[0] === '#' || $body[0] === '!') {
            return null;
        }

        // Walk to the first separator that is not escaped. A key may contain an
        // escaped "=" or ":", which is rare but legal and cheap to honour.
        $length = strlen($body);
        $end = null;
        for ($i = 0; $i < $length; $i++) {
            if ($body[$i] === '\\') {
                $i++;

                continue;
            }
            if ($body[$i] === '=' || $body[$i] === ':' || $body[$i] === ' ' || $body[$i] === "\t") {
                $end = $i;

                break;
            }
        }

        if ($end === null || $end === 0) {
            return null;
        }

        $rawKey = substr($body, 0, $end);
        $rest = substr($body, $end);

        // The separator is any run of whitespace, then at most one = or :, then
        // any run of whitespace. Everything after that is the value.
        if (! preg_match('/^([ \t\f]*[=:]?[ \t\f]*)(.*)$/s', $rest, $sm)) {
            return null;
        }

        return [
            'indent' => $indent,
            'rawKey' => $rawKey,
            'key' => $this->unescape($rawKey),
            'separator' => $sm[1] === '' ? '=' : $sm[1],
            'value' => $sm[2],
        ];
    }

    /** Turn the file's escapes into the text a person typed. */
    private function unescape(string $value): string
    {
        $out = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] !== '\\' || $i + 1 >= $length) {
                $out .= $value[$i];

                continue;
            }

            $next = $value[++$i];

            if ($next !== 'u') {
                $out .= match ($next) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    'f' => "\f",
                    default => $next,
                };

                continue;
            }

            // A surrogate pair is two escapes that mean one character, so they
            // are collected and decoded together.
            $hex = substr($value, $i + 1, 4);
            if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
                $out .= 'u';

                continue;
            }

            $units = [hexdec($hex)];
            $i += 4;

            $tail = substr($value, $i + 1, 6);
            if ($units[0] >= 0xD800 && $units[0] <= 0xDBFF
                && strlen($tail) === 6 && str_starts_with($tail, '\\u') && ctype_xdigit(substr($tail, 2))) {
                $units[] = hexdec(substr($tail, 2));
                $i += 6;
            }

            $out .= mb_convert_encoding(pack('n*', ...$units), 'UTF-8', 'UTF-16BE');
        }

        return $out;
    }

    /** Write a value back the way java.util.Properties would. */
    private function escape(string $value, bool $isKey = false): string
    {
        $out = '';

        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $code = mb_ord($char, 'UTF-8');

            $out .= match (true) {
                $char === '\\' => '\\\\',
                $char === "\n" => '\\n',
                $char === "\t" => '\\t',
                $char === "\r" => '\\r',
                $char === "\f" => '\\f',
                $isKey && ($char === '=' || $char === ':' || $char === ' ') => '\\'.$char,
                $code !== false && ($code < 0x20 || $code > 0x7E) => $this->escapeCodepoint($code),
                default => $char,
            };
        }

        // A leading space would be eaten as part of the separator.
        return str_starts_with($out, ' ') ? '\\'.$out : $out;
    }

    /**
     * \uXXXX is a UTF-16 code unit, so anything past the basic plane, which is
     * every emoji somebody puts in a MOTD, has to go out as a surrogate pair.
     * One five digit escape would be read back as four digits and a stray "4".
     */
    private function escapeCodepoint(int $code): string
    {
        if ($code <= 0xFFFF) {
            return sprintf('\\u%04X', $code);
        }

        $code -= 0x10000;

        return sprintf('\\u%04X\\u%04X', 0xD800 + ($code >> 10), 0xDC00 + ($code & 0x3FF));
    }
}
