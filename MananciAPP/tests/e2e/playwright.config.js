// Testes de ponta a ponta: abrem o site num navegador de verdade (Chromium),
// como computador e como celular. Rodam contra a homologação, nunca contra
// a produção (fazem login e navegam). Ver README.md desta pasta.
const { defineConfig, devices } = require('@playwright/test');

const base = (process.env.E2E_BASE || 'https://mananciapp-render-homolog.onrender.com/').replace(/\/?$/, '/');

module.exports = defineConfig({
    testDir: './specs',
    timeout: 60000,
    retries: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: base,
        locale: 'pt-BR',
        timezoneId: 'America/Sao_Paulo',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'computador', use: { ...devices['Desktop Chrome'] } },
        { name: 'celular', use: { ...devices['Pixel 7'] } },
    ],
});
