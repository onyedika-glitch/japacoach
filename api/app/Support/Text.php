<?php

namespace App\Support;

class Text
{
    /**
     * Instruction appended to AI prompts: the product's writing style avoids long dashes.
     */
    public const NO_DASHES = 'Never use em dashes (—) or en dashes (–) in any text you write; use commas, colons or full stops instead.';

    /**
     * Replace long dashes in AI-written text: number ranges become "x to y", other dashes become commas.
     */
    public static function withoutDashes(string $text): string
    {
        $text = preg_replace('/(\d)\s*[–—]\s*(\d)/u', '$1 to $2', $text) ?? $text;
        $text = preg_replace('/\s*[–—]\s*/u', ', ', $text) ?? $text;

        return preg_replace('/,\s*([,.;:!?])/u', '$1', $text) ?? $text;
    }

    /**
     * Apply withoutDashes to every string inside a nested array.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function withoutDashesDeep(array $data): array
    {
        array_walk_recursive($data, function (mixed &$value): void {
            if (is_string($value)) {
                $value = self::withoutDashes($value);
            }
        });

        return $data;
    }
}
