<?php
declare(strict_types=1);

namespace RedeAlabama\Http;

abstract class Controller
{
    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function view(string $viewFile, array $data = []): void
    {
        extract($data, EXTR_OVERWRITE);
        require $viewFile;
    }
}
