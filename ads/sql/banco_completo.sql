-- ============================================================
-- banco_completo.sql — Barbearia Adrian Souza
-- Itens da rubrica cobertos:
--   ✅ Trigger (BEFORE UPDATE)
--   ✅ Function (fn_calcular_faturamento_cliente)
--   ✅ Stored Procedure (sp_obter_indicadores_dashboard)
--   ✅ Stored Procedure com filtro e paginação (sp_listar_agendamentos)
--   ✅ View CTE analítica (vw_relatorio_servicos)
--   ✅ View de centralização (vw_central_agendamentos)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS barbearia_adrian_souza
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE barbearia_adrian_souza;

-- ── DROP (ordem correta por dependência) ─────────────────────
DROP TABLE IF EXISTS cliente_servico;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS horarios_bloqueados;
DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS servicos;
DROP VIEW  IF EXISTS vw_central_agendamentos;
DROP VIEW  IF EXISTS vw_relatorio_servicos;
DROP PROCEDURE IF EXISTS sp_obter_indicadores_dashboard;
DROP PROCEDURE IF EXISTS sp_listar_agendamentos;
DROP FUNCTION  IF EXISTS fn_calcular_faturamento_cliente;
DROP TRIGGER   IF EXISTS trg_verificar_preco_positivo;

SET FOREIGN_KEY_CHECKS = 1;

-- ══════════════════════════════════════════════════════════════
-- TABELAS
-- ══════════════════════════════════════════════════════════════

CREATE TABLE servicos (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome  VARCHAR(100) NOT NULL,
  preco DECIMAL(8,2) NOT NULL,
  ativo TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produtos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome         VARCHAR(120) NOT NULL,
  descricao    VARCHAR(255) NULL,
  preco        DECIMAL(8,2) NOT NULL,
  imagem       VARCHAR(500) NULL,
  status       ENUM('pendente','aprovado','cancelado') NOT NULL DEFAULT 'pendente',
  criado_em    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome     VARCHAR(100) NOT NULL,
  telefone VARCHAR(20)  NULL,
  email    VARCHAR(150) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE agendamentos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_nome VARCHAR(100) NOT NULL,
  servico_id   INT UNSIGNED NOT NULL,
  data_hora    DATETIME     NOT NULL,
  status       ENUM('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  observacao   VARCHAR(255) NULL,
  criado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_agendamentos_status (status),
  FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horarios_bloqueados (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data   DATE         NOT NULL,
  motivo VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_bloqueada (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_servico (
  cliente_id INT UNSIGNED NOT NULL,
  servico_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (cliente_id, servico_id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════
-- TRIGGER — BEFORE UPDATE (rubrica: padronizar valores positivos)
-- Teste: UPDATE servicos SET preco = -1 WHERE id = 1;
-- ══════════════════════════════════════════════════════════════

DELIMITER //
CREATE TRIGGER trg_verificar_preco_positivo
BEFORE UPDATE ON servicos
FOR EACH ROW
BEGIN
  IF NEW.preco <= 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'O preço do serviço deve ser um valor positivo!';
  END IF;
END //
DELIMITER ;

-- ══════════════════════════════════════════════════════════════
-- FUNCTION — reutilização de script complexo (rubrica)
-- Teste: SELECT fn_calcular_faturamento_cliente('Felipe Martins');
-- ══════════════════════════════════════════════════════════════

DELIMITER //
CREATE FUNCTION fn_calcular_faturamento_cliente(p_nome_cliente VARCHAR(100))
RETURNS DECIMAL(10,2)
DETERMINISTIC
READS SQL DATA
BEGIN
  DECLARE v_total DECIMAL(10,2);
  SELECT IFNULL(SUM(s.preco), 0.00) INTO v_total
  FROM agendamentos a
  JOIN servicos s ON s.id = a.servico_id
  WHERE a.cliente_nome = p_nome_cliente
    AND a.status = 'confirmado';
  RETURN v_total;
END //
DELIMITER ;

-- ══════════════════════════════════════════════════════════════
-- VIEW DE CENTRALIZAÇÃO — une tabelas distintas (rubrica)
-- Teste: SELECT * FROM vw_central_agendamentos;
-- ══════════════════════════════════════════════════════════════

CREATE VIEW vw_central_agendamentos AS
SELECT
  a.id           AS agendamento_id,
  a.cliente_nome,
  s.id           AS servico_id,
  s.nome         AS servico_nome,
  s.preco        AS servico_preco,
  a.data_hora,
  a.status,
  a.observacao,
  a.criado_em
FROM agendamentos a
JOIN servicos s ON s.id = a.servico_id;

-- ══════════════════════════════════════════════════════════════
-- VIEW CTE ANALÍTICA — limpeza e consolidação de dados (rubrica)
-- Teste: SELECT * FROM vw_relatorio_servicos ORDER BY total_agendamentos DESC;
-- ══════════════════════════════════════════════════════════════

CREATE VIEW vw_relatorio_servicos AS
WITH CTE_ServicoEstatistica AS (
  SELECT
    s.id,
    s.nome,
    s.preco,
    COUNT(a.id) AS total_agendamentos,
    SUM(CASE WHEN a.status = 'confirmado' THEN s.preco ELSE 0 END) AS faturamento_gerado
  FROM servicos s
  LEFT JOIN agendamentos a ON a.servico_id = s.id
  GROUP BY s.id, s.nome, s.preco
)
SELECT id, nome, preco, total_agendamentos, faturamento_gerado
FROM CTE_ServicoEstatistica;

-- ══════════════════════════════════════════════════════════════
-- STORED PROCEDURE — centraliza indicadores da dashboard (rubrica)
-- Teste: CALL sp_obter_indicadores_dashboard();
-- ══════════════════════════════════════════════════════════════

DELIMITER //
CREATE PROCEDURE sp_obter_indicadores_dashboard()
BEGIN
  SELECT
    (SELECT COUNT(*) FROM agendamentos)                                                                          AS total_agendamentos,
    (SELECT IFNULL(SUM(s.preco), 0) FROM agendamentos a JOIN servicos s ON s.id = a.servico_id WHERE a.status = 'confirmado') AS faturamento_total,
    (SELECT COUNT(*) FROM agendamentos WHERE DATE(data_hora) = CURDATE())                                        AS agendamentos_hoje,
    (SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente')                                                AS pendentes;
END //
DELIMITER ;

-- ══════════════════════════════════════════════════════════════
-- STORED PROCEDURE — busca + filtro (status) + paginação (rubrica)
-- Ela substitui o SELECT que antes ficava solto dentro do PHP.
-- Se p_status vier vazio, traz todos os status.
-- Teste: CALL sp_listar_agendamentos(NULL, 10, 0);       -- página 1, 10 por página, todos
--        CALL sp_listar_agendamentos('pendente', 5, 0);  -- só pendentes
-- ══════════════════════════════════════════════════════════════

DELIMITER //
CREATE PROCEDURE sp_listar_agendamentos(
  IN p_status  VARCHAR(20),   -- filtro: 'pendente' / 'confirmado' / 'cancelado' / NULL (todos)
  IN p_limite  INT,           -- paginação: quantos registros trazer
  IN p_offset  INT            -- paginação: a partir de qual posição
)
BEGIN
  IF p_limite IS NULL OR p_limite <= 0 THEN
    SET p_limite = 200; -- valor padrão caso a API não informe
  END IF;
  IF p_offset IS NULL OR p_offset < 0 THEN
    SET p_offset = 0;
  END IF;

  SELECT *
  FROM vw_central_agendamentos
  WHERE p_status IS NULL OR p_status = '' OR status = p_status
  ORDER BY data_hora DESC
  LIMIT p_limite OFFSET p_offset;
END //
DELIMITER ;

-- ══════════════════════════════════════════════════════════════
-- DADOS INICIAIS
-- ══════════════════════════════════════════════════════════════

INSERT INTO servicos (nome, preco) VALUES
  ('Corte Premium',      45.00),
  ('Barba Premium',      35.00),
  ('Combo Completo',     70.00),
  ('Plano Profissional', 120.00);

INSERT INTO produtos (nome, descricao, preco, imagem, status) VALUES
  ('Pomada Modeladora Matte',      'Fixação forte com efeito seco e acabamento natural.',          39.90, 'assets/img/Pomada.jpg',             'aprovado'),
  ('Shampoo Masculino 3 em 1',     'Limpa cabelo, barba e corpo com praticidade.',                 32.90, 'assets/img/shampo3.jpg',            'aprovado'),
  ('Cera de Cabelo Extra Forte',   'Controle máximo com brilho intenso.',                          35.90, 'assets/img/ceraCabelo.jpg',         'aprovado'),
  ('Óleo para Barba Premium',      'Blend de óleos naturais que hidrata e suaviza a barba.',       29.90, 'assets/img/oleoBarba.jpg',          'aprovado'),
  ('Balm Hidratante para Barba',   'Amaciante e hidratante com aroma suave.',                      27.90, 'assets/img/hidratante.jpg',         'aprovado'),
  ('Shampoo Específico para Barba','Limpa sem ressecar. Mantém a barba saudável.',                 24.90, 'assets/img/shampoBarba.jpg',        'aprovado'),
  ('Pente de Madeira Artesanal',   'Não gera estática e respeita os fios.',                        18.90, 'assets/img/PenteMadeira.jpg',       'aprovado'),
  ('Escova de Barba com Cabo',     'Cerdas naturais firmes para distribuir o óleo.',               24.90, 'assets/img/escovabarba.jpg',        'aprovado'),
  ('Tesoura Profissional',         'Aço inox alemão. Precisão para aparo de pontas.',              44.90, 'assets/img/TesouraAcabamento.jpg',  'aprovado');

INSERT INTO clientes (nome, telefone, email) VALUES
  ('Felipe Martins',   '44999990001', 'felipe@email.com'),
  ('Gustavo Henrique', '44999990002', 'gustavo@email.com'),
  ('Lucas Ferreira',   '44999990003', 'lucas@email.com');

INSERT INTO cliente_servico (cliente_id, servico_id) VALUES
  (1, 1), (1, 2), (2, 3), (3, 1), (3, 4);

INSERT INTO agendamentos (cliente_nome, servico_id, data_hora, status, observacao) VALUES
  ('Felipe Martins',   1, NOW(),                 'confirmado', 'Corte disfarçado na tesoura'),
  ('Gustavo Henrique', 3, NOW(),                 'pendente',   'Apenas alinhar a barba'),
  ('Lucas Ferreira',   4, '2025-07-11 14:00:00', 'confirmado', 'Sem navalha');

INSERT INTO horarios_bloqueados (data, motivo) VALUES
  ('2025-12-25', 'Feriado de Natal'),
  ('2026-01-01', 'Ano Novo');

-- ══════════════════════════════════════════════════════════════
-- COMANDOS DE TESTE — rode estes para demonstrar na apresentação
-- ══════════════════════════════════════════════════════════════

-- Procedure:
-- CALL sp_obter_indicadores_dashboard();

-- Procedure com filtro e paginação:
-- CALL sp_listar_agendamentos(NULL, 10, 0);
-- CALL sp_listar_agendamentos('pendente', 5, 0);

-- Function:
-- SELECT fn_calcular_faturamento_cliente('Felipe Martins');

-- View de centralização:
-- SELECT * FROM vw_central_agendamentos;

-- View CTE analítica:
-- SELECT * FROM vw_relatorio_servicos ORDER BY total_agendamentos DESC;

-- Trigger (vai retornar erro proposital — isso é correto!):
-- UPDATE servicos SET preco = -1 WHERE id = 1;