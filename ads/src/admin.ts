// Tipagem mínima do Bootstrap (evita o uso de "any")
interface BootstrapModalInstance {
  show(): void;
  hide(): void;
}
interface BootstrapModalConstructor {
  new (el: HTMLElement): BootstrapModalInstance;
  getInstance(el: HTMLElement): BootstrapModalInstance | null;
}
declare const bootstrap: {
  Modal: BootstrapModalConstructor;
};

// Funções chamadas via onclick="" no HTML precisam existir em "window".
// Declarar a extensão da interface Window evita o uso de "any".
declare global {
  interface Window {
    abrirModalNovoAgendamento: () => void;
    editarAgendamento: (id: number) => void;
    salvarAgendamento: () => Promise<void>;
    excluirAgendamento: (id: number) => Promise<void>;
    abrirModalCliente: () => void;
    editarCliente: (id: number) => void;
    salvarCliente: () => Promise<void>;
    excluirCliente: (id: number) => Promise<void>;
    abrirModalServico: () => void;
    editarServico: (id: number) => void;
    salvarServico: () => Promise<void>;
    excluirServico: (id: number) => Promise<void>;
    abrirModalProduto: () => void;
    editarProduto: (id: number) => void;
    salvarProduto: () => Promise<void>;
    excluirProduto: (id: number) => Promise<void>;
  }
}

// ── INTERFACES ────────────────────────────────────────────────────────────────

interface Agendamento {
  id: number;
  cliente_nome: string;
  servico_nome: string;
  servico_preco: number;
  data_hora: string;
  status: "pendente" | "confirmado" | "cancelado";
}

interface Cliente {
  id: number;
  nome: string;
  telefone: string | null;
  email: string | null;
  // Vem de fn_calcular_faturamento_cliente() no banco (rubrica: function)
  faturamento: number;
}

interface Servico {
  id: number;
  nome: string;
  preco: string;
  ativo: "0" | "1";
}

interface Produto {
  id: number;
  nome: string;
  descricao: string;
  preco: string;
  imagem: string;
  status: "pendente" | "aprovado" | "cancelado";
}

interface Indicadores {
  total_agendamentos: string;
  faturamento_total: string;
  agendamentos_hoje: string;
  pendentes: string;
}

interface RankingServico {
  nome: string;
  preco: string;
  total_agendamentos: number;
  faturamento: string;
}

interface RespostaApi<T> {
  sucesso: boolean;
  mensagem: string;
  dados?: T;
}

// ── ESTADO GLOBAL ─────────────────────────────────────────────────────────────

let agendamentosCache: Agendamento[] = [];
let servicosCache: Servico[] = [];
let clientesCache: Cliente[] = [];
let produtosCache: Produto[] = [];
let filtroStatusAtual: string = "";

// ── HELPERS ───────────────────────────────────────────────────────────────────

function mostrarLoading(ativo: boolean): void {
  const el = document.getElementById("loading-overlay");
  if (!el) return;
  el.classList.toggle("ativo", ativo);
}

function formatarMoeda(valor: number): string {
  return valor.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function formatarData(dataHora: string): string {
  if (!dataHora) return "—";
  const d = new Date(dataHora.replace(" ", "T"));
  if (isNaN(d.getTime())) return "—";
  return d.toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
}

function badgeStatus(status: string): string {
  const cores: Record<string, string> = { confirmado: "success", pendente: "warning text-dark", cancelado: "danger" };
  return `<span class="badge bg-${cores[status] ?? "secondary"}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

function badgeLojaStatus(status: string): string {
  const cores: Record<string, string> = { aprovado: "success", pendente: "warning text-dark", cancelado: "danger" };
  return `<span class="badge bg-${cores[status] ?? "secondary"}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

function getEl<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

// Lê o valor de um campo do formulário sem usar "!".
// Se o elemento não existir no DOM, avisa no console e devolve string vazia.
function valorEl(id: string): string {
  const el = getEl<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(id);
  if (!el) {
    console.error(`Campo #${id} não encontrado no formulário.`);
    return "";
  }
  return el.value;
}

// Define o valor de um campo do formulário sem usar "!".
function definirValorEl(id: string, valor: string): void {
  const el = getEl<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(id);
  if (el) el.value = valor;
}

// Abre/fecha um modal Bootstrap com checagem segura de nulo.
function abrirModal(id: string): void {
  const el = getEl(id);
  if (el) new bootstrap.Modal(el).show();
}
function fecharModal(id: string): void {
  const el = getEl(id);
  if (el) bootstrap.Modal.getInstance(el)?.hide();
}

// ── API ───────────────────────────────────────────────────────────────────────

async function buscarDados<T>(acao: string, params: Record<string, string> = {}): Promise<T | null> {
  try {
    const qs = new URLSearchParams(params).toString();
    const resp = await fetch(`api.php?acao=${acao}${qs ? "&" + qs : ""}`);
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const json: RespostaApi<T> = await resp.json();
    if (!json.sucesso) throw new Error(json.mensagem);
    return json.dados ?? null;
  } catch (err) {
    console.error(`Erro [${acao}]:`, err);
    return null;
  }
}

async function enviarDados<T>(payload: Record<string, unknown>): Promise<RespostaApi<T>> {
  try {
    const resp = await fetch("api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    return await resp.json();
  } catch (err) {
    return { sucesso: false, mensagem: "Erro de conexão: " + (err as Error).message };
  }
}

// ── DASHBOARD ─────────────────────────────────────────────────────────────────

async function carregarDashboard(): Promise<void> {
  mostrarLoading(true);
  const [indicadores, ranking] = await Promise.all([
    buscarDados<Indicadores>("indicadores"),
    buscarDados<RankingServico[]>("ranking"),
  ]);
  mostrarLoading(false);

  // Edge case: sem dados
  const statTotal = getEl("stat-total");
  const statFat   = getEl("stat-fat");
  const statHoje  = getEl("stat-hoje");
  const statPend  = getEl("stat-pend");

  if (statTotal) statTotal.textContent = indicadores ? indicadores.total_agendamentos : "0";
  if (statFat)   statFat.textContent   = indicadores ? formatarMoeda(parseFloat(indicadores.faturamento_total) || 0) : "R$ 0,00";
  if (statHoje)  statHoje.textContent  = indicadores ? indicadores.agendamentos_hoje : "0";
  if (statPend)  statPend.textContent  = indicadores ? indicadores.pendentes : "0";

  const rc = getEl("ranking-container");
  if (!rc) return;

  if (!ranking || ranking.length === 0) {
    rc.innerHTML = '<p class="text-secondary">Nenhum dado de ranking disponível.</p>';
    return;
  }

  // Algoritmo de destaque: encontra o campeão com reduce
  const maxAg = ranking.reduce((max, sv) => Math.max(max, Number(sv.total_agendamentos)), 0);

  // .map() — transforma cada serviço em card HTML
  rc.innerHTML = ranking.map((sv) => {
    const total    = Number(sv.total_agendamentos);
    const fat      = parseFloat(sv.faturamento) || 0;
    const campeao  = total === maxAg && maxAg > 0;
    const borda    = campeao ? "border:2px solid #f3c800;position:relative;" : "";
    const trofeu   = campeao ? '<span style="position:absolute;top:8px;right:10px;font-size:18px;">🏆</span>' : "";
    return `
      <div class="col-md-3">
        <div class="card-stat" style="${borda}">
          ${trofeu}
          <h4 style="font-size:20px;">${sv.nome}</h4>
          <p style="color:#f3c800;">${total} agendamento(s)</p>
          <p>Faturamento: ${formatarMoeda(fat)}</p>
        </div>
      </div>`;
  }).join("");
}

// ── AGENDAMENTOS ──────────────────────────────────────────────────────────────

async function carregarAgendamentos(filtro: string = ""): Promise<void> {
  mostrarLoading(true);
  const dados = await buscarDados<Agendamento[]>("agendamentos", { status: filtro });
  mostrarLoading(false);

  const tbody    = getEl("tabela-agendamentos");
  const msgVazio = getEl("msg-sem-agendamentos");
  if (!tbody || !msgVazio) return;

  agendamentosCache = dados ?? [];

  if (agendamentosCache.length === 0) {
    tbody.innerHTML = "";
    msgVazio.classList.remove("d-none");
    return;
  }
  msgVazio.classList.add("d-none");

  // .filter() — separa por status
  const lista = filtro ? agendamentosCache.filter((a) => a.status === filtro) : agendamentosCache;

  // .reduce() — calcula faturamento dos confirmados e exibe na tela
  const faturamento = lista.reduce((acc, a) => {
    return a.status === "confirmado" ? acc + a.servico_preco : acc;
  }, 0);

  const elFat = getEl("ag-faturamento-total");
  if (elFat) elFat.textContent = `Faturamento confirmado: ${formatarMoeda(faturamento)}`;

  // .map() — transforma cada agendamento em linha de tabela
  tbody.innerHTML = lista.map((a) => `
    <tr>
      <td>${a.id}</td>
      <td>${a.cliente_nome}</td>
      <td>${a.servico_nome}</td>
      <td>${formatarData(a.data_hora)}</td>
      <td>${badgeStatus(a.status)}</td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarAgendamento(${a.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirAgendamento(${a.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join("");
}

function preencherSelectServicos(selecionado: string = ""): void {
  const sel = getEl<HTMLSelectElement>("ag-servico");
  if (!sel) return;
  sel.innerHTML = '<option value="">Selecione</option>';
  servicosCache.forEach((s) => {
    const opt = document.createElement("option");
    opt.value = String(s.id);
    opt.textContent = `${s.nome} — ${formatarMoeda(parseFloat(s.preco))}`;
    if (s.nome === selecionado) opt.selected = true;
    sel.appendChild(opt);
  });
}

window.abrirModalNovoAgendamento = function (): void {
  const titulo = getEl("modal-ag-titulo");
  if (titulo) titulo.textContent = "Novo Agendamento";
  definirValorEl("ag-id", "");
  definirValorEl("ag-nome", "");
  definirValorEl("ag-datahora", "");
  definirValorEl("ag-status", "pendente");
  preencherSelectServicos();
  abrirModal("modalAgendamento");
};

window.editarAgendamento = function (id: number): void {
  const ag = agendamentosCache.find((a) => a.id === id);
  if (!ag) return;
  const titulo = getEl("modal-ag-titulo");
  if (titulo) titulo.textContent = "Editar Agendamento";
  definirValorEl("ag-id", String(ag.id));
  definirValorEl("ag-nome", ag.cliente_nome);
  definirValorEl("ag-datahora", ag.data_hora.replace(" ", "T").slice(0, 16));
  definirValorEl("ag-status", ag.status);
  preencherSelectServicos(ag.servico_nome);
  abrirModal("modalAgendamento");
};

window.salvarAgendamento = async function (): Promise<void> {
  const id        = valorEl("ag-id");
  const nome      = valorEl("ag-nome").trim();
  const servicoId = valorEl("ag-servico");
  const dataHora  = valorEl("ag-datahora");
  const status    = valorEl("ag-status");

  if (!nome || !servicoId || !dataHora) { alert("Preencha todos os campos."); return; }

  const resultado = await enviarDados({ acao: id ? "editar_agendamento" : "criar_agendamento", id, nome, servico_id: servicoId, data_hora: dataHora.replace("T", " ") + ":00", status });
  if (resultado.sucesso) {
    fecharModal("modalAgendamento");
    carregarAgendamentos(filtroStatusAtual);
  } else {
    alert("Erro: " + resultado.mensagem);
  }
};

window.excluirAgendamento = async function (id: number): Promise<void> {
  if (!confirm("Excluir este agendamento?")) return;
  const resultado = await enviarDados({ acao: "excluir_agendamento", id });
  if (resultado.sucesso) carregarAgendamentos(filtroStatusAtual);
  else alert("Erro: " + resultado.mensagem);
};

// ── CLIENTES ──────────────────────────────────────────────────────────────────

async function carregarClientes(): Promise<void> {
  mostrarLoading(true);
  const dados = await buscarDados<Cliente[]>("clientes");
  mostrarLoading(false);

  const tbody    = getEl("tabela-clientes");
  const msgVazio = getEl("msg-sem-clientes");
  if (!tbody || !msgVazio) return;

  clientesCache = dados ?? [];

  if (clientesCache.length === 0) {
    tbody.innerHTML = "";
    msgVazio.classList.remove("d-none");
    return;
  }
  msgVazio.classList.add("d-none");

  tbody.innerHTML = clientesCache.map((c) => `
    <tr>
      <td>${c.id}</td>
      <td>${c.nome}</td>
      <td>${c.telefone ?? "—"}</td>
      <td>${c.email ?? "—"}</td>
      <td>${formatarMoeda(c.faturamento)}</td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarCliente(${c.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirCliente(${c.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join("");
}

window.abrirModalCliente = function (): void {
  const titulo = getEl("modal-cli-titulo");
  if (titulo) titulo.textContent = "Novo Cliente";
  definirValorEl("cli-id", "");
  definirValorEl("cli-nome", "");
  definirValorEl("cli-telefone", "");
  definirValorEl("cli-email", "");
  abrirModal("modalCliente");
};

window.editarCliente = function (id: number): void {
  const c = clientesCache.find((x) => x.id === id);
  if (!c) return;
  const titulo = getEl("modal-cli-titulo");
  if (titulo) titulo.textContent = "Editar Cliente";
  definirValorEl("cli-id", String(c.id));
  definirValorEl("cli-nome", c.nome);
  definirValorEl("cli-telefone", c.telefone ?? "");
  definirValorEl("cli-email", c.email ?? "");
  abrirModal("modalCliente");
};

window.salvarCliente = async function (): Promise<void> {
  const id       = valorEl("cli-id");
  const nome     = valorEl("cli-nome").trim();
  const telefone = valorEl("cli-telefone").trim();
  const email    = valorEl("cli-email").trim();

  if (!nome) { alert("Informe o nome do cliente."); return; }

  const resultado = await enviarDados({ acao: id ? "editar_cliente" : "criar_cliente", id, nome, telefone, email });
  if (resultado.sucesso) {
    fecharModal("modalCliente");
    carregarClientes();
  } else {
    alert("Erro: " + resultado.mensagem);
  }
};

window.excluirCliente = async function (id: number): Promise<void> {
  if (!confirm("Excluir este cliente?")) return;
  const resultado = await enviarDados({ acao: "excluir_cliente", id });
  if (resultado.sucesso) carregarClientes();
  else alert("Não foi possível excluir: " + resultado.mensagem);
};

// ── SERVIÇOS ──────────────────────────────────────────────────────────────────

async function carregarServicos(): Promise<void> {
  mostrarLoading(true);
  const dados = await buscarDados<Servico[]>("servicos");
  mostrarLoading(false);

  const tbody    = getEl("tabela-servicos");
  const msgVazio = getEl("msg-sem-servicos");

  servicosCache = dados ?? [];

  if (!tbody || !msgVazio) return;

  if (servicosCache.length === 0) {
    tbody.innerHTML = "";
    msgVazio.classList.remove("d-none");
    return;
  }
  msgVazio.classList.add("d-none");

  tbody.innerHTML = servicosCache.map((s) => `
    <tr>
      <td>${s.id}</td>
      <td>${s.nome}</td>
      <td>${formatarMoeda(parseFloat(s.preco))}</td>
      <td><span class="badge bg-${s.ativo === "1" ? "success" : "secondary"}">${s.ativo === "1" ? "Ativo" : "Inativo"}</span></td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarServico(${s.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirServico(${s.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join("");
}

window.abrirModalServico = function (): void {
  const titulo = getEl("modal-sv-titulo");
  if (titulo) titulo.textContent = "Novo Serviço";
  definirValorEl("sv-id", "");
  definirValorEl("sv-nome", "");
  definirValorEl("sv-preco", "");
  definirValorEl("sv-ativo", "1");
  abrirModal("modalServico");
};

window.editarServico = function (id: number): void {
  const s = servicosCache.find((x) => x.id === id);
  if (!s) return;
  const titulo = getEl("modal-sv-titulo");
  if (titulo) titulo.textContent = "Editar Serviço";
  definirValorEl("sv-id", String(s.id));
  definirValorEl("sv-nome", s.nome);
  definirValorEl("sv-preco", s.preco);
  definirValorEl("sv-ativo", s.ativo);
  abrirModal("modalServico");
};

window.salvarServico = async function (): Promise<void> {
  const id    = valorEl("sv-id");
  const nome  = valorEl("sv-nome").trim();
  const preco = parseFloat(valorEl("sv-preco"));
  const ativo = valorEl("sv-ativo");

  if (!nome || isNaN(preco) || preco <= 0) { alert("Preencha nome e preço válido."); return; }

  const resultado = await enviarDados({ acao: id ? "editar_servico" : "criar_servico", id, nome, preco, ativo });
  if (resultado.sucesso) {
    fecharModal("modalServico");
    carregarServicos();
  } else {
    alert("Erro: " + resultado.mensagem);
  }
};

window.excluirServico = async function (id: number): Promise<void> {
  if (!confirm("Excluir este serviço? Só é possível excluir serviços sem agendamentos vinculados.")) return;
  const resultado = await enviarDados({ acao: "excluir_servico", id });
  if (resultado.sucesso) carregarServicos();
  else alert("Não foi possível excluir: " + resultado.mensagem);
};

// ── LOJA: PRODUTOS ────────────────────────────────────────────────────────────

async function carregarLoja(): Promise<void> {
  mostrarLoading(true);
  const dados = await buscarDados<Produto[]>("produtos");
  mostrarLoading(false);

  produtosCache = dados ?? [];

  const tabelaProd = getEl("tabela-produtos");
  const vazioProd  = getEl("msg-sem-produtos");
  if (!tabelaProd || !vazioProd) return;

  vazioProd.classList.toggle("d-none", produtosCache.length > 0);
  tabelaProd.innerHTML = produtosCache.map((p) => `
    <tr>
      <td><img class="admin-produto-thumb" src="${p.imagem || "https://via.placeholder.com/60"}" alt=""></td>
      <td><strong>${p.nome}</strong><br><small class="text-secondary">${p.descricao || ""}</small></td>
      <td>${formatarMoeda(parseFloat(p.preco))}</td>
      <td>${badgeLojaStatus(p.status)}</td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarProduto(${p.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirProduto(${p.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join("");
}

window.abrirModalProduto = function (): void {
  const titulo = getEl("modal-prod-titulo");
  if (titulo) titulo.textContent = "Novo Produto";
  ["prod-id", "prod-nome", "prod-descricao", "prod-imagem", "prod-preco"].forEach((id) => {
    definirValorEl(id, "");
  });
  definirValorEl("prod-status", "pendente");
  abrirModal("modalProduto");
};

window.editarProduto = function (id: number): void {
  const p = produtosCache.find((x) => x.id === id);
  if (!p) return;
  const titulo = getEl("modal-prod-titulo");
  if (titulo) titulo.textContent = "Editar Produto";
  definirValorEl("prod-id", String(p.id));
  definirValorEl("prod-nome", p.nome);
  definirValorEl("prod-descricao", p.descricao || "");
  definirValorEl("prod-preco", p.preco);
  definirValorEl("prod-imagem", p.imagem || "");
  definirValorEl("prod-status", p.status);
  abrirModal("modalProduto");
};

window.salvarProduto = async function (): Promise<void> {
  const id        = valorEl("prod-id");
  const nome      = valorEl("prod-nome").trim();
  const preco     = parseFloat(valorEl("prod-preco"));
  const descricao = valorEl("prod-descricao").trim();
  const imagem    = valorEl("prod-imagem").trim();
  const status    = valorEl("prod-status");
  if (!nome || isNaN(preco) || preco <= 0) { alert("Preencha nome e preço válido."); return; }
  const resultado = await enviarDados({ acao: "salvar_produto", id, nome, preco, descricao, imagem, status });
  if (resultado.sucesso) {
    fecharModal("modalProduto");
    carregarLoja();
  } else {
    alert("Erro: " + resultado.mensagem);
  }
};

window.excluirProduto = async function (id: number): Promise<void> {
  if (!confirm("Excluir este produto definitivamente?")) return;
  const resultado = await enviarDados({ acao: "excluir_produto", id });
  if (resultado.sucesso) carregarLoja();
  else alert(resultado.mensagem);
};

// ── NAVEGAÇÃO POR ABAS ────────────────────────────────────────────────────────

function ativarTab(nome: string): void {
  document.querySelectorAll<HTMLElement>(".tab-section").forEach((s) => s.classList.add("d-none"));
  document.querySelectorAll<HTMLElement>(".nav-side .nav-link").forEach((l) => l.classList.remove("active"));

  const secao = getEl(`tab-${nome}`);
  const link  = document.querySelector<HTMLElement>(`.nav-side [data-tab="${nome}"]`);

  if (secao) secao.classList.remove("d-none");
  if (link)  link.classList.add("active");

  if (nome === "dashboard")    carregarDashboard();
  if (nome === "agendamentos") { carregarServicos(); carregarAgendamentos(); }
  if (nome === "clientes")     carregarClientes();
  if (nome === "servicos")     carregarServicos();
  if (nome === "loja")         carregarLoja();
}

// ── INICIALIZAÇÃO ─────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll<HTMLElement>(".nav-side [data-tab]").forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      ativarTab(link.dataset["tab"] ?? "dashboard");
    });
  });

  document.querySelectorAll<HTMLElement>("[data-filtro]").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll<HTMLElement>("[data-filtro]").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      filtroStatusAtual = btn.dataset["filtro"] ?? "";
      carregarAgendamentos(filtroStatusAtual);
    });
  });

  carregarDashboard();
  carregarServicos();
});

export {};