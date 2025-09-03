<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string,mixed> */
    private static array $values = [];

    /**
     * @param array<string,mixed> $config
     */
    public static function init(array $config): void
    {
        self::$values = $config + self::$values;
    }

    public static function get(string $key, $default = null)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
    }
}
