<?php

namespace App\Support;

final class UserPair
{
    /** @return array{0: string, 1: string} */
    public static function ordered(string $first, string $second): array
    {
        return strcmp($first, $second) < 0 ? [$first, $second] : [$second, $first];
    }

    public static function key(string $first, string $second): string
    {
        return implode(':', self::ordered($first, $second));
    }
}
