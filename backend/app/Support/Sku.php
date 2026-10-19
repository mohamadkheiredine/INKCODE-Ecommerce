<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * What SKUs and the codes they are built from look like: capital Latin letters and
 * digits in groups joined by single dashes ("TSHIRT-RED-M").
 */
final class Sku
{
    public const string PATTERN = '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/';

    public const int MAX_LENGTH = 64;

    /**
     * A code from free text: "Classic Tee" → "CLASSIC-TEE", at most `$maxLength`
     * characters, never ending on a dash. Empty when the text has no Latin letters or
     * digits after transliteration (an Arabic name, for example).
     */
    public static function codeFrom(string $text, int $maxLength): string
    {
        return rtrim(Str::limit(Str::upper(Str::slug($text)), $maxLength, ''), '-');
    }
}
