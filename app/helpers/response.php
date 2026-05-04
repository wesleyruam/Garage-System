<?php

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function success_response(mixed $data = null, int $status = 200, string $message = 'OK'): void
{
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ], $status);
}

function error_response(string $message, int $status = 400, array $errors = []): void
{
    json_response([
        'success' => false,
        'message' => $message,
        'errors' => $errors,
    ], $status);
}
