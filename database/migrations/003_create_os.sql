CREATE TABLE IF NOT EXISTS ordens_servico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT UNSIGNED NOT NULL,
    veiculo_id BIGINT UNSIGNED NOT NULL,
    status ENUM('aberta', 'em_andamento', 'aguardando_pecas', 'finalizada', 'cancelada') NOT NULL DEFAULT 'aberta',
    valor_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_final DECIMAL(10,2) NULL,
    descricao_problema TEXT NOT NULL,
    diagnostico TEXT NULL,
    agendado_para DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    finalizado_em DATETIME NULL,
    CONSTRAINT fk_os_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_os_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT,
    INDEX idx_os_cliente (cliente_id),
    INDEX idx_os_veiculo (veiculo_id),
    INDEX idx_os_status (status),
    INDEX idx_os_agendado_para (agendado_para)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    os_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(60) NOT NULL,
    data_pagamento DATETIME NOT NULL,
    CONSTRAINT fk_pagamentos_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs_alteracoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    os_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    valor_antigo TEXT NULL,
    valor_novo TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_logs_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
