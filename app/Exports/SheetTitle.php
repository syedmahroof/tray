<?php

namespace App\Exports;

use Illuminate\Support\Str;

class SheetTitle
{
    /**
     * Make a string usable as a worksheet name: Excel forbids : \ / ? * [ ]
     * and caps the name at 31 characters.
     */
    public static function sanitise(string $title): string
    {
        $clean = trim((string) preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/', ' ', $title));
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);

        return $clean === '' ? 'Sheet' : Str::limit($clean, 31, '');
    }
}
