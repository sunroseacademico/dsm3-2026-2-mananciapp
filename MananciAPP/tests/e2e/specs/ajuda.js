// Coleta o que dá errado na página: CSP barrando algo, erro de JavaScript e
// erro do PHP impresso no HTML.
//
// Para não acusar o que não é nosso (falso positivo): mensagens que citam
// serviços de fora (OneSignal, YouTube, Google, Cloudflare Insights) não
// contam — dependem da rede, de cookies e do navegador do teste. Uma
// violação de CSP de algo NOSSO (script, estilo ou imagem do próprio site,
// do R2 ou do CDN do Bootstrap) continua contando.
const { expect } = require('@playwright/test');

const DE_FORA = /onesignal|youtube|ytimg|googlevideo|google|gstatic|doubleclick|cloudflareinsights/i;

function vigiar(page) {
    const problemas = [];
    page.on('console', (m) => {
        const texto = m.text();
        if (m.type() === 'error' && /Content Security Policy|Refused to (load|execute|apply|connect|frame)/i.test(texto) && !DE_FORA.test(texto)) {
            problemas.push('CSP: ' + texto);
        }
    });
    page.on('pageerror', (e) => {
        if (!DE_FORA.test(e.message + ' ' + (e.stack || ''))) {
            problemas.push('JS: ' + e.message);
        }
    });
    return problemas;
}

// Em produção/homologação o PHP não mostra erro na tela (display_errors
// desligado): o que pega erro de verdade é o código 500 da resposta,
// conferido em cada teste. Isto aqui pega o caso de alguém ligar o
// display_errors por engano (vazaria caminho e detalhe do servidor).
async function semErroDoPhp(page) {
    const html = await page.content();
    expect(html).not.toMatch(/<b>(Warning|Fatal error|Notice|Deprecated|Parse error)<\/b>:/);
}

// Comemoração de medalha nova e aviso da ofensiva aparecem por cima da tela
// no primeiro acesso do dia e atrapalhariam os cliques do teste.
async function fecharAvisos(page) {
    const festa = page.locator('#medalhaFesta');
    if (await festa.isVisible().catch(() => false)) {
        await page.keyboard.press('Escape');
        await expect(festa).toBeHidden({ timeout: 5000 });
    }
}

async function entrar(page, email, senha) {
    await page.goto('login.php');
    await page.fill('input[name="IDF_EMAIL"]', email);
    await page.fill('input[name="IDF_SENHA"]', senha);
    // Espera a navegação que o envio do formulário provoca (com sucesso ou
    // com erro, o login sempre recarrega a página).
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('button[type="submit"]')]);
    expect(page.url(), 'O login da conta de teste falhou (e-mail/senha nos segredos E2E_* do GitHub, conta aprovada?).').not.toMatch(/login\.php/);
    // Conta de teste que ainda não aceitou o Termo de Uso para no termo:
    // é configuração da conta, não erro do app.
    expect(page.url(), 'A conta de teste precisa aceitar o Termo de Uso uma vez (entre com ela e aceite).').not.toMatch(/termos\.php/);
    await fecharAvisos(page);
}

module.exports = { vigiar, semErroDoPhp, entrar, fecharAvisos };
