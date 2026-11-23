// Teste de carga do MananciAPP com o k6 (https://k6.io, gratuito).
//
// Simula gente de verdade usando o app ao mesmo tempo e mostra onde o site
// começa a ficar lento ou dar erro. Rodar SEMPRE contra a homologação,
// nunca contra a produção (cria sessões e mexe em presença/progresso).
// Passo a passo e como ler o resultado: tests/carga/README.md.
//
//   k6 run -e BASE=https://mananciapp-render-homolog.onrender.com/ tests/carga/carga.js
//
// Variáveis (-e NOME=valor):
//   BASE      endereço do site, com / no fim (obrigatória)
//   CENARIO   "site" (padrão: navegação + live, subindo aos poucos),
//             "culto" (todo mundo chegando em 1 min e ficando 10 min, o
//             caso real do início do culto), "live" (só a live) ou
//             "fumaca" (5 pessoas por 1 min, para conferir o script)
//   PESSOAS   pico de usuários simultâneos (padrão 300)
//   CONTAS    arquivo CSV com "email,senha" por linha (contas de fiel
//             APROVADAS de teste). Cada pessoa virtual usa uma conta, em
//             rodízio. Com uma conta só para todos, o banco disputa a mesma
//             linha o tempo todo e o resultado sai pior que o real.
//   EMAIL / SENHA  uma conta só (se não houver CONTAS). Sem nenhuma, só
//             simula visitantes sem login (páginas públicas e a live).
//   MENSAGENS "1" para quem está na live mandar 1 mensagem a cada ~2 min
//             (grava no chat da homologação; desligado por padrão).
import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { SharedArray } from 'k6/data';

const BASE = (__ENV.BASE || '').replace(/\/?$/, '/');
const PESSOAS = parseInt(__ENV.PESSOAS || '300', 10);
const CENARIO = __ENV.CENARIO || 'site';
const MENSAGENS = __ENV.MENSAGENS === '1';

if (!__ENV.BASE) {
    throw new Error('Informe o site: -e BASE=https://mananciapp-render-homolog.onrender.com/');
}

const contas = new SharedArray('contas', function () {
    if (__ENV.CONTAS) {
        return open(__ENV.CONTAS).split(/\r?\n/)
            .map((linha) => linha.trim())
            .filter((linha) => linha !== '' && linha.indexOf(',') > 0 && !/^email,/i.test(linha))
            .map((linha) => { const i = linha.indexOf(','); return { email: linha.slice(0, i), senha: linha.slice(i + 1) }; });
    }
    return __ENV.EMAIL && __ENV.SENHA ? [{ email: __ENV.EMAIL, senha: __ENV.SENHA }] : [];
});

// Sobe aos poucos até o pico, segura e desce: mostra em que ponto quebra.
const rampa = [
    { duration: '2m', target: Math.round(PESSOAS * 0.3) },
    { duration: '3m', target: PESSOAS },
    { duration: '5m', target: PESSOAS },
    { duration: '1m', target: 0 },
];
// Início do culto: todo mundo em 1 min, 10 min de culto.
const culto = [
    { duration: '1m', target: PESSOAS },
    { duration: '10m', target: PESSOAS },
    { duration: '1m', target: 0 },
];
const fatia = (estagios, parte) => estagios.map((e) => ({ duration: e.duration, target: Math.round(e.target * parte) }));

const cenarios = {
    fumaca: {
        navegacao: { executor: 'constant-vus', vus: 3, duration: '1m', exec: 'navegacao' },
        live: { executor: 'constant-vus', vus: 2, duration: '1m', exec: 'live' },
    },
    site: {
        // 70% navegando e assistindo aula, 30% na live.
        navegacao: { executor: 'ramping-vus', startVUs: 0, stages: fatia(rampa, 0.7), exec: 'navegacao' },
        live: { executor: 'ramping-vus', startVUs: 0, stages: fatia(rampa, 0.3), exec: 'live' },
    },
    culto: {
        // No culto a maioria está na live; o resto vendo aula.
        live: { executor: 'ramping-vus', startVUs: 0, stages: fatia(culto, 0.7), exec: 'live' },
        navegacao: { executor: 'ramping-vus', startVUs: 0, stages: fatia(culto, 0.3), exec: 'navegacao' },
    },
    live: {
        live: { executor: 'ramping-vus', startVUs: 0, stages: rampa, exec: 'live' },
    },
};

export const options = {
    scenarios: cenarios[CENARIO] || cenarios.site,
    // Metas: 95% das páginas em até 1,5 s e dos AJAX em até 500 ms, menos
    // de 1% de erro. Por endpoint também, para saber QUEM ficou lento.
    // Passou disso, o k6 marca o teste como reprovado.
    thresholds: {
        'http_req_failed': ['rate<0.01'],
        'http_req_duration{tipo:pagina}': ['p(95)<1500'],
        'http_req_duration{tipo:ajax}': ['p(95)<500'],
        'http_req_duration{ep:progresso}': ['p(95)<500'],
        'http_req_duration{ep:chat}': ['p(95)<300'],
        'http_req_duration{ep:presenca}': ['p(95)<500'],
    },
};

const pagina = (ep) => ({ tags: { tipo: 'pagina', ep: ep } });
const ajax = (ep) => ({ tags: { tipo: 'ajax', ep: ep } });
const json = (ep) => ({ headers: { 'Content-Type': 'application/json' }, tags: { tipo: 'ajax', ep: ep } });

// Conta desta pessoa virtual (rodízio), ou null sem contas.
function conta() {
    return contas.length ? contas[(__VU - 1) % contas.length] : null;
}

// Login uma vez por pessoa virtual (o k6 guarda o cookie dela).
let logado = null;
function entrar() {
    if (logado !== null) {
        return logado;
    }
    const c = conta();
    if (!c) {
        logado = false;
        return logado;
    }
    const tela = http.get(BASE + 'login.php', pagina('login'));
    const csrf = (tela.body || '').match(/name="csrf_token" value="([^"]+)"/);
    const resp = http.post(BASE + 'login.php', {
        IDF_EMAIL: c.email,
        IDF_SENHA: c.senha,
        csrf_token: csrf ? csrf[1] : '',
    }, pagina('login'));
    logado = check(resp, { 'login entrou': (r) => r.url.indexOf('login.php') === -1 });
    return logado;
}

// Cada pessoa: abre a home, uma lista de cursos, um curso e fica uns
// minutos assistindo uma aula (player mandando o progresso a cada 30 s,
// presença a cada 60 s e o indicador da live), como o navegador faz.
export function navegacao() {
    const comLogin = entrar();

    group('home', () => {
        const home = http.get(BASE + (comLogin ? 'php/fiel/dashboard.php' : 'index.php'), pagina('home'));
        check(home, { 'home 200': (r) => r.status === 200 });
        http.get(BASE + 'ajax_transmissao_status.php', ajax('status'));
    });
    sleep(5 + Math.random() * 10);

    let curso = null;
    group('cursos', () => {
        const lista = http.get(BASE + (comLogin ? 'php/fiel/cursos.php' : 'cursos.php'), pagina('cursos'));
        check(lista, { 'cursos 200': (r) => r.status === 200 });
        const ids = (lista.body || '').match(/curso\.php\?id=(\d+)/g) || [];
        curso = ids.length ? ids[Math.floor(Math.random() * ids.length)].replace(/\D/g, '') : null;
    });
    sleep(5 + Math.random() * 10);

    if (!comLogin || !curso) {
        return;
    }

    let aula = null;
    group('curso', () => {
        const tela = http.get(BASE + 'php/fiel/curso.php?id=' + curso, pagina('curso'));
        const ids = (tela.body || '').match(/assistir_video\.php\?id=(\d+)/g) || [];
        aula = ids.length ? ids[0].replace(/\D/g, '') : null;
    });
    if (!aula) {
        return;
    }

    group('assistindo', () => {
        const player = http.get(BASE + 'php/fiel/assistir_video.php?id=' + aula, pagina('aula'));
        const csrf = (player.body || '').match(/csrf_token: "([^"]+)"/);
        // ~4 min de aula a 1x: progresso a cada 30 s, presença a cada 60 s.
        for (let i = 1; i <= 8; i++) {
            sleep(30);
            const segundos = i * 30;
            const resp = http.post(BASE + 'php/fiel/ajax_progresso.php', JSON.stringify({
                conteudo_id: parseInt(aula, 10),
                segundos_assistidos: segundos,
                posicao_segundos: segundos,
                concluido: false,
                csrf_token: csrf ? csrf[1] : '',
            }), json('progresso'));
            check(resp, { 'progresso 200': (r) => r.status === 200 });
            if (i % 2 === 0) {
                http.post(BASE + 'ajax_presenca.php', JSON.stringify({ pagina: 'assistir_video.php' }), json('presenca'));
                http.get(BASE + 'ajax_transmissao_status.php', ajax('status'));
            }
        }
    });
}

// Pessoa na live: abre a página e acompanha o chat como o navegador
// (php/includes/transmissao_script.php): manda a versão da última lista
// (?v=), de 3 a 10 s conforme o movimento e, logada, confere o bloqueio a
// cada ~30 s (?b=1).
export function live() {
    const comLogin = entrar();
    const tela = http.get(BASE + (comLogin ? 'php/fiel/ao_vivo.php' : 'ao-vivo.php'), pagina('live'));
    const id = (tela.body || '').match(/TRANSMISSAO_ID = (\d+)/);
    if (!id) {
        // Sem live AO_VIVO na homologação, o chat não é pedido.
        sleep(30);
        return;
    }
    const csrf = (tela.body || '').match(/CSRF_TOKEN = "([^"]+)"/);
    let versao = '';
    let intervalo = 5;
    let ultimoBloqueio = 0;
    let ultimaMensagem = Date.now();
    const fim = Date.now() + 5 * 60 * 1000;
    while (Date.now() < fim) {
        let url = BASE + 'ajax_transmissao_comentarios.php?idtr_id=' + id[1];
        if (versao) url += '&v=' + versao;
        if (comLogin && Date.now() - ultimoBloqueio > 30000) {
            url += '&b=1';
            ultimoBloqueio = Date.now();
        }
        const chat = http.get(url, ajax('chat'));
        check(chat, { 'chat 200': (r) => r.status === 200 });
        let novo = false;
        try {
            const dados = chat.json();
            if (dados && dados.ok && !dados.igual) {
                novo = versao !== '' && dados.versao !== versao;
                versao = dados.versao || '';
            }
        } catch (e) { /* resposta não-JSON já conta no check acima */ }

        if (MENSAGENS && comLogin && csrf && Date.now() - ultimaMensagem > 90000 + Math.random() * 60000) {
            http.post(BASE + 'php/fiel/ajax_transmissao_comentar.php', JSON.stringify({
                idtr_id: parseInt(id[1], 10),
                mensagem: 'Amém! (teste de carga)',
                csrf_token: csrf[1],
            }), json('mensagem'));
            ultimaMensagem = Date.now();
            novo = true;
        }
        intervalo = novo ? 3 : Math.min(10, intervalo * 1.5);
        sleep(intervalo);
    }
}
