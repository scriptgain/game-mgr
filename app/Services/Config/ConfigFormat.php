<?php

namespace App\Services\Config;

/**
 * One game config file format.
 *
 * The contract that matters is apply(): it takes the file exactly as it is on
 * disk and gives back the same document with only the named keys changed.
 * Comments, blank lines, ordering, line endings and every key the panel has
 * never heard of survive untouched. Nothing here ever re-serialises a document
 * from a parsed structure, because that is precisely how a config editor
 * silently eats the four lines an operator added by hand.
 */
interface ConfigFormat
{
    /**
     * Every setting the document currently holds, as key => raw string value.
     * Keys are addressed the way the format is addressed: a flat name for
     * properties, "Section.Key" for ini, a dotted path for yaml, and the plain
     * tuple key for Palworld.
     */
    public function parse(string $raw): array;

    /**
     * $raw with only $values applied.
     *
     * @param  array<string,string>  $values
     * @param  array<int,string>  $skipped  keys the format could not place, out
     *                                      by reference so a caller can say so
     *                                      rather than pretend the save landed
     */
    public function apply(string $raw, array $values, array &$skipped = []): string;

    /** Human name for the format, used in the UI when a file will not parse. */
    public function label(): string;
}
