<?php

namespace App\Services;

use App\Core\Database;

class RateLimiterService
{
    public function tooManyAttempts(string $key, int $maxAttempts, int $lockoutMinutes): bool
    {
        $this->clearExpired($lockoutMinutes);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE attempt_key = :attempt_key AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue('attempt_key', $key);
        $stmt->bindValue('minutes', $lockoutMinutes, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    public function hit(string $key): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO login_attempts (attempt_key, ip_address, user_agent, created_at)
             VALUES (:attempt_key, :ip_address, :user_agent, :created_at)'
        );
        $stmt->execute([
            'attempt_key' => $key,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => now(),
        ]);
    }

    public function clear(string $key): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM login_attempts WHERE attempt_key = :attempt_key');
        $stmt->execute(['attempt_key' => $key]);
    }

    private function clearExpired(int $lockoutMinutes): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue('minutes', $lockoutMinutes, \PDO::PARAM_INT);
        $stmt->execute();
    }
}
