INSERT INTO usuarios (nome, email, senha, tipo, ativo, created_at, updated_at)
VALUES (
    'Administrador',
    'admin@garage.local',
    '$2y$12$uDXpQ6uc42QNOugRUnhKie6ckNMzpOw2VaeQc8VpL.yS23MNcGG2K',
    'admin',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE email = email;

INSERT INTO clientes (nome, cpf_cnpj, telefone, email, endereco, created_at, updated_at)
VALUES ('Cliente Exemplo', '00000000000', '(11) 99999-9999', 'cliente@example.com', 'Rua Exemplo, 100', NOW(), NOW())
ON DUPLICATE KEY UPDATE cpf_cnpj = cpf_cnpj;

INSERT INTO clientes (nome, cpf_cnpj, telefone, email, endereco, created_at, updated_at)
VALUES
    ('Marina Costa', '12312312399', '(11) 98888-1111', 'marina@example.com', 'Av. Central, 450', NOW(), NOW()),
    ('Rafael Lima', '98798798711', '(21) 97777-2222', 'rafael@example.com', 'Rua das Oficinas, 88', NOW(), NOW()),
    ('Auto Peças Horizonte', '11222333000144', '(31) 3333-4444', 'compras@horizonte.local', 'Rodovia BR 10, km 20', NOW(), NOW())
ON DUPLICATE KEY UPDATE cpf_cnpj = cpf_cnpj;

INSERT INTO usuarios (nome, email, senha, tipo, cliente_id, ativo, created_at, updated_at)
SELECT
    'Cliente Exemplo',
    'cliente@garage.local',
    '$2y$12$iWpEebhYMeeNXOqYpEdXV.7huH0cTFIa1dWFta9ZijOfgOP8rXk/e',
    'cliente',
    id,
    1,
    NOW(),
    NOW()
FROM clientes
WHERE cpf_cnpj = '00000000000'
ON DUPLICATE KEY UPDATE usuarios.id = usuarios.id;

INSERT INTO veiculos (cliente_id, placa, modelo, ano, cor, km, created_at, updated_at)
SELECT id, 'ABC1D23', 'Gol 1.0', 2020, 'Prata', 45000, NOW(), NOW()
FROM clientes
WHERE cpf_cnpj = '00000000000'
ON DUPLICATE KEY UPDATE placa = placa;

INSERT INTO veiculos (cliente_id, placa, modelo, ano, cor, km, created_at, updated_at)
SELECT id, 'MRN2A10', 'Honda Fit EX', 2019, 'Azul', 62000, NOW(), NOW()
FROM clientes WHERE cpf_cnpj = '12312312399'
ON DUPLICATE KEY UPDATE placa = placa;

INSERT INTO veiculos (cliente_id, placa, modelo, ano, cor, km, created_at, updated_at)
SELECT id, 'RFL8B44', 'Toyota Corolla XEI', 2021, 'Branco', 38000, NOW(), NOW()
FROM clientes WHERE cpf_cnpj = '98798798711'
ON DUPLICATE KEY UPDATE placa = placa;

INSERT INTO veiculos (cliente_id, placa, modelo, ano, cor, km, created_at, updated_at)
SELECT id, 'HOR4D20', 'Fiat Fiorino', 2018, 'Vermelho', 118000, NOW(), NOW()
FROM clientes WHERE cpf_cnpj = '11222333000144'
ON DUPLICATE KEY UPDATE placa = placa;

INSERT INTO produtos (nome, codigo, preco_custo, preco_venda, estoque, created_at, updated_at)
VALUES ('Óleo 5W30', 'OLEO-5W30', 25.00, 45.00, 10, NOW(), NOW())
ON DUPLICATE KEY UPDATE codigo = codigo;

INSERT INTO produtos (nome, codigo, preco_custo, preco_venda, estoque, created_at, updated_at)
VALUES
    ('Filtro de óleo', 'FILTRO-OLEO', 14.00, 32.00, 4, NOW(), NOW()),
    ('Pastilha de freio dianteira', 'PAST-FREIO-D', 85.00, 180.00, 2, NOW(), NOW()),
    ('Vela de ignição', 'VELA-IGN', 18.00, 42.00, 16, NOW(), NOW()),
    ('Aditivo radiador', 'ADITIVO-RAD', 22.00, 49.00, 3, NOW(), NOW()),
    ('Correia dentada', 'CORREIA-DENT', 70.00, 155.00, 5, NOW(), NOW())
ON DUPLICATE KEY UPDATE codigo = codigo;

INSERT INTO ordens_servico
    (cliente_id, veiculo_id, status, valor_inicial, valor_final, descricao_problema, diagnostico, agendado_para, created_at, updated_at, finalizado_em)
SELECT c.id, v.id, 'em_andamento', 120.00, NULL, 'Revisão preventiva', 'Em análise', NOW(), NOW(), NOW(), NULL
FROM clientes c
JOIN veiculos v ON v.cliente_id = c.id
WHERE c.cpf_cnpj = '00000000000' AND v.placa = 'ABC1D23'
  AND NOT EXISTS (
      SELECT 1 FROM ordens_servico os
      WHERE os.cliente_id = c.id AND os.veiculo_id = v.id AND os.descricao_problema = 'Revisão preventiva'
  );

INSERT INTO ordens_servico
    (cliente_id, veiculo_id, status, valor_inicial, valor_final, descricao_problema, diagnostico, agendado_para, created_at, updated_at, finalizado_em)
SELECT c.id, v.id, 'aberta', 380.00, NULL, 'Troca de pastilhas e ruído ao frear', 'Aguardando inspeção visual', DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), NOW(), NULL
FROM clientes c JOIN veiculos v ON v.cliente_id = c.id
WHERE c.cpf_cnpj = '12312312399' AND v.placa = 'MRN2A10'
AND NOT EXISTS (SELECT 1 FROM ordens_servico os WHERE os.veiculo_id = v.id AND os.descricao_problema = 'Troca de pastilhas e ruído ao frear');

INSERT INTO ordens_servico
    (cliente_id, veiculo_id, status, valor_inicial, valor_final, descricao_problema, diagnostico, agendado_para, created_at, updated_at, finalizado_em)
SELECT c.id, v.id, 'aguardando_pecas', 920.00, NULL, 'Correia dentada e revisão de 40 mil km', 'Correia com desgaste; aguardando peça', DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), NOW(), NULL
FROM clientes c JOIN veiculos v ON v.cliente_id = c.id
WHERE c.cpf_cnpj = '98798798711' AND v.placa = 'RFL8B44'
AND NOT EXISTS (SELECT 1 FROM ordens_servico os WHERE os.veiculo_id = v.id AND os.descricao_problema = 'Correia dentada e revisão de 40 mil km');

INSERT INTO ordens_servico
    (cliente_id, veiculo_id, status, valor_inicial, valor_final, descricao_problema, diagnostico, agendado_para, created_at, updated_at, finalizado_em)
SELECT c.id, v.id, 'finalizada', 540.00, 610.00, 'Vazamento no radiador', 'Mangueira substituída e sistema pressurizado', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), NOW(), NOW()
FROM clientes c JOIN veiculos v ON v.cliente_id = c.id
WHERE c.cpf_cnpj = '11222333000144' AND v.placa = 'HOR4D20'
AND NOT EXISTS (SELECT 1 FROM ordens_servico os WHERE os.veiculo_id = v.id AND os.descricao_problema = 'Vazamento no radiador');

INSERT INTO logs_alteracoes (os_id, usuario_id, motivo, valor_antigo, valor_novo, created_at)
SELECT os.id, u.id, 'Ordem criada', NULL, os.status, os.created_at
FROM ordens_servico os
JOIN usuarios u ON u.email = 'admin@garage.local'
WHERE NOT EXISTS (
    SELECT 1 FROM logs_alteracoes l WHERE l.os_id = os.id AND l.motivo = 'Ordem criada'
);

INSERT INTO os_itens (os_id, tipo, produto_id, descricao, quantidade, custo_unitario, valor_unitario, desconto, subtotal, created_at)
SELECT os.id, 'servico', NULL, 'Mão de obra diagnóstico e revisão', 1, 0, 120.00, 0, 120.00, NOW()
FROM ordens_servico os
WHERE os.descricao_problema = 'Revisão preventiva'
AND NOT EXISTS (SELECT 1 FROM os_itens i WHERE i.os_id = os.id AND i.descricao = 'Mão de obra diagnóstico e revisão');

INSERT INTO os_itens (os_id, tipo, produto_id, descricao, quantidade, custo_unitario, valor_unitario, desconto, subtotal, created_at)
SELECT os.id, 'peca', p.id, p.nome, 1, p.preco_custo, p.preco_venda, 0, p.preco_venda, NOW()
FROM ordens_servico os
JOIN produtos p ON p.codigo = 'FILTRO-OLEO'
WHERE os.descricao_problema = 'Revisão preventiva'
AND NOT EXISTS (SELECT 1 FROM os_itens i WHERE i.os_id = os.id AND i.produto_id = p.id);

INSERT INTO os_itens (os_id, tipo, produto_id, descricao, quantidade, custo_unitario, valor_unitario, desconto, subtotal, created_at)
SELECT os.id, 'servico', NULL, 'Substituição de pastilhas dianteiras', 1, 0, 160.00, 0, 160.00, NOW()
FROM ordens_servico os
WHERE os.descricao_problema = 'Troca de pastilhas e ruído ao frear'
AND NOT EXISTS (SELECT 1 FROM os_itens i WHERE i.os_id = os.id AND i.descricao = 'Substituição de pastilhas dianteiras');

INSERT INTO os_itens (os_id, tipo, produto_id, descricao, quantidade, custo_unitario, valor_unitario, desconto, subtotal, created_at)
SELECT os.id, 'peca', p.id, p.nome, 1, p.preco_custo, p.preco_venda, 0, p.preco_venda, NOW()
FROM ordens_servico os
JOIN produtos p ON p.codigo = 'PAST-FREIO-D'
WHERE os.descricao_problema = 'Troca de pastilhas e ruído ao frear'
AND NOT EXISTS (SELECT 1 FROM os_itens i WHERE i.os_id = os.id AND i.produto_id = p.id);

UPDATE ordens_servico os
SET valor_inicial = COALESCE((SELECT SUM(i.subtotal) FROM os_itens i WHERE i.os_id = os.id), os.valor_inicial),
    valor_final = COALESCE((SELECT SUM(i.subtotal) FROM os_itens i WHERE i.os_id = os.id), os.valor_final),
    orcamento_status = IF(os.status IN ('finalizada', 'em_andamento', 'aguardando_pecas'), 'aprovado', os.orcamento_status)
WHERE EXISTS (SELECT 1 FROM os_itens i WHERE i.os_id = os.id);

INSERT INTO pagamentos (os_id, valor, forma_pagamento, observacao, data_pagamento)
SELECT os.id, 100.00, 'pix', 'Sinal do orçamento', NOW()
FROM ordens_servico os
WHERE os.descricao_problema = 'Revisão preventiva'
AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.os_id = os.id AND p.observacao = 'Sinal do orçamento');
