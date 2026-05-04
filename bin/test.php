<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers/utils.php';
load_env(base_path('.env'));

final class TestFailure extends RuntimeException
{
}

final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $json
    ) {
    }
}

final class IntegrationTestRunner
{
    private string $database = 'garage_system_test';
    private string $baseUrl = 'http://127.0.0.1:18080';
    private string $cookieAdmin;
    private string $cookieCliente;
    private mixed $server = null;
    private ?string $adminCsrf = null;
    private ?string $clienteCsrf = null;
    private int $clienteCriadoId = 0;
    private int $veiculoCriadoId = 0;
    private int $produtoCriadoId = 0;
    private int $ordemCriadaId = 0;
    private int $ordemOutroClienteId = 0;
    private array $results = [];

    public function __construct()
    {
        $this->cookieAdmin = sys_get_temp_dir() . '/garage_test_admin.cookies';
        $this->cookieCliente = sys_get_temp_dir() . '/garage_test_cliente.cookies';
        @unlink($this->cookieAdmin);
        @unlink($this->cookieCliente);
    }

    public function run(): void
    {
        try {
            $this->prepareDatabase();
            $this->startServer();

            $this->test('health endpoint exposes security headers', fn () => $this->health());
            $this->test('protected endpoints require authentication', fn () => $this->requiresAuth());
            $this->test('login requires csrf and valid credentials', fn () => $this->adminLogin());
            $this->test('admin can manage users', fn () => $this->adminUsers());
            $this->test('admin can create core resources', fn () => $this->coreCrud());
            $this->test('invalid route ids do not coerce to valid ids', fn () => $this->invalidId());
            $this->test('text payload is sanitized before persistence', fn () => $this->sanitizesPayload());
            $this->test('duplicates return json conflict instead of fatal error', fn () => $this->duplicateConflict());
            $this->test('customer portal only exposes own service orders', fn () => $this->customerPortalAndIdor());
            $this->test('login rate limit blocks repeated failures', fn () => $this->rateLimit());
        } finally {
            $this->stopServer();
        }

        $this->printSummary();
    }

    private function prepareDatabase(): void
    {
        $db = config('database');
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $safeDatabase = str_replace('`', '``', $this->database);
        $pdo->exec("DROP DATABASE IF EXISTS `{$safeDatabase}`");
        $pdo->exec("CREATE DATABASE `{$safeDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$safeDatabase}`");

        $files = glob(base_path('database/migrations/*.sql')) ?: [];
        sort($files);
        foreach ($files as $file) {
            $sql = trim((string) file_get_contents($file));
            if ($sql !== '') {
                $pdo->exec($sql);
            }
        }

        $seed = trim((string) file_get_contents(base_path('database/seeds/seed.sql')));
        if ($seed !== '') {
            $pdo->exec($seed);
        }

        $this->createOtherCustomerOrder($pdo);
    }

    private function createOtherCustomerOrder(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT INTO clientes (nome, cpf_cnpj, telefone, email, endereco, created_at, updated_at)
             VALUES ('Outro Cliente Teste', '55544433322', NULL, 'outro-test@example.com', NULL, NOW(), NOW())"
        );
        $clienteId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO veiculos (cliente_id, placa, modelo, ano, cor, km, created_at, updated_at)
             VALUES (:cliente_id, 'TST9B99', 'Modelo Outro', 2024, 'Branco', 500, NOW(), NOW())"
        );
        $stmt->execute(['cliente_id' => $clienteId]);
        $veiculoId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO ordens_servico
             (cliente_id, veiculo_id, status, valor_inicial, valor_final, descricao_problema, diagnostico, created_at, updated_at, finalizado_em)
             VALUES (:cliente_id, :veiculo_id, 'aberta', 80.00, NULL, 'OS de outro cliente', NULL, NOW(), NOW(), NULL)"
        );
        $stmt->execute(['cliente_id' => $clienteId, 'veiculo_id' => $veiculoId]);
        $this->ordemOutroClienteId = (int) $pdo->lastInsertId();
    }

    private function startServer(): void
    {
        $command = sprintf(
            'APP_ENV=testing APP_DEBUG=false DB_DATABASE=%s php -S 127.0.0.1:18080 -t public',
            escapeshellarg($this->database)
        );
        $this->server = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . '/garage_test_server.log', 'a'],
            2 => ['file', sys_get_temp_dir() . '/garage_test_server.log', 'a'],
        ], $pipes, base_path());

        if (!is_resource($this->server)) {
            throw new TestFailure('Não foi possível iniciar o servidor de teste.');
        }

        $deadline = microtime(true) + 5;
        do {
            usleep(100000);
            $response = $this->request('GET', '/api/health', null, null, false);
            if ($response?->status === 200) {
                return;
            }
        } while (microtime(true) < $deadline);

        throw new TestFailure('Servidor de teste não respondeu em tempo hábil.');
    }

    private function stopServer(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
    }

    private function test(string $name, callable $callback): void
    {
        try {
            $callback();
            $this->results[] = ['name' => $name, 'ok' => true];
            echo "[PASS] {$name}" . PHP_EOL;
        } catch (Throwable $exception) {
            $this->results[] = ['name' => $name, 'ok' => false, 'error' => $exception->getMessage()];
            echo "[FAIL] {$name}: {$exception->getMessage()}" . PHP_EOL;
        }
    }

    private function health(): void
    {
        $response = $this->request('GET', '/api/health');
        $this->assertStatus($response, 200);
        $this->assertSame('nosniff', $response->headers['x-content-type-options'] ?? null, 'X-Content-Type-Options ausente.');
        $this->assertSame('DENY', $response->headers['x-frame-options'] ?? null, 'X-Frame-Options ausente.');
        $this->assertFalse(isset($response->headers['x-powered-by']), 'X-Powered-By não deve ser exposto.');
    }

    private function requiresAuth(): void
    {
        $this->assertStatus($this->request('GET', '/api/clientes'), 401);
        $this->assertStatus($this->request('GET', '/api/admin/usuarios'), 401);
    }

    private function adminLogin(): void
    {
        $csrf = $this->csrf($this->cookieAdmin);
        $missingCsrf = $this->request('POST', '/api/login', [
            'email' => 'admin@garage.local',
            'senha' => 'Admin@123456',
        ], $this->cookieAdmin);
        $this->assertStatus($missingCsrf, 419);

        $login = $this->request('POST', '/api/login', [
            'email' => 'admin@garage.local',
            'senha' => 'Admin@123456',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $csrf]);
        $this->assertStatus($login, 200);
        $this->adminCsrf = $login->json['data']['csrf_token'] ?? null;
        $this->assertTrue(is_string($this->adminCsrf) && $this->adminCsrf !== $csrf, 'CSRF não foi rotacionado no login.');

        $me = $this->request('GET', '/api/me', null, $this->cookieAdmin);
        $this->assertStatus($me, 200);
        $this->assertSame('admin', $me->json['data']['tipo'] ?? null, 'Usuário admin não autenticou corretamente.');
    }

    private function adminUsers(): void
    {
        $list = $this->request('GET', '/api/admin/usuarios', null, $this->cookieAdmin);
        $this->assertStatus($list, 200);
        $this->assertTrue(count($list->json['data']) >= 2, 'Lista de usuários deveria conter admin e cliente seed.');

        $created = $this->request('POST', '/api/admin/usuarios', [
            'nome' => 'Mecanico Teste',
            'email' => 'mecanico-test@example.com',
            'senha' => 'Mecanico@123456',
            'tipo' => 'mecanico',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($created, 201);
        $this->assertSame('mecanico', $created->json['data']['tipo'] ?? null, 'Usuário de staff não foi criado.');
    }

    private function coreCrud(): void
    {
        $cliente = $this->request('POST', '/api/clientes', [
            'nome' => 'Cliente Teste Fluxo',
            'cpf_cnpj' => '12345678901',
            'telefone' => '11999999999',
            'email' => 'cliente-fluxo@example.com',
            'endereco' => 'Rua Teste, 10',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($cliente, 201);
        $this->clienteCriadoId = (int) $cliente->json['data']['id'];

        $veiculo = $this->request('POST', '/api/veiculos', [
            'cliente_id' => $this->clienteCriadoId,
            'placa' => 'TES1A23',
            'modelo' => 'Civic Teste',
            'ano' => 2023,
            'km' => 1200,
            'cor' => 'Cinza',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($veiculo, 201);
        $this->veiculoCriadoId = (int) $veiculo->json['data']['id'];

        $produto = $this->request('POST', '/api/produtos', [
            'nome' => 'Filtro Teste',
            'codigo' => 'FILTRO-TESTE',
            'preco_custo' => 10.5,
            'preco_venda' => 25.9,
            'estoque' => 3,
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($produto, 201);
        $this->produtoCriadoId = (int) $produto->json['data']['id'];

        $estoque = $this->request('POST', "/api/produtos/{$this->produtoCriadoId}/estoque", [
            'quantidade' => 2,
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($estoque, 200);
        $this->assertSame(5, $estoque->json['data']['estoque'] ?? null, 'Movimentação de estoque falhou.');

        $ordem = $this->request('POST', '/api/os', [
            'cliente_id' => $this->clienteCriadoId,
            'veiculo_id' => $this->veiculoCriadoId,
            'valor_inicial' => 150,
            'descricao_problema' => 'Barulho no freio',
            'diagnostico' => 'Aguardando análise',
            'agendado_para' => date('Y-m-d H:i:s'),
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($ordem, 201);
        $this->ordemCriadaId = (int) $ordem->json['data']['id'];

        $status = $this->request('PATCH', "/api/os/{$this->ordemCriadaId}/status", [
            'status' => 'em_andamento',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($status, 200);
        $this->assertSame('em_andamento', $status->json['data']['status'] ?? null, 'Atualização de status falhou.');

        $schedule = $this->request('PATCH', "/api/os/{$this->ordemCriadaId}/agenda", [
            'agendado_para' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($schedule, 200);

        $history = $this->request('GET', "/api/os/{$this->ordemCriadaId}/historico", null, $this->cookieAdmin);
        $this->assertStatus($history, 200);
        $this->assertTrue(count($history->json['data']) >= 1, 'Histórico da O.S. não registrou alterações.');

        $item = $this->request('POST', "/api/os/{$this->ordemCriadaId}/itens", [
            'tipo' => 'servico',
            'descricao' => 'Teste de rodagem',
            'quantidade' => 1,
            'valor_unitario' => 90,
            'custo_unitario' => 0,
            'desconto' => 0,
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($item, 201);

        $financial = $this->request('GET', "/api/os/{$this->ordemCriadaId}/financeiro", null, $this->cookieAdmin);
        $this->assertStatus($financial, 200);
        $this->assertSame(90.0, (float) $financial->json['data']['total'], 'Resumo financeiro não calculou total dos itens.');

        $approved = $this->request('POST', "/api/os/{$this->ordemCriadaId}/aprovar", [], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($approved, 200);
        $this->assertSame('aprovado', $approved->json['data']['orcamento_status'] ?? null, 'Orçamento não foi aprovado.');

        $payment = $this->request('POST', "/api/os/{$this->ordemCriadaId}/pagamentos", [
            'valor' => 40,
            'forma_pagamento' => 'pix',
            'observacao' => 'Pagamento de teste',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($payment, 201);
    }

    private function invalidId(): void
    {
        $response = $this->request('GET', '/api/clientes/1%20OR%201=1', null, $this->cookieAdmin);
        $this->assertStatus($response, 404);
    }

    private function sanitizesPayload(): void
    {
        $response = $this->request('POST', '/api/clientes', [
            'nome' => '<script>alert(1)</script>',
            'cpf_cnpj' => '77788899900',
            'telefone' => '<img src=x onerror=alert(1)>',
            'email' => 'xss-test@example.com',
            'endereco' => '<b>Rua HTML</b>',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($response, 201);
        $body = json_encode($response->json, JSON_THROW_ON_ERROR);
        $this->assertFalse(str_contains($body, '<script>') || str_contains($body, '<img') || str_contains($body, '<b>'), 'Payload HTML foi persistido sem sanitização.');
    }

    private function duplicateConflict(): void
    {
        $response = $this->request('POST', '/api/clientes', [
            'nome' => 'Duplicado',
            'cpf_cnpj' => '00000000000',
            'email' => 'duplicado@example.com',
        ], $this->cookieAdmin, true, ['X-CSRF-Token: ' . $this->adminCsrf]);
        $this->assertStatus($response, 409);
        $this->assertSame('application/json; charset=utf-8', $response->headers['content-type'] ?? null, 'Conflito deveria responder JSON.');
    }

    private function customerPortalAndIdor(): void
    {
        $csrf = $this->csrf($this->cookieCliente);
        $login = $this->request('POST', '/api/login', [
            'email' => 'cliente@garage.local',
            'senha' => 'Cliente@123456',
        ], $this->cookieCliente, true, ['X-CSRF-Token: ' . $csrf]);
        $this->assertStatus($login, 200);
        $this->clienteCsrf = $login->json['data']['csrf_token'] ?? null;

        $ownOrders = $this->request('GET', '/api/cliente/os', null, $this->cookieCliente);
        $this->assertStatus($ownOrders, 200);
        $this->assertTrue(count($ownOrders->json['data']) >= 1, 'Cliente deveria ver suas próprias O.S.');

        $this->assertStatus($this->request('GET', '/api/clientes', null, $this->cookieCliente), 403);
        $this->assertStatus($this->request('GET', '/api/os', null, $this->cookieCliente), 403);
        $this->assertStatus($this->request('GET', '/api/admin/usuarios', null, $this->cookieCliente), 403);

        $ownedId = (int) $ownOrders->json['data'][0]['id'];
        $this->assertStatus($this->request('GET', "/api/cliente/os/{$ownedId}", null, $this->cookieCliente), 200);
        $this->assertStatus($this->request('GET', "/api/cliente/os/{$this->ordemOutroClienteId}", null, $this->cookieCliente), 404);
        $this->assertStatus($this->request('GET', "/api/os/{$this->ordemOutroClienteId}", null, $this->cookieCliente), 404);
    }

    private function rateLimit(): void
    {
        $csrf = $this->clienteCsrf ?: $this->csrf($this->cookieCliente);
        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->request('POST', '/api/login', [
                'email' => 'rate-test@example.com',
                'senha' => 'senha-errada',
            ], $this->cookieCliente, true, ['X-CSRF-Token: ' . $csrf]);
        }

        $this->assertStatus($last, 429);
    }

    private function csrf(string $cookieJar): string
    {
        $response = $this->request('GET', '/api/csrf', null, $cookieJar);
        $this->assertStatus($response, 200);
        $token = $response->json['data']['token'] ?? null;
        $this->assertTrue(is_string($token) && strlen($token) === 64, 'Token CSRF inválido.');
        return $token;
    }

    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        ?string $cookieJar = null,
        bool $decodeJson = true,
        array $headers = []
    ): ?HttpResponse {
        $curl = curl_init($this->baseUrl . $path);
        $responseHeaders = [];

        $headers[] = 'Accept: application/json';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line !== '' && str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return $length;
            },
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 5,
        ]);

        if ($cookieJar !== null) {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieJar);
        }

        $body = curl_exec($curl);
        if ($body === false) {
            curl_close($curl);
            return null;
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $json = [];
        if ($decodeJson && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        return new HttpResponse($status, $responseHeaders, $body, $json);
    }

    private function assertStatus(?HttpResponse $response, int $status): void
    {
        $this->assertTrue($response instanceof HttpResponse, "Resposta HTTP ausente; esperado {$status}.");
        $this->assertSame($status, $response->status, "Status esperado {$status}, recebido {$response->status}. Body: {$response->body}");
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new TestFailure($message . ' Esperado: ' . var_export($expected, true) . ', recebido: ' . var_export($actual, true));
        }
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new TestFailure($message);
        }
    }

    private function assertFalse(bool $condition, string $message): void
    {
        $this->assertTrue(!$condition, $message);
    }

    private function printSummary(): void
    {
        $failed = array_filter($this->results, fn (array $result) => !$result['ok']);
        echo PHP_EOL . count($this->results) . ' testes executados, ' . count($failed) . ' falhas.' . PHP_EOL;

        if ($failed !== []) {
            exit(1);
        }
    }
}

(new IntegrationTestRunner())->run();
