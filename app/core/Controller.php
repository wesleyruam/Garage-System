<?php

namespace App\Core;

abstract class Controller
{
    protected function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                error_response('JSON inválido.', 400);
                exit;
            }

            return $decoded;
        }

        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['PUT', 'PATCH', 'DELETE'], true)) {
            parse_str(file_get_contents('php://input') ?: '', $data);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    protected function validate(array $data, array $rules): void
    {
        $errors = validate($data, $rules);
        if ($errors !== []) {
            error_response('Dados inválidos.', 422, $errors);
            exit;
        }
    }

    protected function routeId(string $value): int
    {
        if (!preg_match('/^[1-9][0-9]*$/', $value)) {
            error_response('Identificador inválido.', 404);
            exit;
        }

        return (int) $value;
    }
}
