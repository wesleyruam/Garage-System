ALTER TABLE ordens_servico
    ADD COLUMN IF NOT EXISTS agendado_para DATETIME NULL AFTER diagnostico;

CREATE INDEX IF NOT EXISTS idx_os_agendado_para ON ordens_servico (agendado_para);

CREATE TABLE IF NOT EXISTS os_anexos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    os_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    mime VARCHAR(120) NOT NULL,
    tamanho BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_os_anexos_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
    CONSTRAINT fk_os_anexos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_os_anexos_os (os_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
