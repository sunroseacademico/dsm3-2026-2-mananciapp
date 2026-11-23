// Páginas públicas: abrem, sem erro do PHP, de JavaScript ou de CSP.
const { test, expect } = require('@playwright/test');
const { vigiar, semErroDoPhp } = require('./ajuda');

const paginas = [
    'index.html',
    'sobre.php',
    'cursos.php',
    'ao-vivo.php',
    'como-instalar.php',
    'privacidade.php',
    'login.php',
    'esqueci_senha.php',
    'php/fiel/register.php',
    'php/fiel/termos.php',
];

for (const caminho of paginas) {
    test(`abre ${caminho}`, async ({ page }) => {
        const problemas = vigiar(page);
        const resposta = await page.goto(caminho);
        expect(resposta.status()).toBeLessThan(400);
        await semErroDoPhp(page);
        expect(problemas).toEqual([]);
    });
}

test('cadastro: menor de idade pede o responsável', async ({ page }) => {
    await page.goto('php/fiel/register.php');
    const nascimento = page.locator('input[name="IDF_NASCIMENTO"]');
    test.skip(await nascimento.count() === 0, 'Sem o campo de nascimento (seção 49).');
    const quinzeAnos = new Date();
    quinzeAnos.setFullYear(quinzeAnos.getFullYear() - 15);
    await nascimento.fill(quinzeAnos.toISOString().slice(0, 10));
    await nascimento.dispatchEvent('change');
    await expect(page.locator('#blocoResponsavel')).toBeVisible();
    await nascimento.fill('1990-05-01');
    await nascimento.dispatchEvent('change');
    await expect(page.locator('#blocoResponsavel')).toBeHidden();
});

test('área do fiel sem login volta para o login', async ({ page }) => {
    await page.goto('php/fiel/dashboard.php');
    await expect(page).toHaveURL(/login\.php/);
});
