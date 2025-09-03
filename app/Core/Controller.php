<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $template, array $params = []): void
    {
        View::render($template, $params);
    }
}