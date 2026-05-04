ALTER TABLE usuarios
    MODIFY tipo ENUM('admin', 'atendente', 'mecanico', 'cliente') NOT NULL DEFAULT 'atendente';

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS cliente_id BIGINT UNSIGNED NULL AFTER tipo;

CREATE INDEX IF NOT EXISTS idx_usuarios_cliente ON usuarios (cliente_id);

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND CONSTRAINT_NAME = 'fk_usuarios_cliente'
);

SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
