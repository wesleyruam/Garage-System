<?php

namespace App\Services;

use App\Core\Session;
use App\Models\OrdemServico;

class AuthorizationService
{
    public static function isStaff(): bool
    {
        return in_array(Session::get('user_tipo'), ['admin', 'atendente', 'mecanico'], true);
    }

    public static function isAdmin(): bool
    {
        return Session::get('user_tipo') === 'admin';
    }

    public static function canViewOrdemServico(OrdemServico $ordem): bool
    {
        if (self::isStaff()) {
            return true;
        }

        return Session::get('user_tipo') === 'cliente'
            && (int) Session::get('cliente_id') === $ordem->cliente_id;
    }
}
