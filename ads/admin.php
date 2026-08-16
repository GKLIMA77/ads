<?php
require_once __DIR__ . '/includes/admin_auth.php';

if (isset($_GET['sair'])) {
    $_SESSION = [];
    session_destroy();
    session_start();
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin.php';
    header('Location: ' . $redirect);
    exit;
}

$erroLogin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entrar'])) {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';
    if (hash_equals(ADMIN_USUARIO, $usuario) && password_verify($senha, ADMIN_SENHA_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_autenticado'] = true;
        $_SESSION['admin_ultimo_acesso'] = time();
        header('Location: admin.php');
        exit;
    }
    $erroLogin = 'Usuário ou senha inválidos.';
}

if (adminAutenticado()) {
    $ultimo = $_SESSION['admin_ultimo_acesso'] ?? 0;
    if (time() - $ultimo > 1800) {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php');
        exit;
    }
    $_SESSION['admin_ultimo_acesso'] = time();
}

if (!adminAutenticado()):
  $tituloPagina = 'Login — Barbearia Adrian Souza';
  include __DIR__ . '/includes/head-admin.php';
?>
<body class="login-page">
  <main class="login-card">
    <div class="login-icon"><i class="fa-solid fa-lock"></i></div>
    <p class="mini-texto">ÁREA RESTRITA</p>
    <h1>Painel da barbearia</h1>
    <p class="login-subtitulo">Entre com seu usuário para gerenciar agendamentos e clientes.</p>
    <?php if ($erroLogin): ?>
      <div class="alert alert-danger py-2"><?php echo htmlspecialchars($erroLogin); ?></div>
    <?php endif; ?>
    <form method="post" class="login-form">
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required>
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="current-password" required>
      <button type="submit" name="entrar" class="btn btn-gold w-100 mt-3">
        <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar no painel
      </button>
    </form>
    <a class="login-voltar" href="index.php"><i class="fa-solid fa-arrow-left me-1"></i>Voltar para o site</a>
  </main>
  <?php $incluirAdminJs = false; include __DIR__ . '/includes/footer-admin.php'; ?>
<?php exit; endif; ?>
<?php
include __DIR__ . '/includes/conexao.php';
$tituloPagina = 'Admin — Barbearia Adrian Souza';
include __DIR__ . '/includes/head-admin.php';
?>
<body>

<div id="loading-overlay"><div class="spinner-border" role="status"></div></div>

<div class="d-flex">
  <!-- SIDEBAR -->
  <div class="sidebar">
    <span class="brand">💈 Admin</span>
    <nav class="nav-side d-flex flex-column">
      <a href="#" class="nav-link active" data-tab="dashboard"><i class="fas fa-chart-bar me-2"></i>Dashboard</a>
      <a href="#" class="nav-link" data-tab="agendamentos"><i class="fas fa-calendar me-2"></i>Agendamentos</a>
      <a href="#" class="nav-link" data-tab="servicos"><i class="fas fa-scissors me-2"></i>Serviços</a>
      <a href="#" class="nav-link" data-tab="loja"><i class="fas fa-store me-2"></i>Loja</a>
      <hr>
      <a href="admin.php?sair=1&redirect=index.php" class="nav-link"><i class="fas fa-home me-2"></i>Ver Site</a>
      <a href="admin.php?sair=1" class="nav-link"><i class="fas fa-right-from-bracket me-2"></i>Sair</a>
    </nav>
  </div>

  <!-- MAIN -->
  <div class="main-content flex-grow-1">

    <!-- DASHBOARD -->
    <div id="tab-dashboard" class="tab-section">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="section-title mb-0">Dashboard</h2>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-sm-3">
          <div class="card-stat">
            <h4 id="stat-total">0</h4>
            <p>Agendamentos no total</p>
          </div>
        </div>
        <div class="col-sm-3">
          <div class="card-stat card-stat-pendente">
            <h4 id="stat-pend">0</h4>
            <p>Pendentes</p>
          </div>
        </div>
        <div class="col-sm-3">
          <div class="card-stat card-stat-confirmado">
            <h4 id="stat-hoje">0</h4>
            <p>Agendamentos hoje</p>
          </div>
        </div>
        <div class="col-sm-3">
          <div class="card-stat">
            <h4 id="stat-fat">R$ 0,00</h4>
            <p>Faturamento confirmado</p>
          </div>
        </div>
      </div>
      <h5 class="text-light mb-3">Ranking de serviços</h5>
      <div id="ranking-container" class="row g-3"></div>
    </div>

    <!-- AGENDAMENTOS -->
    <div id="tab-agendamentos" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="section-title mb-0">Agendamentos</h2>
        <button class="btn btn-gold" onclick="abrirModalNovoAgendamento()"><i class="fas fa-plus me-2"></i>Novo</button>
      </div>
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="tab-btn active" data-filtro="">Todos</button>
        <button class="tab-btn" data-filtro="pendente">Pendentes</button>
        <button class="tab-btn" data-filtro="confirmado">Confirmados</button>
        <button class="tab-btn" data-filtro="cancelado">Cancelados</button>
      </div>
      <div id="ag-faturamento-total" class="mb-3 text-warning fw-bold" style="font-size:15px;"></div>
      <div class="table-responsive">
        <table class="table table-dark table-hover">
          <thead><tr><th>#</th><th>Cliente</th><th>Serviço</th><th>Data/Hora</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody id="tabela-agendamentos"></tbody>
        </table>
        <div id="msg-sem-agendamentos" class="text-secondary text-center py-4 d-none">Nenhum agendamento encontrado.</div>
      </div>
    </div>

    <!-- SERVIÇOS -->
    <div id="tab-servicos" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="section-title mb-0">Serviços</h2>
        <button class="btn btn-gold" onclick="abrirModalServico()"><i class="fas fa-plus me-2"></i>Novo Serviço</button>
      </div>
      <div class="table-responsive">
        <table class="table table-dark table-hover">
          <thead><tr><th>#</th><th>Nome</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody id="tabela-servicos"></tbody>
        </table>
        <div id="msg-sem-servicos" class="text-secondary text-center py-4 d-none">Nenhum serviço cadastrado.</div>
      </div>
    </div>

    <!-- LOJA -->
    <div id="tab-loja" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h2 class="section-title mb-1">Loja</h2>
          <p class="text-secondary mb-0">Gerencie os produtos exibidos no site.</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-gold" onclick="abrirModalProduto()"><i class="fas fa-plus me-2"></i>Novo Produto</button>
        </div>
      </div>

      <h5 class="text-light mt-2">Produtos</h5>
      <div class="table-responsive">
        <table class="table table-dark table-hover">
          <thead><tr><th>Imagem</th><th>Produto</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody id="tabela-produtos"></tbody>
        </table>
        <div id="msg-sem-produtos" class="text-secondary text-center py-3 d-none">Nenhum produto cadastrado.</div>
      </div>
    </div>

  </div>
</div>

<!-- MODAL AGENDAMENTO -->
<div class="modal fade" id="modalAgendamento" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="modal-ag-titulo">Agendamento</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ag-id">
      <div class="mb-3"><label class="form-label">Cliente</label><input type="text" id="ag-nome" class="form-control" placeholder="Nome do cliente"></div>
      <div class="mb-3"><label class="form-label">Serviço</label>
        <select id="ag-servico" class="form-select"><option value="">Selecione</option></select>
      </div>
      <div class="mb-3"><label class="form-label">Data/Hora</label><input type="datetime-local" id="ag-datahora" class="form-control"></div>
      <div class="mb-3"><label class="form-label">Status</label>
        <select id="ag-status" class="form-select">
          <option value="pendente">Pendente</option>
          <option value="confirmado">Confirmado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn btn-gold" onclick="salvarAgendamento()">Salvar</button>
    </div>
  </div></div>
</div>

<!-- MODAL SERVIÇO -->
<div class="modal fade" id="modalServico" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="modal-sv-titulo">Serviço</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="sv-id">
      <div class="mb-3"><label class="form-label">Nome</label><input type="text" id="sv-nome" class="form-control" placeholder="Ex.: Corte Premium"></div>
      <div class="mb-3"><label class="form-label">Preço (R$)</label><input type="number" id="sv-preco" class="form-control" step="0.01" min="0.01"></div>
      <div class="mb-3"><label class="form-label">Status</label>
        <select id="sv-ativo" class="form-select">
          <option value="1">Ativo</option>
          <option value="0">Inativo</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn btn-gold" onclick="salvarServico()">Salvar</button>
    </div>
  </div></div>
</div>

<!-- MODAL PRODUTO -->
<div class="modal fade" id="modalProduto" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header">
    <h5 class="modal-title" id="modal-prod-titulo">Produto</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="prod-id">
    <div class="row g-3">
      <div class="col-md-8"><label class="form-label">Nome</label><input type="text" id="prod-nome" class="form-control" placeholder="Ex.: Pomada Modeladora Matte"></div>
      <div class="col-md-4"><label class="form-label">Preço (R$)</label><input type="number" id="prod-preco" class="form-control" step="0.01" min="0.01"></div>
      <div class="col-md-6"><label class="form-label">Status</label>
        <select id="prod-status" class="form-select">
          <option value="pendente">Pendente</option>
          <option value="aprovado">Aprovado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
      <div class="col-12"><label class="form-label">Descrição</label><textarea id="prod-descricao" class="form-control" rows="2"></textarea></div>
      <div class="col-12"><label class="form-label">URL da imagem</label><input type="url" id="prod-imagem" class="form-control" placeholder="https://..."></div>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-gold" onclick="salvarProduto()">Salvar</button>
  </div>
</div></div></div>

<?php $incluirAdminJs = true; include __DIR__ . '/includes/footer-admin.php'; ?>