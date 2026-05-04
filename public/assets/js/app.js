const state = {
    csrf: null,
    user: null,
    view: 'dashboard',
    sidebarOpen: false,
    calendarDate: new Date(),
    filters: {
        ordemStatus: 'todos',
        busca: ''
    },
    data: {
        clientes: [],
        veiculos: [],
        produtos: [],
        ordens: [],
        usuarios: [],
        clienteOrdens: []
    }
};

const app = document.querySelector('#app');
const toast = document.querySelector('#toast');

const statusLabels = {
    orcamento: 'Orçamento',
    aprovada: 'Aprovada',
    aberta: 'Aberta',
    em_andamento: 'Em andamento',
    aguardando_pecas: 'Aguardando peças',
    finalizada: 'Finalizada',
    cancelada: 'Cancelada'
};

const navByRole = {
    admin: [
        ['dashboard', 'Dashboard'],
        ['ordens', 'Ordens'],
        ['agenda', 'Agenda'],
        ['estoque', 'Estoque'],
        ['financeiro', 'Financeiro'],
        ['relatorios', 'Relatórios'],
        ['clientes', 'Clientes'],
        ['veiculos', 'Veículos'],
        ['produtos', 'Produtos'],
        ['usuarios', 'Usuários']
    ],
    atendente: [
        ['dashboard', 'Dashboard'],
        ['ordens', 'Ordens'],
        ['agenda', 'Agenda'],
        ['estoque', 'Estoque'],
        ['clientes', 'Clientes'],
        ['veiculos', 'Veículos'],
        ['produtos', 'Produtos']
    ],
    mecanico: [
        ['dashboard', 'Dashboard'],
        ['ordens', 'Ordens'],
        ['agenda', 'Agenda'],
        ['estoque', 'Estoque'],
        ['produtos', 'Produtos']
    ],
    cliente: [
        ['cliente-os', 'Minhas O.S.'],
        ['cliente-agenda', 'Andamento']
    ]
};

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function money(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(String(value).replace(' ', 'T')).toLocaleDateString('pt-BR');
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3200);
}

async function api(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {})
    };

    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        credentials: 'same-origin',
        ...options,
        headers,
        body: options.body && !(options.body instanceof FormData) ? JSON.stringify(options.body) : options.body
    });

    const text = await response.text();
    const payload = text ? JSON.parse(text) : {};

    if (!response.ok) {
        const error = new Error(payload.message || 'Erro inesperado.');
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload.data;
}

async function withCsrf(path, method, body) {
    const headers = { 'X-CSRF-Token': state.csrf };
    if (body instanceof FormData) {
        headers['X-CSRF-Token'] = state.csrf;
    }
    return api(path, {
        method,
        body,
        headers
    });
}

async function loadCsrf() {
    const data = await api('/api/csrf');
    state.csrf = data.token;
}

async function init() {
    try {
        await loadCsrf();
        const user = await api('/api/me').catch(() => null);
        if (user) {
            state.user = user;
            state.view = user.tipo === 'cliente' ? 'cliente-os' : 'dashboard';
            await refreshData();
            renderShell();
        } else {
            renderLogin();
        }
    } catch (error) {
        renderLogin();
        showToast('Não foi possível iniciar a aplicação.');
    }
}

function renderLogin() {
    app.innerHTML = `
        <section class="auth-screen">
            <div class="auth-panel">
                <div class="brand-mark">GS</div>
                <div>
                    <h1>Garage System</h1>
                    <p>Acesse o painel operacional ou acompanhe sua ordem de serviço.</p>
                </div>
                <form class="login-form" id="loginForm">
                    <label class="field">
                        <span>E-mail</span>
                        <input class="input" name="email" type="email" autocomplete="email" value="admin@garage.local" required>
                    </label>
                    <label class="field">
                        <span>Senha</span>
                        <input class="input" name="senha" type="password" autocomplete="current-password" value="Admin@123456" required>
                    </label>
                    <button class="button" type="submit">Entrar</button>
                </form>
                <p class="muted">Cliente de teste: cliente@garage.local / Cliente@123456</p>
            </div>
        </section>
    `;

    document.querySelector('#loginForm').addEventListener('submit', login);
}

async function login(event) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
        const data = await withCsrf('/api/login', 'POST', Object.fromEntries(form));
        state.user = data.user;
        state.csrf = data.csrf_token;
        state.view = state.user.tipo === 'cliente' ? 'cliente-os' : 'dashboard';
        await refreshData();
        renderShell();
        showToast('Sessão iniciada.');
    } catch (error) {
        showToast(error.message);
    }
}

async function logout() {
    try {
        await withCsrf('/api/logout', 'POST', {});
    } catch (error) {
        // Sessão local ainda deve ser descartada mesmo se o servidor já encerrou.
    }
    state.user = null;
    state.csrf = null;
    await loadCsrf();
    renderLogin();
}

function isStaff() {
    return ['admin', 'atendente', 'mecanico'].includes(state.user?.tipo);
}

async function refreshData() {
    if (!state.user) return;

    if (state.user.tipo === 'cliente') {
        state.data.clienteOrdens = await api('/api/cliente/os');
        return;
    }

    const requests = [
        api('/api/clientes').then(data => state.data.clientes = data),
        api('/api/veiculos').then(data => state.data.veiculos = data),
        api('/api/produtos').then(data => state.data.produtos = data),
        api('/api/os').then(data => state.data.ordens = data)
    ];

    if (state.user.tipo === 'admin') {
        requests.push(api('/api/admin/usuarios').then(data => state.data.usuarios = data));
    }

    await Promise.all(requests);
}

function renderShell() {
    const nav = navByRole[state.user.tipo] || navByRole.cliente;
    app.innerHTML = `
        <div class="layout">
            <aside class="sidebar ${state.sidebarOpen ? 'open' : ''}" id="sidebar">
                <div class="sidebar-brand">
                    <div class="brand-mark">GS</div>
                    <div>
                        <strong>Garage System</strong>
                        <span>${escapeHtml(state.user.tipo)}</span>
                    </div>
                </div>
                <nav class="nav">
                    ${nav.map(([key, label]) => `<button class="${state.view === key ? 'active' : ''}" data-view="${key}">${label}</button>`).join('')}
                </nav>
                <div class="sidebar-footer">
                    <div class="user-chip">
                        <strong>${escapeHtml(state.user.nome)}</strong>
                        <span>${escapeHtml(state.user.email)}</span>
                    </div>
                    <button class="button secondary" id="logoutBtn">Sair</button>
                </div>
            </aside>
            <main class="content">
                <div class="topbar">
                    <button class="icon-button mobile-only" id="menuBtn" title="Abrir menu">☰</button>
                    <strong>Garage System</strong>
                    <button class="icon-button" id="refreshBtn" title="Atualizar">↻</button>
                </div>
                <div id="view"></div>
            </main>
        </div>
    `;

    document.querySelectorAll('[data-view]').forEach(button => {
        button.addEventListener('click', async () => {
            state.view = button.dataset.view;
            state.sidebarOpen = false;
            renderShell();
        });
    });

    document.querySelector('#logoutBtn').addEventListener('click', logout);
    document.querySelector('#refreshBtn')?.addEventListener('click', async () => {
        await refreshData();
        renderShell();
        showToast('Dados atualizados.');
    });
    document.querySelector('#menuBtn')?.addEventListener('click', () => {
        state.sidebarOpen = !state.sidebarOpen;
        document.querySelector('#sidebar').classList.toggle('open', state.sidebarOpen);
    });

    renderCurrentView();
}

function renderCurrentView() {
    const container = document.querySelector('#view');
    const views = {
        dashboard: renderDashboard,
        ordens: renderOrdens,
        agenda: () => renderAgenda(state.data.ordens, 'Agenda da oficina'),
        estoque: renderEstoque,
        financeiro: renderFinanceiro,
        relatorios: renderRelatorios,
        clientes: renderClientes,
        veiculos: renderVeiculos,
        produtos: renderProdutos,
        usuarios: renderUsuarios,
        'cliente-os': renderClienteOrdens,
        'cliente-agenda': () => renderAgenda(state.data.clienteOrdens, 'Andamento das minhas O.S.')
    };

    container.innerHTML = (views[state.view] || renderDashboard)();
    bindViewEvents();
}

function pageHead(title, subtitle, actions = '') {
    return `
        <div class="page-head">
            <div class="page-title">
                <h1>${title}</h1>
                <p>${subtitle}</p>
            </div>
            <div class="actions">${actions}</div>
        </div>
    `;
}

function renderDashboard() {
    const ordens = state.data.ordens;
    const abertas = ordens.filter(o => o.status !== 'finalizada' && o.status !== 'cancelada').length;
    const receita = ordens.reduce((sum, order) => sum + Number(order.valor_final || order.valor_inicial || 0), 0);
    const baixoEstoque = state.data.produtos.filter(p => Number(p.estoque) <= 3).length;
    const ticketMedio = ordens.length ? receita / ordens.length : 0;

    return `
        ${pageHead('Dashboard', 'Cockpit operacional da oficina.', '<button class="button secondary" data-view-shortcut="agenda">Ver agenda</button><button class="button" data-open-modal="ordem">Nova O.S.</button>')}
        <section class="stats-grid">
            ${stat('Clientes', state.data.clientes.length)}
            ${stat('O.S. ativas', abertas)}
            ${stat('Alertas estoque', baixoEstoque)}
            ${stat('Ticket médio', money(ticketMedio))}
            ${stat('Receita prevista', money(receita))}
        </section>
        <section class="dashboard-grid">
            <div class="panel pad">
                <div class="section-title"><h2>Fluxo de serviço</h2></div>
                ${kanban(ordens)}
            </div>
            <div class="panel pad">
                <div class="section-title"><h2>Alertas inteligentes</h2></div>
                ${renderAlerts()}
            </div>
        </section>
        <section class="charts-grid">
            ${chartPanel('Receita por status', 'statusRevenueChart', 'exportStatusChart', 'exportStatusCsv')}
            ${chartPanel('O.S. por status', 'statusCountChart', 'exportCountChart', 'exportCountCsv')}
        </section>
        <section class="ops-strip">
            ${operationCard('Entrada', ordens.filter(o => o.status === 'aberta').length, 'Aguardando triagem')}
            ${operationCard('Produção', ordens.filter(o => o.status === 'em_andamento').length, 'Serviços em execução')}
            ${operationCard('Peças', ordens.filter(o => o.status === 'aguardando_pecas').length, 'Dependem do estoque')}
            ${operationCard('Entrega', ordens.filter(o => o.status === 'finalizada').length, 'Prontas/finalizadas')}
        </section>
    `;
}

function chartPanel(title, canvasId, pngId, csvId) {
    return `
        <div class="panel pad">
            <div class="section-title">
                <h2>${title}</h2>
                <div class="actions">
                    <button class="button secondary compact" id="${pngId}">PNG</button>
                    <button class="button secondary compact" id="${csvId}">Excel/CSV</button>
                </div>
            </div>
            <canvas class="chart" id="${canvasId}" width="720" height="300"></canvas>
        </div>
    `;
}

function stat(label, value) {
    return `<div class="stat"><span>${label}</span><strong>${value}</strong></div>`;
}

function operationCard(label, value, detail) {
    return `<div class="operation-card"><span>${label}</span><strong>${value}</strong><small>${detail}</small></div>`;
}

function renderAlerts() {
    const alerts = [];
    state.data.produtos.filter(p => Number(p.estoque) <= 3).slice(0, 4).forEach(p => {
        alerts.push(`<button class="alert-row" data-view-shortcut="estoque"><strong>Estoque baixo</strong><span>${escapeHtml(p.nome)} · ${p.estoque} un.</span></button>`);
    });
    state.data.ordens.filter(o => o.status === 'aguardando_pecas').slice(0, 4).forEach(o => {
        alerts.push(`<button class="alert-row" data-view-shortcut="ordens"><strong>O.S. aguardando peças</strong><span>#${o.id} · ${escapeHtml(o.descricao_problema)}</span></button>`);
    });
    return alerts.join('') || '<div class="empty-state">Nenhum alerta crítico agora.</div>';
}

function kanban(ordens) {
    const groups = [
        ['aberta', 'Abertas'],
        ['em_andamento', 'Em andamento'],
        ['aguardando_pecas', 'Aguardando peças']
    ];

    return `<div class="kanban">
        ${groups.map(([status, label]) => `
            <div class="lane">
                <h3>${label}</h3>
                ${ordens.filter(o => o.status === status).map(orderCard).join('') || '<div class="empty-state">Sem itens</div>'}
            </div>
        `).join('')}
    </div>`;
}

function orderCard(order) {
    return `
        <article class="order-card">
            <strong>O.S. #${order.id}</strong>
            <span class="badge ${order.status}">${statusLabels[order.status] || order.status}</span>
            <p class="muted">${escapeHtml(order.descricao_problema)}</p>
            <small>${formatDate(order.created_at)} · ${money(order.valor_final || order.valor_inicial)}</small>
        </article>
    `;
}

function miniList(items) {
    if (!items.length) return '<div class="empty-state">Nenhuma ordem cadastrada.</div>';
    return items.map(orderCard).join('');
}

function renderClientes() {
    return `
        ${pageHead('Clientes', 'Cadastro e consulta de clientes.', '<button class="button" data-open-modal="cliente">Novo cliente</button>')}
        ${table(['Nome', 'CPF/CNPJ', 'Telefone', 'E-mail'], state.data.clientes.map(c => [
            c.nome, c.cpf_cnpj, c.telefone || '-', c.email || '-'
        ]))}
    `;
}

function renderVeiculos() {
    return `
        ${pageHead('Veículos', 'Frota vinculada aos clientes.', '<button class="button" data-open-modal="veiculo">Novo veículo</button>')}
        ${table(['Placa', 'Modelo', 'Cliente', 'Ano', 'KM'], state.data.veiculos.map(v => [
            v.placa, v.modelo, customerName(v.cliente_id), v.ano || '-', v.km || '-'
        ]))}
    `;
}

function renderProdutos() {
    return `
        ${pageHead('Produtos', 'Estoque e peças da oficina.', '<button class="button" data-open-modal="produto">Novo produto</button>')}
        ${table(['Nome', 'Código', 'Custo', 'Venda', 'Estoque'], state.data.produtos.map(p => [
            p.nome, p.codigo, money(p.preco_custo), money(p.preco_venda), p.estoque
        ]))}
    `;
}

function renderEstoque() {
    const produtos = [...state.data.produtos].sort((a, b) => Number(a.estoque) - Number(b.estoque));
    return `
        ${pageHead('Estoque', 'Controle rápido de peças, margens e reposição.', '<button class="button secondary" data-open-modal="produto">Cadastrar peça</button>')}
        <section class="stats-grid">
            ${stat('Itens cadastrados', produtos.length)}
            ${stat('Baixo estoque', produtos.filter(p => Number(p.estoque) <= 3).length)}
            ${stat('Custo estocado', money(produtos.reduce((sum, p) => sum + Number(p.preco_custo) * Number(p.estoque), 0)))}
            ${stat('Valor de venda', money(produtos.reduce((sum, p) => sum + Number(p.preco_venda) * Number(p.estoque), 0)))}
        </section>
        ${table(['Produto', 'Código', 'Margem', 'Estoque', 'Ação'], produtos.map(p => [
            p.nome,
            p.codigo,
            `${Math.round(((Number(p.preco_venda) - Number(p.preco_custo)) / Math.max(Number(p.preco_venda), 1)) * 100)}%`,
            `<span class="stock-pill ${Number(p.estoque) <= 3 ? 'low' : ''}">${p.estoque} un.</span>`,
            `<button class="button secondary compact" data-stock="${p.id}">Movimentar</button>`
        ]), true)}
    `;
}

function renderFinanceiro() {
    const ordens = state.data.ordens;
    const receita = ordens.reduce((sum, o) => sum + Number(o.valor_final || o.valor_inicial || 0), 0);
    const finalizadas = ordens.filter(o => o.status === 'finalizada');
    const emAberto = ordens.filter(o => o.status !== 'finalizada' && o.status !== 'cancelada');
    return `
        ${pageHead('Financeiro', 'Prévia financeira baseada nas ordens de serviço.', '<button class="button secondary" data-view-shortcut="ordens">Ver O.S.</button>')}
        <section class="stats-grid">
            ${stat('Receita prevista', money(receita))}
            ${stat('Receita finalizada', money(finalizadas.reduce((sum, o) => sum + Number(o.valor_final || o.valor_inicial || 0), 0)))}
            ${stat('A receber', money(emAberto.reduce((sum, o) => sum + Number(o.valor_inicial || 0), 0)))}
            ${stat('O.S. abertas', emAberto.length)}
        </section>
        <div class="panel pad">
            <div class="section-title"><h2>Carteira por status</h2></div>
            <div class="finance-bars">
                ${Object.keys(statusLabels).map(status => financeBar(status, ordens)).join('')}
            </div>
        </div>
    `;
}

function financeBar(status, ordens) {
    const total = ordens.reduce((sum, o) => sum + Number(o.valor_final || o.valor_inicial || 0), 0) || 1;
    const value = ordens.filter(o => o.status === status).reduce((sum, o) => sum + Number(o.valor_final || o.valor_inicial || 0), 0);
    const width = Math.max(4, Math.round((value / total) * 100));
    return `
        <div class="finance-row">
            <span>${statusLabels[status]}</span>
            <div><i style="width:${width}%"></i></div>
            <strong>${money(value)}</strong>
        </div>
    `;
}

function renderRelatorios() {
    const ordens = state.data.ordens;
    const clientesComOs = new Set(ordens.map(o => o.cliente_id)).size;
    const conversao = state.data.clientes.length ? Math.round((clientesComOs / state.data.clientes.length) * 100) : 0;
    return `
        ${pageHead('Relatórios', 'Indicadores gerenciais para tomada de decisão.')}
        <section class="report-grid">
            ${reportCard('Conversão de clientes', `${conversao}%`, 'Clientes com ao menos uma O.S.')}
            ${reportCard('Tempo operacional', `${ordens.filter(o => o.status === 'em_andamento').length} em produção`, 'Carga atual da oficina')}
            ${reportCard('Dependência de peças', `${ordens.filter(o => o.status === 'aguardando_pecas').length}`, 'O.S. travadas por estoque')}
            ${reportCard('Base cadastrada', `${state.data.veiculos.length} veículos`, 'Frota em atendimento')}
        </section>
        <div class="panel pad">
            <div class="section-title"><h2>Resumo executivo</h2></div>
            <p class="muted">Este relatório usa dados operacionais em tempo real. Quando adicionarmos pagamentos e compras, ele pode evoluir para DRE, fluxo de caixa e comissões.</p>
        </div>
    `;
}

function reportCard(title, value, detail) {
    return `<article class="report-card"><span>${title}</span><strong>${value}</strong><p>${detail}</p></article>`;
}

function renderOrdens() {
    const filtered = state.data.ordens.filter(o => {
        const byStatus = state.filters.ordemStatus === 'todos' || o.status === state.filters.ordemStatus;
        const haystack = `${o.id} ${customerName(o.cliente_id)} ${vehicleLabel(o.veiculo_id)} ${o.descricao_problema}`.toLowerCase();
        return byStatus && haystack.includes(state.filters.busca.toLowerCase());
    });

    return `
        ${pageHead('Ordens de serviço', 'Controle operacional dos atendimentos.', '<button class="button" data-open-modal="ordem">Nova O.S.</button>')}
        <div class="toolbar panel pad">
            <input class="input" id="searchOrders" placeholder="Buscar por cliente, veículo, problema ou número" value="${escapeHtml(state.filters.busca)}">
            <select class="select" id="statusFilter">
                <option value="todos">Todos os status</option>
                ${Object.entries(statusLabels).map(([value, label]) => `<option value="${value}" ${state.filters.ordemStatus === value ? 'selected' : ''}>${label}</option>`).join('')}
            </select>
        </div>
        ${table(['O.S.', 'Cliente', 'Veículo', 'Status', 'Problema', 'Valor', 'Ações'], filtered.map(o => [
            `#${o.id}`,
            customerName(o.cliente_id),
            vehicleLabel(o.veiculo_id),
            `<span class="badge ${o.status}">${statusLabels[o.status] || o.status}</span>`,
            escapeHtml(o.descricao_problema),
            money(o.valor_final || o.valor_inicial),
            orderActions(o)
        ]), true)}
    `;
}

function orderActions(order) {
    return `
        <div class="row-actions">
            <button class="button secondary compact" data-order-detail="${order.id}">Detalhes</button>
            <select class="select compact" data-status-change="${order.id}">
                ${Object.entries(statusLabels).map(([value, label]) => `<option value="${value}" ${order.status === value ? 'selected' : ''}>${label}</option>`).join('')}
            </select>
        </div>
    `;
}

function renderUsuarios() {
    const funcionarios = state.data.usuarios.filter(u => u.tipo !== 'cliente');
    const clientes = state.data.usuarios.filter(u => u.tipo === 'cliente');

    return `
        ${pageHead('Usuários', 'Separe acessos internos da oficina e acessos do portal do cliente.', '<button class="button secondary" data-open-modal="usuario-funcionario">Novo funcionário</button><button class="button" data-open-modal="usuario-cliente">Novo acesso cliente</button>')}
        <section class="stats-grid">
            ${stat('Funcionários', funcionarios.length)}
            ${stat('Clientes com acesso', clientes.length)}
            ${stat('Administradores', funcionarios.filter(u => u.tipo === 'admin').length)}
            ${stat('Mecânicos', funcionarios.filter(u => u.tipo === 'mecanico').length)}
            ${stat('Atendentes', funcionarios.filter(u => u.tipo === 'atendente').length)}
        </section>
        <section class="users-split">
            <div>
                <div class="section-title"><h2>Funcionários da oficina</h2><span class="muted">Administração, atendimento e mecânica</span></div>
                ${table(['Nome', 'E-mail', 'Perfil', 'Ativo'], funcionarios.map(u => [
                    u.nome, u.email, roleLabel(u.tipo), u.ativo ? 'Sim' : 'Não'
                ]))}
            </div>
            <div>
                <div class="section-title"><h2>Clientes com portal</h2><span class="muted">Acompanham apenas suas próprias O.S.</span></div>
                ${table(['Nome', 'E-mail', 'Cliente vinculado', 'Ativo'], clientes.map(u => [
                    u.nome, u.email, u.cliente_id ? customerName(u.cliente_id) : '-', u.ativo ? 'Sim' : 'Não'
                ]))}
            </div>
        </section>
    `;
}

function roleLabel(role) {
    return {
        admin: 'Administrador',
        atendente: 'Atendente',
        mecanico: 'Mecânico',
        cliente: 'Cliente'
    }[role] || role;
}

function renderClienteOrdens() {
    return `
        ${pageHead('Minhas ordens', 'Acompanhe cada etapa do serviço do seu veículo.')}
        <section class="stats-grid">
            ${stat('Em aberto', state.data.clienteOrdens.filter(o => o.status !== 'finalizada' && o.status !== 'cancelada').length)}
            ${stat('Finalizadas', state.data.clienteOrdens.filter(o => o.status === 'finalizada').length)}
            ${stat('Total', state.data.clienteOrdens.length)}
            ${stat('Valor previsto', money(state.data.clienteOrdens.reduce((sum, o) => sum + Number(o.valor_final || o.valor_inicial || 0), 0)))}
        </section>
        <div class="panel pad">
            <div class="timeline">
                ${state.data.clienteOrdens.map(clienteTimelineCard).join('') || '<div class="empty-state">Nenhuma O.S. encontrada.</div>'}
            </div>
        </div>
    `;
}

function clienteTimelineCard(order) {
    const steps = ['aberta', 'em_andamento', 'aguardando_pecas', 'finalizada'];
    const current = Math.max(0, steps.indexOf(order.status));
    return `
        <article class="timeline-card">
            <div>
                <strong>O.S. #${order.id}</strong>
                <span class="badge ${order.status}">${statusLabels[order.status] || order.status}</span>
            </div>
            <p>${escapeHtml(order.descricao_problema)}</p>
            <div class="stepper">
                ${steps.map((step, index) => `<span class="${index <= current ? 'done' : ''}">${statusLabels[step]}</span>`).join('')}
            </div>
        </article>
    `;
}

function renderAgenda(ordens, title) {
    const now = state.calendarDate;
    const year = now.getFullYear();
    const month = now.getMonth();
    const first = new Date(year, month, 1);
    const start = new Date(first);
    start.setDate(first.getDate() - first.getDay());
    const days = Array.from({ length: 35 }, (_, index) => {
        const day = new Date(start);
        day.setDate(start.getDate() + index);
        return day;
    });

    return `
        ${pageHead(title, 'Arraste uma O.S. para reagendar. Passe o mouse para detalhes; duplo clique abre a O.S.')}
        <div class="panel pad">
            <div class="section-title">
                <h2>${now.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}</h2>
                <div class="actions">
                    <button class="button secondary compact" data-calendar-nav="-1">Anterior</button>
                    <button class="button secondary compact" data-calendar-today>Hoje</button>
                    <button class="button secondary compact" data-calendar-nav="1">Próximo</button>
                </div>
            </div>
            <div class="calendar">
                <div class="calendar-head">${['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map(d => `<div>${d}</div>`).join('')}</div>
                <div class="calendar-grid">
                    ${days.map(day => calendarDay(day, month, ordens)).join('')}
                </div>
            </div>
        </div>
    `;
}

function calendarDay(day, currentMonth, ordens) {
    const key = day.toISOString().slice(0, 10);
    const events = ordens.filter(order => orderDateKey(order) === key);
    return `
        <div class="day ${day.getMonth() !== currentMonth ? 'dim' : ''}" data-date="${key}">
            <div class="day-number">${day.getDate()}</div>
            ${events.slice(0, 4).map(o => calendarEvent(o)).join('')}
            ${events.length > 2 ? `<div class="day-event">+${events.length - 2}</div>` : ''}
        </div>
    `;
}

function orderDateKey(order) {
    return String(order.agendado_para || order.created_at || '').slice(0, 10);
}

function calendarEvent(order) {
    const detail = `#${order.id} · ${customerName(order.cliente_id)} · ${vehicleLabel(order.veiculo_id)} · ${statusLabels[order.status]} · ${money(order.valor_final || order.valor_inicial)}`;
    return `
        <div class="day-event ${order.status}" draggable="${isStaff()}" data-os-id="${order.id}" title="${escapeHtml(detail)}">
            #${order.id} ${escapeHtml(order.descricao_problema)}
        </div>
    `;
}

function table(headers, rows, allowHtml = false) {
    if (!rows.length) return '<div class="panel"><div class="empty-state">Nada por aqui ainda.</div></div>';
    return `
        <div class="panel table-wrap">
            <table>
                <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                <tbody>
                    ${rows.map(row => `<tr>${row.map(cell => `<td>${allowHtml ? cell : escapeHtml(cell)}</td>`).join('')}</tr>`).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function customerName(id) {
    return state.data.clientes.find(c => Number(c.id) === Number(id))?.nome || `Cliente #${id}`;
}

function vehicleLabel(id) {
    const vehicle = state.data.veiculos.find(v => Number(v.id) === Number(id));
    return vehicle ? `${vehicle.placa} · ${vehicle.modelo}` : `Veículo #${id}`;
}

function bindViewEvents() {
    document.querySelectorAll('[data-open-modal]').forEach(button => {
        button.addEventListener('click', () => openModal(button.dataset.openModal));
    });
    if (state.view === 'dashboard') {
        drawDashboardCharts();
    }
    document.querySelectorAll('[data-view-shortcut]').forEach(button => {
        button.addEventListener('click', () => {
            state.view = button.dataset.viewShortcut;
            renderShell();
        });
    });
    document.querySelector('#statusFilter')?.addEventListener('change', event => {
        state.filters.ordemStatus = event.target.value;
        renderCurrentView();
    });
    document.querySelector('#searchOrders')?.addEventListener('input', event => {
        state.filters.busca = event.target.value;
        renderCurrentView();
    });
    document.querySelectorAll('[data-status-change]').forEach(select => {
        select.addEventListener('change', () => updateOrderStatus(select.dataset.statusChange, select.value));
    });
    document.querySelectorAll('[data-order-detail]').forEach(button => {
        button.addEventListener('click', () => openOrderDetail(button.dataset.orderDetail));
    });
    document.querySelectorAll('[data-stock]').forEach(button => {
        button.addEventListener('click', () => openStockModal(button.dataset.stock));
    });
    document.querySelectorAll('[data-calendar-nav]').forEach(button => {
        button.addEventListener('click', () => {
            state.calendarDate = new Date(state.calendarDate.getFullYear(), state.calendarDate.getMonth() + Number(button.dataset.calendarNav), 1);
            renderCurrentView();
        });
    });
    document.querySelector('[data-calendar-today]')?.addEventListener('click', () => {
        state.calendarDate = new Date();
        renderCurrentView();
    });
    document.querySelectorAll('.day-event[data-os-id]').forEach(eventEl => {
        eventEl.addEventListener('dblclick', () => openOrderDetail(eventEl.dataset.osId));
        eventEl.addEventListener('dragstart', event => {
            event.dataTransfer.setData('text/plain', eventEl.dataset.osId);
        });
    });
    document.querySelectorAll('.day[data-date]').forEach(day => {
        day.addEventListener('dragover', event => event.preventDefault());
        day.addEventListener('drop', event => {
            event.preventDefault();
            const id = event.dataTransfer.getData('text/plain');
            if (id && isStaff()) updateOrderSchedule(id, day.dataset.date);
        });
    });
}

function openModal(type) {
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.innerHTML = modalTemplate(type);
    document.body.appendChild(modal);
    modal.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            modal.remove();
        });
    });
    modal.addEventListener('click', event => {
        if (event.target === modal) modal.remove();
    });
    modal.querySelector('form').addEventListener('submit', event => submitModal(event, type, modal));
}

function modalTemplate(type) {
    const titles = {
        cliente: 'Novo cliente',
        veiculo: 'Novo veículo',
        produto: 'Novo produto',
        ordem: 'Nova ordem de serviço',
        usuario: 'Novo usuário',
        'usuario-funcionario': 'Novo funcionário',
        'usuario-cliente': 'Novo acesso cliente'
    };

    return `
        <section class="modal">
            <header>
                <h2>${titles[type]}</h2>
                <button class="icon-button" data-close type="button" title="Fechar">×</button>
            </header>
            <form>
                <div class="modal-body form-grid">${modalFields(type)}</div>
                <footer>
                    <button class="button secondary" data-close type="button">Cancelar</button>
                    <button class="button" type="submit">Salvar</button>
                </footer>
            </form>
        </section>
    `;
}

function modalFields(type) {
    const templates = {
        cliente: `
            ${field('nome', 'Nome')}
            ${field('cpf_cnpj', 'CPF/CNPJ')}
            ${field('telefone', 'Telefone', 'text', '', false)}
            ${field('email', 'E-mail', 'email', '', false)}
            ${field('endereco', 'Endereço', 'text', 'wide', false)}
        `,
        veiculo: `
            ${selectField('cliente_id', 'Cliente', state.data.clientes.map(c => [c.id, c.nome]))}
            ${field('placa', 'Placa')}
            ${field('modelo', 'Modelo')}
            ${field('ano', 'Ano', 'number', '', false)}
            ${field('km', 'KM', 'number', '', false)}
            ${field('cor', 'Cor', 'text', '', false)}
        `,
        produto: `
            ${field('nome', 'Nome')}
            ${field('codigo', 'Código')}
            ${field('preco_custo', 'Preço de custo', 'number')}
            ${field('preco_venda', 'Preço de venda', 'number')}
            ${field('estoque', 'Estoque', 'number')}
        `,
        ordem: `
            ${selectField('cliente_id', 'Cliente', state.data.clientes.map(c => [c.id, c.nome]))}
            ${selectField('veiculo_id', 'Veículo', state.data.veiculos.map(v => [v.id, `${v.placa} · ${v.modelo}`]))}
            ${field('agendado_para', 'Agendado para', 'datetime-local', '', false)}
            ${field('valor_inicial', 'Valor inicial', 'number')}
            ${textareaField('descricao_problema', 'Descrição do problema')}
            ${textareaField('diagnostico', 'Diagnóstico', false)}
        `,
        usuario: `
            ${field('nome', 'Nome')}
            ${field('email', 'E-mail', 'email')}
            ${field('senha', 'Senha', 'password')}
            ${selectField('tipo', 'Tipo', [['admin', 'Admin'], ['atendente', 'Atendente'], ['mecanico', 'Mecânico'], ['cliente', 'Cliente']])}
            ${selectField('cliente_id', 'Cliente vinculado', [['', 'Nenhum'], ...state.data.clientes.map(c => [c.id, c.nome])])}
        `,
        'usuario-funcionario': `
            ${field('nome', 'Nome')}
            ${field('email', 'E-mail corporativo', 'email')}
            ${field('senha', 'Senha temporária', 'password')}
            ${selectField('tipo', 'Perfil interno', [['atendente', 'Atendente'], ['mecanico', 'Mecânico'], ['admin', 'Administrador']])}
        `,
        'usuario-cliente': `
            ${field('nome', 'Nome do contato')}
            ${field('email', 'E-mail do cliente', 'email')}
            ${field('senha', 'Senha temporária', 'password')}
            <input type="hidden" name="tipo" value="cliente">
            ${selectField('cliente_id', 'Cliente vinculado', state.data.clientes.map(c => [c.id, c.nome]))}
        `
    };

    return templates[type];
}

function drawDashboardCharts() {
    const byStatus = Object.keys(statusLabels).map(status => {
        const orders = state.data.ordens.filter(o => o.status === status);
        return {
            label: statusLabels[status],
            count: orders.length,
            revenue: orders.reduce((sum, o) => sum + Number(o.valor_final || o.valor_inicial || 0), 0)
        };
    });
    drawBarChart('statusRevenueChart', byStatus.map(i => i.label), byStatus.map(i => i.revenue), true);
    drawBarChart('statusCountChart', byStatus.map(i => i.label), byStatus.map(i => i.count), false);

    document.querySelector('#exportStatusChart')?.addEventListener('click', () => exportCanvas('statusRevenueChart', 'receita-por-status.png'));
    document.querySelector('#exportCountChart')?.addEventListener('click', () => exportCanvas('statusCountChart', 'os-por-status.png'));
    document.querySelector('#exportStatusCsv')?.addEventListener('click', () => exportCsv('receita-por-status.csv', ['Status', 'Receita'], byStatus.map(i => [i.label, i.revenue])));
    document.querySelector('#exportCountCsv')?.addEventListener('click', () => exportCsv('os-por-status.csv', ['Status', 'Quantidade'], byStatus.map(i => [i.label, i.count])));
}

function drawBarChart(canvasId, labels, values, currency) {
    const canvas = document.querySelector(`#${canvasId}`);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    const max = Math.max(...values, 1);
    const left = 48;
    const bottom = 48;
    const gap = 18;
    const barWidth = (width - left - 24 - gap * (values.length - 1)) / values.length;
    ctx.strokeStyle = '#dce4de';
    ctx.beginPath();
    ctx.moveTo(left, 16);
    ctx.lineTo(left, height - bottom);
    ctx.lineTo(width - 16, height - bottom);
    ctx.stroke();
    values.forEach((value, index) => {
        const barHeight = ((height - bottom - 32) * value) / max;
        const x = left + 12 + index * (barWidth + gap);
        const y = height - bottom - barHeight;
        ctx.fillStyle = ['#146c5a', '#b7791f', '#c95f32', '#147a4f', '#b42318'][index % 5];
        ctx.fillRect(x, y, barWidth, barHeight);
        ctx.fillStyle = '#17201b';
        ctx.font = '13px sans-serif';
        ctx.fillText(currency ? money(value) : String(value), x, Math.max(14, y - 8));
        ctx.fillStyle = '#647067';
        ctx.font = '12px sans-serif';
        wrapCanvasText(ctx, labels[index], x, height - 28, barWidth);
    });
}

function wrapCanvasText(ctx, text, x, y, maxWidth) {
    const words = String(text).split(' ');
    let line = '';
    words.forEach(word => {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            ctx.fillText(line, x, y);
            line = word;
            y += 14;
        } else {
            line = test;
        }
    });
    ctx.fillText(line, x, y);
}

function exportCanvas(canvasId, filename) {
    const canvas = document.querySelector(`#${canvasId}`);
    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = filename;
    link.click();
}

function exportCsv(filename, headers, rows) {
    const csv = [headers, ...rows]
        .map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(';'))
        .join('\n');
    const blob = new Blob([`\ufeff${csv}`], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
}

function field(name, label, type = 'text', extra = '', required = true) {
    return `<label class="field ${extra}"><span>${label}</span><input class="input" name="${name}" type="${type}" ${type !== 'number' ? '' : 'step="0.01"'} ${required ? 'required' : ''}></label>`;
}

function textareaField(name, label, required = true) {
    return `<label class="field wide"><span>${label}</span><textarea class="textarea" name="${name}" ${required ? 'required' : ''}></textarea></label>`;
}

function selectField(name, label, options) {
    return `<label class="field"><span>${label}</span><select class="select" name="${name}" required>${options.map(([value, text]) => `<option value="${escapeHtml(value)}">${escapeHtml(text)}</option>`).join('')}</select></label>`;
}

async function submitModal(event, type, modal) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const body = Object.fromEntries([...form.entries()].filter(([, value]) => value !== ''));
    const endpoints = {
        cliente: ['/api/clientes', 'POST'],
        veiculo: ['/api/veiculos', 'POST'],
        produto: ['/api/produtos', 'POST'],
        ordem: ['/api/os', 'POST'],
        usuario: ['/api/admin/usuarios', 'POST'],
        'usuario-funcionario': ['/api/admin/usuarios', 'POST'],
        'usuario-cliente': ['/api/admin/usuarios', 'POST']
    };

    try {
        const [path, method] = endpoints[type];
        await withCsrf(path, method, body);
        modal.remove();
        await refreshData();
        renderShell();
        showToast('Registro salvo.');
    } catch (error) {
        showToast(error.message);
    }
}

async function updateOrderStatus(id, status) {
    try {
        await withCsrf(`/api/os/${id}/status`, 'PATCH', { status });
        await refreshData();
        renderShell();
        showToast('Status atualizado.');
    } catch (error) {
        showToast(error.message);
    }
}

async function updateOrderSchedule(id, date) {
    try {
        await withCsrf(`/api/os/${id}/agenda`, 'PATCH', { agendado_para: `${date} 09:00:00` });
        await refreshData();
        renderShell();
        showToast('O.S. reagendada.');
    } catch (error) {
        showToast(error.message);
    }
}

function openOrderDetail(id) {
    const order = state.data.ordens.find(item => Number(item.id) === Number(id));
    if (!order) return;
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <section class="modal">
            <header>
                <h2>Ordem de serviço #${order.id}</h2>
                <button class="icon-button" data-close type="button" title="Fechar">×</button>
            </header>
            <div class="modal-body">
                <div class="detail-grid">
                    <div><span>Cliente</span><strong>${escapeHtml(customerName(order.cliente_id))}</strong></div>
                    <div><span>Veículo</span><strong>${escapeHtml(vehicleLabel(order.veiculo_id))}</strong></div>
                    <div><span>Status</span><strong>${statusLabels[order.status] || order.status}</strong></div>
                    <div><span>Orçamento</span><strong>${escapeHtml(order.orcamento_status || 'rascunho')}</strong></div>
                    <div><span>Valor</span><strong>${money(order.valor_final || order.valor_inicial)}</strong></div>
                </div>
                <h3>Problema relatado</h3>
                <p>${escapeHtml(order.descricao_problema)}</p>
                <h3>Diagnóstico</h3>
                <p>${escapeHtml(order.diagnostico || 'Ainda não informado.')}</p>
                <div class="detail-actions">
                    <button class="button secondary" data-load-history="${order.id}" type="button">Histórico</button>
                    <button class="button secondary" data-load-files="${order.id}" type="button">Anexos</button>
                    <button class="button secondary" data-load-items="${order.id}" type="button">Itens</button>
                    <button class="button secondary" data-load-financial="${order.id}" type="button">Financeiro</button>
                    ${isStaff() ? `<button class="button secondary" data-add-item="${order.id}" type="button">Adicionar item</button>` : ''}
                    ${isStaff() ? `<button class="button secondary" data-add-payment="${order.id}" type="button">Pagamento</button>` : ''}
                    ${isStaff() ? `<button class="button secondary" data-approve-budget="${order.id}" type="button">Aprovar orçamento</button>` : ''}
                    ${isStaff() ? `<button class="button" data-upload-file="${order.id}" type="button">Enviar imagem</button>` : ''}
                </div>
                <div id="orderExtra" class="order-extra"></div>
            </div>
            <footer><button class="button secondary" data-close type="button">Fechar</button></footer>
        </section>
    `;
    document.body.appendChild(modal);
    modal.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => modal.remove()));
    modal.querySelector('[data-load-history]')?.addEventListener('click', () => loadOrderHistory(order.id, modal));
    modal.querySelector('[data-load-files]')?.addEventListener('click', () => loadOrderFiles(order.id, modal));
    modal.querySelector('[data-load-items]')?.addEventListener('click', () => loadOrderItems(order.id, modal));
    modal.querySelector('[data-load-financial]')?.addEventListener('click', () => loadOrderFinancial(order.id, modal));
    modal.querySelector('[data-add-item]')?.addEventListener('click', () => openItemModal(order.id, modal));
    modal.querySelector('[data-add-payment]')?.addEventListener('click', () => openPaymentModal(order.id, modal));
    modal.querySelector('[data-approve-budget]')?.addEventListener('click', () => approveBudget(order.id, modal));
    modal.querySelector('[data-upload-file]')?.addEventListener('click', () => uploadOrderFile(order.id, modal));
}

async function loadOrderItems(id, modal) {
    const items = await api(`/api/os/${id}/itens`);
    modal.querySelector('#orderExtra').innerHTML = `
        <h3>Itens da O.S.</h3>
        ${items.map(item => `<div class="history-item"><strong>${escapeHtml(item.descricao)}</strong><span>${escapeHtml(item.tipo)} · ${item.quantidade} x ${money(item.valor_unitario)} · desconto ${money(item.desconto)}</span><p>Subtotal: <strong>${money(item.subtotal)}</strong></p></div>`).join('') || '<p class="muted">Nenhum item cadastrado.</p>'}
    `;
}

async function loadOrderFinancial(id, modal) {
    const [summary, payments] = await Promise.all([
        api(`/api/os/${id}/financeiro`),
        api(`/api/os/${id}/pagamentos`)
    ]);
    modal.querySelector('#orderExtra').innerHTML = `
        <h3>Resumo financeiro</h3>
        <div class="detail-grid">
            <div><span>Total</span><strong>${money(summary.total)}</strong></div>
            <div><span>Pago</span><strong>${money(summary.pago)}</strong></div>
            <div><span>Saldo</span><strong>${money(summary.saldo)}</strong></div>
            <div><span>Margem</span><strong>${summary.margem_percentual}%</strong></div>
        </div>
        <h3>Pagamentos</h3>
        ${payments.map(payment => `<div class="history-item"><strong>${money(payment.valor)}</strong><span>${escapeHtml(payment.forma_pagamento)} · ${formatDate(payment.data_pagamento)}</span><p>${escapeHtml(payment.observacao || '')}</p></div>`).join('') || '<p class="muted">Nenhum pagamento registrado.</p>'}
    `;
}

function openItemModal(orderId, parentModal) {
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <section class="modal">
            <header><h2>Adicionar item à O.S. #${orderId}</h2><button class="icon-button" data-close type="button">×</button></header>
            <form>
                <div class="modal-body form-grid">
                    ${selectField('tipo', 'Tipo', [['servico', 'Serviço'], ['peca', 'Peça']])}
                    ${selectField('produto_id', 'Produto/peça', [['', 'Nenhum'], ...state.data.produtos.map(p => [p.id, `${p.codigo} · ${p.nome} · estoque ${p.estoque}`])])}
                    ${field('descricao', 'Descrição', 'text', 'wide', false)}
                    ${field('quantidade', 'Quantidade', 'number')}
                    ${field('valor_unitario', 'Valor unitário', 'number')}
                    ${field('custo_unitario', 'Custo unitário', 'number', '', false)}
                    ${field('desconto', 'Desconto', 'number', '', false)}
                </div>
                <footer><button class="button secondary" data-close type="button">Cancelar</button><button class="button" type="submit">Adicionar</button></footer>
            </form>
        </section>`;
    document.body.appendChild(modal);
    modal.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => modal.remove()));
    modal.querySelector('form').addEventListener('submit', async event => {
        event.preventDefault();
        const body = Object.fromEntries([...new FormData(event.currentTarget).entries()].filter(([, value]) => value !== ''));
        try {
            await withCsrf(`/api/os/${orderId}/itens`, 'POST', body);
            modal.remove();
            await refreshData();
            await loadOrderItems(orderId, parentModal);
            showToast('Item adicionado à O.S.');
        } catch (error) {
            showToast(error.message);
        }
    });
}

function openPaymentModal(orderId, parentModal) {
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <section class="modal">
            <header><h2>Registrar pagamento</h2><button class="icon-button" data-close type="button">×</button></header>
            <form>
                <div class="modal-body form-grid">
                    ${field('valor', 'Valor', 'number')}
                    ${selectField('forma_pagamento', 'Forma de pagamento', [['pix', 'PIX'], ['cartao_credito', 'Cartão crédito'], ['cartao_debito', 'Cartão débito'], ['dinheiro', 'Dinheiro'], ['boleto', 'Boleto']])}
                    ${field('observacao', 'Observação', 'text', 'wide', false)}
                </div>
                <footer><button class="button secondary" data-close type="button">Cancelar</button><button class="button" type="submit">Registrar</button></footer>
            </form>
        </section>`;
    document.body.appendChild(modal);
    modal.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => modal.remove()));
    modal.querySelector('form').addEventListener('submit', async event => {
        event.preventDefault();
        const body = Object.fromEntries([...new FormData(event.currentTarget).entries()].filter(([, value]) => value !== ''));
        try {
            await withCsrf(`/api/os/${orderId}/pagamentos`, 'POST', body);
            modal.remove();
            await loadOrderFinancial(orderId, parentModal);
            showToast('Pagamento registrado.');
        } catch (error) {
            showToast(error.message);
        }
    });
}

async function approveBudget(orderId, modal) {
    try {
        await withCsrf(`/api/os/${orderId}/aprovar`, 'POST', {});
        await refreshData();
        showToast('Orçamento aprovado.');
        modal.remove();
        openOrderDetail(orderId);
    } catch (error) {
        showToast(error.message);
    }
}

async function loadOrderHistory(id, modal) {
    const history = await api(`/api/os/${id}/historico`);
    modal.querySelector('#orderExtra').innerHTML = `
        <h3>Histórico de alterações</h3>
        ${history.map(item => `<div class="history-item"><strong>${escapeHtml(item.motivo)}</strong><span>${escapeHtml(item.usuario_nome)} · ${formatDate(item.created_at)}</span><p>${escapeHtml(item.valor_antigo || '-')} → ${escapeHtml(item.valor_novo || '-')}</p></div>`).join('') || '<p class="muted">Sem histórico.</p>'}
    `;
}

async function loadOrderFiles(id, modal) {
    const files = await api(`/api/os/${id}/anexos`);
    modal.querySelector('#orderExtra').innerHTML = `
        <h3>Anexos da O.S.</h3>
        ${files.map(file => `<div class="history-item"><strong>${escapeHtml(file.nome_original)}</strong><span>${escapeHtml(file.mime)} · ${Math.round(Number(file.tamanho) / 1024)} KB</span></div>`).join('') || '<p class="muted">Nenhum anexo enviado.</p>'}
    `;
}

function uploadOrderFile(id, modal) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp,application/pdf';
    input.addEventListener('change', async () => {
        if (!input.files.length) return;
        const form = new FormData();
        form.append('arquivo', input.files[0]);
        try {
            await withCsrf(`/api/os/${id}/anexos`, 'POST', form);
            await loadOrderFiles(id, modal);
            showToast('Arquivo enviado.');
        } catch (error) {
            showToast(error.message);
        }
    });
    input.click();
}

function openStockModal(id) {
    const product = state.data.produtos.find(item => Number(item.id) === Number(id));
    if (!product) return;
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <section class="modal">
            <header>
                <h2>Movimentar estoque</h2>
                <button class="icon-button" data-close type="button" title="Fechar">×</button>
            </header>
            <form>
                <div class="modal-body form-grid">
                    <div class="wide">
                        <p class="muted">${escapeHtml(product.nome)} · estoque atual: <strong>${product.estoque}</strong></p>
                    </div>
                    ${field('quantidade', 'Quantidade (+ entrada / - saída)', 'number')}
                </div>
                <footer>
                    <button class="button secondary" data-close type="button">Cancelar</button>
                    <button class="button" type="submit">Aplicar</button>
                </footer>
            </form>
        </section>
    `;
    document.body.appendChild(modal);
    modal.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            modal.remove();
        });
    });
    modal.querySelector('form').addEventListener('submit', async event => {
        event.preventDefault();
        const body = Object.fromEntries(new FormData(event.currentTarget));
        try {
            await withCsrf(`/api/produtos/${id}/estoque`, 'POST', body);
            modal.remove();
            await refreshData();
            renderShell();
            showToast('Estoque atualizado.');
        } catch (error) {
            showToast(error.message);
        }
    });
}

init();
