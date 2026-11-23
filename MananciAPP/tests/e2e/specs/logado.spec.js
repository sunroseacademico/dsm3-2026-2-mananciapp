// Fluxos com login. Usam as contas de teste da homologação (segredos do
// GitHub E2E_FIEL_EMAIL/E2E_FIEL_SENHA e E2E_ADMIN_EMAIL/E2E_ADMIN_SENHA);
// sem elas, os testes são pulados. A conta de admin de teste não pode ter
// a verificação em duas etapas ligada.
const { test, expect } = require('@playwright/test');
const { vigiar, semErroDoPhp, entrar, fecharAvisos } = require('./ajuda');

const fiel = { email: process.env.E2E_FIEL_EMAIL, senha: process.env.E2E_FIEL_SENHA };
const admin = { email: process.env.E2E_ADMIN_EMAIL, senha: process.env.E2E_ADMIN_SENHA };

test.describe('fiel', () => {
    test.skip(!fiel.email || !fiel.senha, 'Sem E2E_FIEL_EMAIL/E2E_FIEL_SENHA.');

    test('entra, navega e vê a privacidade', async ({ page, isMobile }) => {
        const problemas = vigiar(page);
        await entrar(page, fiel.email, fiel.senha);
        await expect(page).toHaveURL(/php\/fiel\//);
        await semErroDoPhp(page);

        // Barra inferior só no celular.
        const barra = page.locator('.fiel-barra-inferior');
        if (isMobile) {
            await expect(barra).toBeVisible();
            await barra.getByText('Cursos').click();
            await expect(page).toHaveURL(/cursos\.php/);
        } else {
            await expect(barra).toBeHidden();
        }

        for (const caminho of ['php/fiel/cursos.php', 'php/fiel/busca.php', 'php/fiel/perfil.php', 'php/fiel/ofensiva.php', 'php/fiel/configuracoes.php']) {
            const resposta = await page.goto(caminho);
            expect(resposta.status(), caminho).toBeLessThan(400);
            await semErroDoPhp(page);
            await fecharAvisos(page);
        }
        await expect(page.locator('#privacidade')).toBeVisible();
        expect(problemas).toEqual([]);
    });
});

test.describe('admin', () => {
    test.skip(!admin.email || !admin.senha, 'Sem E2E_ADMIN_EMAIL/E2E_ADMIN_SENHA.');

    test('entra e abre as telas principais do painel', async ({ page }) => {
        const problemas = vigiar(page);
        await entrar(page, admin.email, admin.senha);
        test.skip(/login_2fa\.php/.test(page.url()), 'A conta de admin de teste está com 2FA ligado.');
        await expect(page).toHaveURL(/php\/admin\/dashboard\.php/);
        for (const caminho of ['dashboard.php', 'admin_aprovar_usuarios.php', 'admin_conteudos.php', 'admin_enviar_mensagem.php', 'admin_visao_usuario.php?q=te', 'admin_atividade.php', 'admin_seguranca_conta.php']) {
            const resposta = await page.goto('php/admin/' + caminho);
            expect(resposta.status(), caminho).toBeLessThan(400);
            await semErroDoPhp(page);
        }
        expect(problemas).toEqual([]);
    });
});
