<?php

namespace App\Services\Config;

/**
 * A text file split into lines with its line endings remembered.
 *
 * Every format here edits by line, so they all need the same two things: the
 * content of each line without its terminator, and the exact terminator that
 * followed it. Reassembling from those two is byte identical to the input,
 * including a file that ends without a newline and a file that uses CRLF on
 * some lines and LF on others, which is what a Windows editor leaves behind
 * after somebody has hand edited a Linux game's config.
 */
class LineDocument
{
    /** @var array<int,string> */
    public array $lines = [];

    /** @var array<int,string> the terminator that followed each line */
    public array $endings = [];

    public function __construct(string $raw)
    {
        $offset = 0;
        $length = strlen($raw);

        while ($offset < $length) {
            $break = strcspn($raw, "\r\n", $offset);
            $this->lines[] = substr($raw, $offset, $break);
            $offset += $break;

            $ending = '';
            if ($offset < $length) {
                if (substr($raw, $offset, 2) === "\r\n") {
                    $ending = "\r\n";
                } else {
                    $ending = $raw[$offset];
                }
                $offset += strlen($ending);
            }
            $this->endings[] = $ending;
        }
    }

    public function render(): string
    {
        $out = '';
        foreach ($this->lines as $i => $line) {
            $out .= $line.($this->endings[$i] ?? '');
        }

        return $out;
    }

    /** The terminator this file mostly uses, for lines we have to invent. */
    public function preferredEnding(): string
    {
        $crlf = 0;
        foreach ($this->endings as $ending) {
            if ($ending === "\r\n") {
                $crlf++;
            }
        }

        return $crlf > 0 && $crlf * 2 >= count($this->endings) ? "\r\n" : "\n";
    }

    /**
     * Append a line to the end of the document, giving the previously last
     * line a terminator first if it did not have one.
     */
    public function append(string $line): void
    {
        $last = count($this->lines) - 1;
        if ($last >= 0 && $this->endings[$last] === '') {
            $this->endings[$last] = $this->preferredEnding();
        }

        $this->lines[] = $line;
        $this->endings[] = '';
    }

    /** Insert a line so it becomes index $at, pushing the rest down. */
    public function insert(int $at, string $line): void
    {
        if ($at >= count($this->lines)) {
            $this->append($line);

            return;
        }

        array_splice($this->lines, $at, 0, [$line]);
        array_splice($this->endings, $at, 0, [$this->preferredEnding()]);
    }
}
