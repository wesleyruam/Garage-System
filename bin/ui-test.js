#!/usr/bin/env node

const { spawn } = require('node:child_process');
const { existsSync, mkdtempSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');

const root = process.cwd();
const appPort = 18100;
const debugPort = 9331;
const baseUrl = `http://127.0.0.1:${appPort}`;
const database = 'garage_system_ui_test';
const chromeProfile = mkdtempSync(join(tmpdir(), 'garage-chrome-'));

let server;
let chrome;
let chromeOutput = '';
let ws;
let nextId = 1;
const pending = new Map();

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function waitForHttp(url, timeout = 7000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        try {
            const response = await fetch(url);
            if (response.ok) return;
        } catch (_) {
            // Aguarda o servidor subir.
        }
        await wait(150);
    }
    throw new Error(`Timeout aguardando ${url}`);
}

function startServer() {
    server = spawn('php', ['-S', `127.0.0.1:${appPort}`, '-t', 'public'], {
        cwd: root,
        env: { ...process.env, DB_DATABASE: database, APP_ENV: 'testing', APP_DEBUG: 'false' },
        stdio: ['ignore', 'pipe', 'pipe']
    });
}

async function prepareDatabase() {
    await new Promise((resolve, reject) => {
        const migrate = spawn('php', ['bin/migrate.php', '--seed'], {
            cwd: root,
            env: { ...process.env, DB_DATABASE: database },
            stdio: ['ignore', 'pipe', 'pipe']
        });
        let output = '';
        migrate.stdout.on('data', chunk => output += chunk);
        migrate.stderr.on('data', chunk => output += chunk);
        migrate.on('exit', code => code === 0 ? resolve() : reject(new Error(output)));
    });
}

async function startChrome() {
    const browser = existsSync('/usr/bin/chromium') ? 'chromium' : 'google-chrome-stable';
    chrome = spawn(browser, [
        '--headless=new',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-extensions',
        '--disable-component-extensions-with-background-pages',
        `--remote-debugging-port=${debugPort}`,
        `--user-data-dir=${chromeProfile}`,
        'about:blank'
    ], { stdio: ['ignore', 'pipe', 'pipe'] });
    chrome.stdout.on('data', chunk => chromeOutput += chunk);
    chrome.stderr.on('data', chunk => chromeOutput += chunk);

    try {
        await waitForHttp(`http://localhost:${debugPort}/json/version`);
    } catch (error) {
        throw new Error(`${error.message}. Chrome output: ${chromeOutput.slice(-1200)}`);
    }
    const page = await (await fetch(`http://localhost:${debugPort}/json/new?${encodeURIComponent(baseUrl)}`, {
        method: 'PUT'
    })).json();
    ws = new WebSocket(page.webSocketDebuggerUrl);
    ws.onmessage = event => {
        const message = JSON.parse(event.data);
        if (message.id && pending.has(message.id)) {
            const { resolve, reject } = pending.get(message.id);
            pending.delete(message.id);
            message.error ? reject(new Error(message.error.message)) : resolve(message.result);
        }
    };
    await new Promise(resolve => ws.onopen = resolve);
    await cdp('Runtime.enable');
    await cdp('Page.enable');
}

function cdp(method, params = {}) {
    const id = nextId++;
    ws.send(JSON.stringify({ id, method, params }));
    return new Promise((resolve, reject) => pending.set(id, { resolve, reject }));
}

async function evalJs(expression) {
    const result = await cdp('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true
    });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.text || 'Erro no Runtime.evaluate');
    }
    return result.result.value;
}

async function waitFor(expression, timeout = 7000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        if (await evalJs(expression)) return;
        await wait(120);
    }
    const body = await evalJs('document.body.innerText.slice(0, 600)').catch(() => '');
    throw new Error(`Timeout aguardando condição: ${expression}. Tela atual: ${body}`);
}

async function click(selector) {
    const ok = await evalJs(`
        (() => {
            const el = document.querySelector(${JSON.stringify(selector)});
            if (!el) return false;
            el.click();
            return true;
        })()
    `);
    if (!ok) throw new Error(`Elemento não encontrado para click: ${selector}`);
}

async function fill(selector, value) {
    const ok = await evalJs(`
        (() => {
            const el = document.querySelector(${JSON.stringify(selector)});
            if (!el) return false;
            el.focus();
            el.value = ${JSON.stringify(value)};
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        })()
    `);
    if (!ok) throw new Error(`Elemento não encontrado para preencher: ${selector}`);
}

async function login(email, password) {
    await fill('input[name="email"]', email);
    await fill('input[name="senha"]', password);
    await click('#loginForm button[type="submit"]');
}

async function run() {
    await prepareDatabase();
    startServer();
    await waitForHttp(`${baseUrl}/api/health`);
    await startChrome();
    await waitFor('document.querySelector("#loginForm") !== null');

    await login('admin@garage.local', 'Admin@123456');
    await waitFor('document.body.innerText.includes("Dashboard") && document.querySelector("[data-open-modal=\\"ordem\\"]") !== null');
    await waitFor('document.querySelector("#statusRevenueChart") !== null && document.querySelector("#exportStatusChart") !== null');
    await click('#exportStatusChart');
    await click('#exportStatusCsv');

    await click('[data-view="clientes"]');
    await waitFor('document.querySelector("[data-open-modal=\\"cliente\\"]") !== null');
    await click('[data-open-modal="cliente"]');
    await waitFor('document.querySelector(".modal-backdrop") !== null && document.body.innerText.includes("Novo cliente")');
    await click('.modal footer [data-close]');
    await waitFor('document.querySelector(".modal-backdrop") === null');

    await click('[data-view="usuarios"]');
    await waitFor('document.body.innerText.includes("Funcionários da oficina") && document.body.innerText.includes("Clientes com portal")');
    await waitFor('document.querySelector("[data-open-modal=\\"usuario-funcionario\\"]") !== null && document.querySelector("[data-open-modal=\\"usuario-cliente\\"]") !== null');

    await click('[data-view="estoque"]');
    await waitFor('document.body.innerText.includes("Controle rápido de peças")');
    await click('[data-view="financeiro"]');
    await waitFor('document.body.innerText.includes("Prévia financeira")');
    await click('[data-view="ordens"]');
    await waitFor('document.querySelector("#searchOrders") !== null');
    await fill('#searchOrders', 'Revisão');
    await waitFor('document.querySelector("#searchOrders").value === "Revisão"');
    await click('[data-order-detail]');
    await waitFor('document.body.innerText.includes("Histórico") && document.querySelector("[data-load-history]") !== null');
    await click('[data-load-history]');
    await waitFor('document.body.innerText.includes("Histórico de alterações")');
    await click('.modal [data-close]');
    await waitFor('document.querySelector(".modal-backdrop") === null');

    await click('[data-view="agenda"]');
    await waitFor('document.querySelector(".day-event[data-os-id]") !== null');
    await evalJs(`
        (() => {
            const event = document.querySelector('.day-event[data-os-id]');
            const target = [...document.querySelectorAll('.day[data-date]')].find(day => day.dataset.date !== event.closest('.day').dataset.date);
            const data = new DataTransfer();
            event.dispatchEvent(new DragEvent('dragstart', { bubbles: true, dataTransfer: data }));
            target.dispatchEvent(new DragEvent('dragover', { bubbles: true, dataTransfer: data }));
            target.dispatchEvent(new DragEvent('drop', { bubbles: true, dataTransfer: data }));
            return true;
        })()
    `);
    await waitFor('document.body.innerText.includes("O.S. reagendada.")');

    await click('#logoutBtn');
    await waitFor('document.querySelector("#loginForm") !== null');
    await login('cliente@garage.local', 'Cliente@123456');
    await waitFor('document.body.innerText.includes("Minhas ordens")');
    const clienteProtegido = await evalJs('document.body.innerText.includes("Usuários") || document.body.innerText.includes("Clientes")');
    if (clienteProtegido) {
        throw new Error('Portal do cliente exibiu navegação administrativa.');
    }

    console.log('[PASS] Chromium UI flow: login, cancel modal, charts export, order detail, calendar drag/drop and customer portal');
}

async function cleanup() {
    try {
        if (ws) ws.close();
        if (chrome) chrome.kill('SIGTERM');
        if (server) server.kill('SIGTERM');
        rmSync(chromeProfile, { recursive: true, force: true });
    } catch (_) {
        // Best effort.
    }
}

run()
    .catch(error => {
        console.error('[FAIL]', error.message);
        process.exitCode = 1;
    })
    .finally(cleanup);
