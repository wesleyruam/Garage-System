<?php

namespace App\Services;

class NotificacaoService
{
    public function registrar(string $mensagem): void
    {
        error_log('[notificacao] ' . $mensagem);
    }
}
