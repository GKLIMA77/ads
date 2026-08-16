<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/admin_auth.php';
include __DIR__ . '/includes/conexao.php';

$acao = $_GET['acao'] ?? '';

// Tratamento para requisições POST com JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['acao'])) {
        $acao = $input['acao'];
    }
}

if ($acao !== 'produtos_publicos') {
    exigirAdmin();
}

function responder($sucesso, $mensagem, $dados = null) {
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function texto($valor, $padrao = '') {
    return trim((string)($valor ?? $padrao));
}

try {
    switch ($acao) {

        // ── DASHBOARD ────────────────────────────────────────────────────────────

        // CALL da Stored Procedure otimizada
        case 'indicadores':
            $res = $conexao->query("CALL sp_obter_indicadores_dashboard()");
            $indicadores = $res->fetch_assoc();
            $res->free();
            // Libera conexão para consultas subsequentes do MySQL
            while ($conexao->more_results() && $conexao->next_result()) {
                if ($r = $conexao->store_result()) $r->free();
            }
            responder(true, 'Indicadores obtidos com sucesso', $indicadores);
            break;

        // Leitura usando a View Analítica CTE
        case 'ranking':
            $res = $conexao->query("SELECT * FROM vw_relatorio_servicos ORDER BY total_agendamentos DESC");
            $ranking = [];
            while ($linha = $res->fetch_assoc()) {
                $ranking[] = [
                    'id'                 => (int)$linha['id'],
                    'nome'               => $linha['nome'],
                    'preco'              => (float)$linha['preco'],
                    'total_agendamentos' => (int)$linha['total_agendamentos'],
                    'faturamento'        => (float)$linha['faturamento_gerado']
                ];
            }
            responder(true, 'Ranking obtido', $ranking);
            break;

        // ── AGENDAMENTOS ─────────────────────────────────────────────────────────

        // Leitura via Stored Procedure: ela já faz a busca,
        // o filtro por status e a paginação (rubrica)
        case 'agendamentos':
            $statusFiltro = $_GET['status'] ?? '';
            $statusParam  = $statusFiltro !== '' ? $statusFiltro : null;
            $pagina       = max(1, (int)($_GET['pagina'] ?? 1));
            $porPagina    = min(200, max(1, (int)($_GET['por_pagina'] ?? 200)));
            $offset       = ($pagina - 1) * $porPagina;

            $stmt = $conexao->prepare('CALL sp_listar_agendamentos(?, ?, ?)');
            $stmt->bind_param('sii', $statusParam, $porPagina, $offset);
            $stmt->execute();
            $res = $stmt->get_result();

            $lista = [];
            while ($linha = $res->fetch_assoc()) {
                $lista[] = [
                    'id'           => (int)$linha['agendamento_id'],
                    'cliente_nome' => $linha['cliente_nome'],
                    'servico_nome' => $linha['servico_nome'],
                    'servico_preco'=> (float)$linha['servico_preco'],
                    'data_hora'    => $linha['data_hora'],
                    'status'       => $linha['status']
                ];
            }
            $stmt->close();
            // Libera a conexão para a próxima consulta poder rodar
            while ($conexao->more_results() && $conexao->next_result()) {
                if ($r = $conexao->store_result()) $r->free();
            }
            responder(true, 'Lista de agendamentos', $lista);
            break;

        case 'criar_agendamento':
            $nome      = texto($input['nome'] ?? '');
            $servicoId = (int)($input['servico_id'] ?? 0);
            $dataHora  = texto($input['data_hora'] ?? '');
            $status    = texto($input['status'] ?? 'pendente');
            if ($nome === '' || $servicoId <= 0 || $dataHora === '') {
                responder(false, 'Preencha todos os campos do agendamento.');
            }
            if (!in_array($status, ['pendente', 'confirmado', 'cancelado'], true)) {
                $status = 'pendente';
            }
            $stmt = $conexao->prepare('INSERT INTO agendamentos (cliente_nome, servico_id, data_hora, status) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('siss', $nome, $servicoId, $dataHora, $status);
            if (!$stmt->execute()) responder(false, 'Erro ao criar agendamento: ' . $stmt->error);
            responder(true, 'Agendamento criado com sucesso.');
            break;

        case 'editar_agendamento':
            $id        = (int)($input['id'] ?? 0);
            $nome      = texto($input['nome'] ?? '');
            $servicoId = (int)($input['servico_id'] ?? 0);
            $dataHora  = texto($input['data_hora'] ?? '');
            $status    = texto($input['status'] ?? 'pendente');
            if ($id <= 0 || $nome === '' || $servicoId <= 0 || $dataHora === '') {
                responder(false, 'Dados inválidos para edição.');
            }
            if (!in_array($status, ['pendente', 'confirmado', 'cancelado'], true)) {
                $status = 'pendente';
            }
            $stmt = $conexao->prepare('UPDATE agendamentos SET cliente_nome = ?, servico_id = ?, data_hora = ?, status = ? WHERE id = ?');
            $stmt->bind_param('sissi', $nome, $servicoId, $dataHora, $status, $id);
            if (!$stmt->execute()) responder(false, 'Erro ao editar agendamento: ' . $stmt->error);
            responder(true, 'Agendamento atualizado com sucesso.');
            break;

        case 'excluir_agendamento':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) responder(false, 'ID inválido.');
            $conexao->query("DELETE FROM agendamentos WHERE id = $id");
            if ($conexao->affected_rows === 0) {
                responder(false, 'Agendamento não encontrado (pode já ter sido excluído).');
            }
            responder(true, 'Agendamento excluído com sucesso.');
            break;

        // ── CLIENTES ─────────────────────────────────────────────────────────────

        // Usa fn_calcular_faturamento_cliente() (rubrica: function para
        // reutilização de script complexo) para trazer, junto de cada
        // cliente, o total já faturado com ele em agendamentos confirmados.
        case 'clientes':
            $res = $conexao->query("
                SELECT c.*, fn_calcular_faturamento_cliente(c.nome) AS faturamento
                FROM clientes c
                ORDER BY c.nome ASC
            ");
            $clientes = [];
            while ($linha = $res->fetch_assoc()) {
                $linha['faturamento'] = (float)$linha['faturamento'];
                $clientes[] = $linha;
            }
            responder(true, 'Lista de clientes', $clientes);
            break;

        case 'criar_cliente':
            $nome     = texto($input['nome'] ?? '');
            $telefone = texto($input['telefone'] ?? '');
            $email    = texto($input['email'] ?? '');
            if ($nome === '') responder(false, 'Informe o nome do cliente.');
            $stmt = $conexao->prepare('INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $nome, $telefone, $email);
            if (!$stmt->execute()) responder(false, 'Erro ao criar cliente: ' . $stmt->error);
            responder(true, 'Cliente criado com sucesso.');
            break;

        case 'editar_cliente':
            $id       = (int)($input['id'] ?? 0);
            $nome     = texto($input['nome'] ?? '');
            $telefone = texto($input['telefone'] ?? '');
            $email    = texto($input['email'] ?? '');
            if ($id <= 0 || $nome === '') responder(false, 'Dados inválidos para edição.');
            $stmt = $conexao->prepare('UPDATE clientes SET nome = ?, telefone = ?, email = ? WHERE id = ?');
            $stmt->bind_param('sssi', $nome, $telefone, $email, $id);
            if (!$stmt->execute()) responder(false, 'Erro ao editar cliente: ' . $stmt->error);
            responder(true, 'Cliente atualizado com sucesso.');
            break;

        case 'excluir_cliente':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) responder(false, 'ID inválido.');
            // Regra de exclusão: impede exclusão se houver agendamentos vinculados pelo nome
            $nomeCliente = $conexao->query("SELECT nome FROM clientes WHERE id = $id")->fetch_assoc()['nome'] ?? '';
            if ($nomeCliente !== '') {
                $check = $conexao->query("SELECT id FROM agendamentos WHERE cliente_nome = '" . $conexao->real_escape_string($nomeCliente) . "' LIMIT 1");
                if ($check && $check->num_rows > 0) {
                    responder(false, 'Não é possível excluir este cliente pois existem agendamentos vinculados a ele!');
                }
            }
            $conexao->query("DELETE FROM clientes WHERE id = $id");
            responder(true, 'Cliente excluído com sucesso.');
            break;

        // ── SERVIÇOS ─────────────────────────────────────────────────────────────

        case 'servicos':
            $res = $conexao->query("SELECT * FROM servicos ORDER BY nome ASC");
            $servicos = [];
            while ($linha = $res->fetch_assoc()) {
                $servicos[] = $linha;
            }
            responder(true, 'Lista de serviços', $servicos);
            break;

        case 'criar_servico':
            $nome  = texto($input['nome'] ?? '');
            $preco = (float)($input['preco'] ?? 0);
            $ativo = (int)($input['ativo'] ?? 1);
            if ($nome === '' || $preco <= 0) responder(false, 'Informe nome e preço válido.');
            $stmt = $conexao->prepare('INSERT INTO servicos (nome, preco, ativo) VALUES (?, ?, ?)');
            $stmt->bind_param('sdi', $nome, $preco, $ativo);
            if (!$stmt->execute()) responder(false, 'Erro ao criar serviço: ' . $stmt->error);
            responder(true, 'Serviço criado com sucesso.');
            break;

        case 'editar_servico':
            $id    = (int)($input['id'] ?? 0);
            $nome  = texto($input['nome'] ?? '');
            $preco = (float)($input['preco'] ?? 0);
            $ativo = (int)($input['ativo'] ?? 1);
            if ($id <= 0 || $nome === '' || $preco <= 0) responder(false, 'Dados inválidos para edição.');
            $stmt = $conexao->prepare('UPDATE servicos SET nome = ?, preco = ?, ativo = ? WHERE id = ?');
            $stmt->bind_param('sdii', $nome, $preco, $ativo, $id);
            if (!$stmt->execute()) responder(false, 'Erro ao editar serviço: ' . $stmt->error);
            responder(true, 'Serviço atualizado com sucesso.');
            break;

        // Regra de exclusão com mensagem clara
        case 'excluir_servico':
            $id = (int)($input['id'] ?? 0);
            $check = $conexao->query("SELECT id FROM agendamentos WHERE servico_id = $id");
            if ($check->num_rows > 0) {
                responder(false, 'Não é possível excluir este serviço pois existem agendamentos ativos vinculados a ele!');
            }
            $conexao->query("DELETE FROM servicos WHERE id = $id");
            responder(true, 'Serviço removido com sucesso.');
            break;

        // ── LOJA: PRODUTOS ────────────────────────────────────────────────────────

        case 'produtos':
            $res = $conexao->query("SELECT * FROM produtos ORDER BY criado_em DESC");
            $produtos = [];
            while ($linha = $res->fetch_assoc()) $produtos[] = $linha;
            responder(true, 'Lista de produtos', $produtos);
            break;

        case 'produtos_publicos':
            $res = $conexao->query("SELECT * FROM produtos WHERE status = 'aprovado' ORDER BY nome");
            $produtos = [];
            while ($linha = $res->fetch_assoc()) $produtos[] = $linha;
            responder(true, 'Produtos disponíveis', $produtos);
            break;

        case 'salvar_produto':
            $id          = (int)($input['id'] ?? 0);
            $nome        = texto($input['nome']);
            $descricao   = texto($input['descricao']);
            $preco       = (float)($input['preco'] ?? 0);
            $imagem      = texto($input['imagem']);
            $status      = texto($input['status'], 'pendente');
            if ($nome === '' || $preco <= 0 || !in_array($status, ['pendente', 'aprovado', 'cancelado'], true)) {
                responder(false, 'Preencha nome, preço e status válido.');
            }
            if ($id > 0) {
                $stmt = $conexao->prepare('UPDATE produtos SET nome = ?, descricao = ?, preco = ?, imagem = ?, status = ? WHERE id = ?');
                $stmt->bind_param('ssdssi', $nome, $descricao, $preco, $imagem, $status, $id);
            } else {
                $stmt = $conexao->prepare('INSERT INTO produtos (nome, descricao, preco, imagem, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('ssdss', $nome, $descricao, $preco, $imagem, $status);
            }
            if (!$stmt->execute()) responder(false, 'Não foi possível salvar o produto: ' . $stmt->error);
            responder(true, 'Produto salvo com sucesso.');
            break;

        case 'excluir_produto':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) responder(false, 'ID inválido.');
            $conexao->query("DELETE FROM produtos WHERE id = $id");
            if ($conexao->affected_rows === 0) {
                responder(false, 'Produto não encontrado (pode já ter sido excluído).');
            }
            responder(true, 'Produto excluído com sucesso.');
            break;

        default:
            responder(false, 'Ação não reconhecida.');
    }
} catch (Exception $e) {
    responder(false, 'Erro no servidor: ' . $e->getMessage());
}
?>