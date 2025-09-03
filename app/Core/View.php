<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static string $basePath = __DIR__ . '/../../views';

    public static function render(string $template, array $params = []): void
    {
        $file = rtrim(self::$basePath, '/\\') . '/' . ltrim($template, '/\\') . '.php';
        if (!file_exists($file)) {
            http_response_code(500);
            echo 'Template not found: ' . htmlspecialchars($template);
            return;
        }
        extract($params, EXTR_OVERWRITE);
        include $file;
    }
}