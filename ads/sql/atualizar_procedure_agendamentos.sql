-- ============================================================
-- atualizar_procedure_agendamentos.sql
-- Rode isso SÓ se você já tem o banco criado e só precisa
-- adicionar a stored procedure nova, sem apagar os dados.
--
-- Importe pela aba "Importar" do phpMyAdmin (escolher o arquivo),
-- e não colando o texto na aba "SQL" — senão o DELIMITER não
-- é processado direito e a procedure não é criada.
-- ============================================================

USE barbearia_adrian_souza;

DROP PROCEDURE IF EXISTS sp_listar_agendamentos;

DELIMITER //
CREATE PROCEDURE sp_listar_agendamentos(
  IN p_status  VARCHAR(20),
  IN p_limite  INT,
  IN p_offset  INT
)
BEGIN
  IF p_limite IS NULL OR p_limite <= 0 THEN
    SET p_limite = 200;
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

-- Teste:
-- CALL sp_listar_agendamentos(NULL, 10, 0);
-- CALL sp_listar_agendamentos('pendente', 5, 0);
