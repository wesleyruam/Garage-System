<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Usuario;
use App\Repositories\UsuarioRepository;

class AuthService
{
    public function __construct(
        private readonly UsuarioRepository $users = new UsuarioRepository(),
        private readonly RateLimiterService $limiter = new RateLimiterService()
    ) {
    }

    public function register(array $data): Usuario
    {
        if ($this->users->findByEmail($data['email'])) {
            error_response('E-mail já cadastrado.', 409);
            exit;
        }

        return $this->users->create($data);
    }

    public function login(string $email, string $password): ?Usuario
    {
        $key = $this->limiterKey($email);
        $max = config('app.max_login_attempts', 5);
        $minutes = config('app.login_lockout_minutes', 15);

        if ($this->limiter->tooManyAttempts($key, $max, $minutes)) {
            error_response('Muitas tentativas inválidas. Tente novamente mais tarde.', 429);
            exit;
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !$user->ativo || !password_verify($password, $user->senha)) {
            $this->limiter->hit($key);
            return null;
        }

        if (password_needs_rehash($user->senha, PASSWORD_DEFAULT)) {
            // Rehash pode ser adicionado aqui sem alterar contrato público.
        }

        $this->limiter->clear($key);
        Session::regenerate();
        Session::put('user_id', $user->id);
        Session::put('user_tipo', $user->tipo);
        Session::put('cliente_id', $user->cliente_id);

        return $user;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    private function limiterKey(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}
