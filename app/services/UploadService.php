<?php

namespace App\Services;

class UploadService
{
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function store(array $file, string $folder = 'documents'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            error_response('Falha no upload.', 422);
            exit;
        }

        if (($file['size'] ?? 0) > config('app.max_upload_bytes')) {
            error_response('Arquivo excede o tamanho permitido.', 422);
            exit;
        }

        $original = $file['name'] ?? '';
        $extension = mb_strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$extension])) {
            error_response('Extensão de arquivo não permitida.', 422);
            exit;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED[$extension], true)) {
            error_response('Conteúdo do arquivo não corresponde à extensão.', 422);
            exit;
        }

        $base = config('app.uploads_path') . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $folder);
        if (!is_dir($base)) {
            mkdir($base, 0750, true);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $base . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            error_response('Não foi possível salvar o arquivo.', 500);
            exit;
        }

        chmod($target, 0640);
        return $target;
    }
}
