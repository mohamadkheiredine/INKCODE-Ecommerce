<?php

namespace App\Enums;

/**
 * The interface languages. Arabic is read right to left.
 */
enum Locale: string
{
    case En = 'en';
    case Ar = 'ar';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
