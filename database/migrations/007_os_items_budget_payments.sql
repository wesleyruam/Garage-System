ALTER TABLE ordens_servico
    MODIFY status ENUM('orcamento', 'aprovada', 'aberta', 'em_andamento', 'aguardando_pecas', 'finalizada', 'cancelada') NOT NULL DEFAULT 'orcamento';

ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS orcamento_status ENUM('rascunho', 'enviado', 'aprovado', 'recusado') NOT NULL DEFAULT 'rascunho' AFTER status;

CREATE TABLE IF NOT EXISTS os_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    os_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('peca', 'servico') NOT NULL,
    produto_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 1,
    custo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
    desconto DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_os_itens_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_os_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL,
    INDEX idx_os_itens_os (os_id),
    INDEX idx_os_itens_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS observacao VARCHAR(255) NULL AFTER forma_pagamento;
