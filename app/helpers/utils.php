<?php

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false || $value === null ? $default : $value;
}

function config(string $key, mixed $default = null): mixed
{
    static $config = [];

    [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
    if (!isset($config[$file])) {
        $path = base_path("config/{$file}.php");
        $config[$file] = is_file($path) ? require $path : [];
    }

    return $item === null ? $config[$file] : ($config[$file][$item] ?? $default);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean_string(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim(strip_tags($value));
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
}

function clean_payload(array $data, array $fields): array
{
    foreach ($fields as $field) {
        if (array_key_exists($field, $data) && is_string($data[$field])) {
            $data[$field] = clean_string($data[$field]);
        }
    }

    return $data;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
