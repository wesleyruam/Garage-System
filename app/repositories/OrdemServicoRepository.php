<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\OrdemServico;

class OrdemServicoRepository
{
    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM ordens_servico ORDER BY id DESC LIMIT 200');
        return array_map(fn (array $row) => $this->map($row)->toArray(), $stmt->fetchAll());
    }

    public function allByCliente(int $clienteId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM ordens_servico WHERE cliente_id = :cliente_id ORDER BY id DESC LIMIT 200'
        );
        $stmt->execute(['cliente_id' => $clienteId]);
        return array_map(fn (array $row) => $this->map($row)->toArray(), $stmt->fetchAll());
    }

    public function find(int $id): ?OrdemServico
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ordens_servico WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function findForCliente(int $id, int $clienteId): ?OrdemServico
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM ordens_servico WHERE id = :id AND cliente_id = :cliente_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'cliente_id' => $clienteId]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function create(array $data): OrdemServico
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO ordens_servico
             (cliente_id, veiculo_id, status, orcamento_status, valor_inicial, valor_final, descricao_problema, diagnostico, agendado_para, created_at, updated_at, finalizado_em)
             VALUES
             (:cliente_id, :veiculo_id, :status, :orcamento_status, :valor_inicial, :valor_final, :descricao_problema, :diagnostico, :agendado_para, :created_at, :updated_at, :finalizado_em)'
        );
        $stmt->execute([
            'cliente_id' => $data['cliente_id'],
            'veiculo_id' => $data['veiculo_id'],
            'status' => $data['status'] ?? 'orcamento',
            'orcamento_status' => $data['orcamento_status'] ?? 'rascunho',
            'valor_inicial' => $data['valor_inicial'] ?? 0,
            'valor_final' => $data['valor_final'] ?? null,
            'descricao_problema' => $data['descricao_problema'],
            'diagnostico' => $data['diagnostico'] ?? null,
            'agendado_para' => $data['agendado_para'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
            'finalizado_em' => null,
        ]);

        return $this->find((int) Database::connection()->lastInsertId());
    }

    public function updateStatus(int $id, string $status, ?float $valorFinal = null, ?int $usuarioId = null): ?OrdemServico
    {
        $before = $this->find($id);
        $finishedAt = $status === 'finalizada' ? now() : null;
        $stmt = Database::connection()->prepare(
            'UPDATE ordens_servico SET status = :status, valor_final = :valor_final,
             finalizado_em = :finalizado_em, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'valor_final' => $valorFinal,
            'finalizado_em' => $finishedAt,
            'updated_at' => now(),
        ]);
        if ($before && $usuarioId) {
            $this->logChange($id, $usuarioId, 'Status alterado', $before->status, $status);
        }

        return $this->find($id);
    }

    public function approveBudget(int $id, int $usuarioId): ?OrdemServico
    {
        $before = $this->find($id);
        $stmt = Database::connection()->prepare(
            "UPDATE ordens_servico SET status = 'aprovada', orcamento_status = 'aprovado', updated_at = :updated_at WHERE id = :id"
        );
        $stmt->execute(['id' => $id, 'updated_at' => now()]);

        if ($before) {
            $this->logChange($id, $usuarioId, 'Orçamento aprovado', $before->orcamento_status, 'aprovado');
        }

        return $this->find($id);
    }

    public function items(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, p.codigo AS produto_codigo
             FROM os_itens i
             LEFT JOIN produtos p ON p.id = i.produto_id
             WHERE i.os_id = :id
             ORDER BY i.id DESC'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function addItem(int $id, int $usuarioId, array $data): array
    {
        return Database::transaction(function () use ($id, $usuarioId, $data) {
            $tipo = $data['tipo'];
            $produtoId = isset($data['produto_id']) ? (int) $data['produto_id'] : null;
            $descricao = $data['descricao'] ?? '';
            $quantidade = (float) $data['quantidade'];
            $custo = (float) ($data['custo_unitario'] ?? 0);
            $valor = (float) $data['valor_unitario'];
            $desconto = (float) ($data['desconto'] ?? 0);

            if ($tipo === 'peca') {
                $stmt = Database::connection()->prepare('SELECT * FROM produtos WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $produtoId]);
                $produto = $stmt->fetch();
                if (!$produto) {
                    error_response('Produto não encontrado.', 422);
                    exit;
                }
                if ((int) $produto['estoque'] < $quantidade) {
                    error_response('Estoque insuficiente para reservar peça.', 422);
                    exit;
                }
                $descricao = $descricao ?: $produto['nome'];
                $custo = $custo ?: (float) $produto['preco_custo'];
                $valor = $valor ?: (float) $produto['preco_venda'];
                Database::connection()->prepare('UPDATE produtos SET estoque = estoque - :qtd, updated_at = :updated_at WHERE id = :id')
                    ->execute(['qtd' => $quantidade, 'updated_at' => now(), 'id' => $produtoId]);
            }

            $subtotal = max(0, ($quantidade * $valor) - $desconto);
            $stmt = Database::connection()->prepare(
                'INSERT INTO os_itens (os_id, tipo, produto_id, descricao, quantidade, custo_unitario, valor_unitario, desconto, subtotal, created_at)
                 VALUES (:os_id, :tipo, :produto_id, :descricao, :quantidade, :custo_unitario, :valor_unitario, :desconto, :subtotal, :created_at)'
            );
            $stmt->execute([
                'os_id' => $id,
                'tipo' => $tipo,
                'produto_id' => $produtoId,
                'descricao' => $descricao,
                'quantidade' => $quantidade,
                'custo_unitario' => $custo,
                'valor_unitario' => $valor,
                'desconto' => $desconto,
                'subtotal' => $subtotal,
                'created_at' => now(),
            ]);

            $this->recalculateTotals($id);
            $this->logChange($id, $usuarioId, 'Item adicionado', null, $descricao . ' - ' . number_format($subtotal, 2, '.', ''));
            return $this->items($id)[0];
        });
    }

    public function payments(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pagamentos WHERE os_id = :id ORDER BY id DESC');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function addPayment(int $id, int $usuarioId, array $data): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pagamentos (os_id, valor, forma_pagamento, observacao, data_pagamento)
             VALUES (:os_id, :valor, :forma_pagamento, :observacao, :data_pagamento)'
        );
        $stmt->execute([
            'os_id' => $id,
            'valor' => $data['valor'],
            'forma_pagamento' => $data['forma_pagamento'],
            'observacao' => $data['observacao'] ?? null,
            'data_pagamento' => now(),
        ]);
        $this->logChange($id, $usuarioId, 'Pagamento registrado', null, $data['forma_pagamento'] . ' - ' . $data['valor']);
        return $this->payments($id)[0];
    }

    public function financialSummary(int $id): array
    {
        $items = $this->items($id);
        $payments = $this->payments($id);
        $total = array_reduce($items, fn ($sum, $item) => $sum + (float) $item['subtotal'], 0.0);
        $paid = array_reduce($payments, fn ($sum, $payment) => $sum + (float) $payment['valor'], 0.0);
        $cost = array_reduce($items, fn ($sum, $item) => $sum + ((float) $item['custo_unitario'] * (float) $item['quantidade']), 0.0);

        return [
            'total' => round($total, 2),
            'pago' => round($paid, 2),
            'saldo' => round($total - $paid, 2),
            'custo' => round($cost, 2),
            'lucro_estimado' => round($total - $cost, 2),
            'margem_percentual' => $total > 0 ? round((($total - $cost) / $total) * 100, 2) : 0,
        ];
    }

    private function recalculateTotals(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE ordens_servico
             SET valor_inicial = COALESCE((SELECT SUM(subtotal) FROM os_itens WHERE os_id = :items_id_1), 0),
                 valor_final = COALESCE((SELECT SUM(subtotal) FROM os_itens WHERE os_id = :items_id_2), 0),
                 updated_at = :updated_at
             WHERE id = :order_id'
        );
        $stmt->execute([
            'items_id_1' => $id,
            'items_id_2' => $id,
            'order_id' => $id,
            'updated_at' => now(),
        ]);
    }

    public function updateSchedule(int $id, string $scheduledAt, int $usuarioId): ?OrdemServico
    {
        $before = $this->find($id);
        $stmt = Database::connection()->prepare(
            'UPDATE ordens_servico SET agendado_para = :agendado_para, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'agendado_para' => $scheduledAt, 'updated_at' => now()]);

        if ($before) {
            $this->logChange($id, $usuarioId, 'Agendamento alterado', $before->agendado_para, $scheduledAt);
        }

        return $this->find($id);
    }

    public function history(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.*, u.nome AS usuario_nome
             FROM logs_alteracoes l
             JOIN usuarios u ON u.id = l.usuario_id
             WHERE l.os_id = :id
             ORDER BY l.id DESC'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function attachments(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, os_id, usuario_id, nome_original, mime, tamanho, created_at
             FROM os_anexos
             WHERE os_id = :id
             ORDER BY id DESC'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function addAttachment(int $id, int $usuarioId, array $file, string $path): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO os_anexos (os_id, usuario_id, nome_original, caminho, mime, tamanho, created_at)
             VALUES (:os_id, :usuario_id, :nome_original, :caminho, :mime, :tamanho, :created_at)'
        );
        $stmt->execute([
            'os_id' => $id,
            'usuario_id' => $usuarioId,
            'nome_original' => $file['name'],
            'caminho' => $path,
            'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($path),
            'tamanho' => filesize($path),
            'created_at' => now(),
        ]);

        $this->logChange($id, $usuarioId, 'Imagem/anexo adicionado', null, $file['name']);
        return $this->attachments($id)[0];
    }

    public function logChange(int $id, int $usuarioId, string $motivo, ?string $old, ?string $new): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO logs_alteracoes (os_id, usuario_id, motivo, valor_antigo, valor_novo, created_at)
             VALUES (:os_id, :usuario_id, :motivo, :valor_antigo, :valor_novo, :created_at)'
        );
        $stmt->execute([
            'os_id' => $id,
            'usuario_id' => $usuarioId,
            'motivo' => $motivo,
            'valor_antigo' => $old,
            'valor_novo' => $new,
            'created_at' => now(),
        ]);
    }

    private function map(array $row): OrdemServico
    {
        return new OrdemServico(
            (int) $row['id'],
            (int) $row['cliente_id'],
            (int) $row['veiculo_id'],
            $row['status'],
            $row['orcamento_status'] ?? 'rascunho',
            (float) $row['valor_inicial'],
            $row['valor_final'] !== null ? (float) $row['valor_final'] : null,
            $row['descricao_problema'],
            $row['diagnostico'] ?? null,
            $row['agendado_para'] ?? null,
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null,
            $row['finalizado_em'] ?? null
        );
    }
}
