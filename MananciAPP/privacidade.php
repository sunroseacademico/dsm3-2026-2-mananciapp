<?php
// Política de Privacidade (LGPD). Página pública: aparece no cadastro, no
// rodapé, em Configurações → Privacidade e dados e no Termo de Uso.
// Ao mudar algo relevante, trocar PRIVACIDADE_VERSAO_ATUAL (php/conexao.php)
// e a data abaixo. Texto a revisar com um advogado (ver docs/interno/lgpd.md).
require __DIR__ . '/php/conexao.php';

$contato_email = trim((string) site_env('CONTATO_EMAIL'));
$logado = isset($_SESSION['Usuario_logado']);
$voltar = $logado
    ? site_url(($_SESSION['Usuario_tipo'] ?? '') === 'admin' ? 'php/admin/dashboard.php' : 'php/fiel/configuracoes.php#privacidade')
    : site_url('index.html');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade - Missão Manancial</title>
    <meta name="description" content="Como a plataforma da Missão Evangélica Manancial da Esperança trata seus dados pessoais (LGPD).">
    <link href="css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/php/includes/pwa_head.php'; ?>
    <style>
        body { min-height: 100vh; padding: 2rem 1rem; }
        .politica { max-width: 820px; margin: 0 auto; background: var(--auth-bg, #1e293b); border: 1px solid var(--border-color, rgba(96,165,250,0.2)); border-radius: 20px; padding: 2rem; }
        .politica-cabecalho { text-align: center; margin-bottom: 1.5rem; }
        .politica-cabecalho img { height: 48px; margin-bottom: 0.8rem; }
        .politica-cabecalho h1 { font-size: 1.5rem; margin-bottom: 0.3rem; }
        .politica-cabecalho p, .politica-versao { color: var(--text-muted); font-size: 0.88rem; margin: 0; }
        .politica-indice { display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: center; margin-bottom: 1.5rem; }
        .politica-indice a { font-size: 0.8rem; padding: 0.3rem 0.65rem; border-radius: 999px; border: 1px solid var(--border-color, rgba(96,165,250,0.2)); color: var(--text-main); text-decoration: none; }
        .politica h2 { font-size: 1.08rem; margin: 1.8rem 0 0.6rem; color: var(--text-main); scroll-margin-top: 1rem; }
        .politica p, .politica li, .politica td, .politica th { color: var(--text-muted); font-size: 0.93rem; line-height: 1.65; }
        .politica ul { padding-left: 1.2rem; }
        .politica table { width: 100%; border-collapse: collapse; margin: 0.5rem 0 1rem; }
        .politica th, .politica td { border-bottom: 1px solid var(--border-color, rgba(96,165,250,0.15)); padding: 0.45rem 0.4rem; vertical-align: top; text-align: left; }
        .politica th { color: var(--text-main); font-weight: 600; }
        .politica-tabela { overflow-x: auto; }
        .politica-voltar { display: inline-flex; gap: 0.4rem; align-items: center; margin-bottom: 1rem; }
        @media (max-width: 576px) { .politica { padding: 1.2rem; border-radius: 14px; } body { padding: 1rem 0.5rem; } }
    </style>
</head>
<body>
    <main class="politica">
        <a href="<?php echo htmlspecialchars($voltar); ?>" class="auth-link politica-voltar"><i class="fas fa-arrow-left" aria-hidden="true"></i>Voltar</a>
        <div class="politica-cabecalho">
            <img src="assets/logo-sm.png" alt="Logo Missão Manancial">
            <h1>Política de Privacidade</h1>
            <p>Como cuidamos dos seus dados, em linguagem simples.</p>
        </div>

        <nav class="politica-indice" aria-label="Seções">
            <a href="#quem">Quem cuida</a>
            <a href="#dados">Dados</a>
            <a href="#para-que">Para quê</a>
            <a href="#compartilhamento">Com quem</a>
            <a href="#prazo">Por quanto tempo</a>
            <a href="#menores">Menores</a>
            <a href="#cookies">Cookies</a>
            <a href="#direitos">Seus direitos</a>
            <a href="#seguranca">Segurança</a>
        </nav>

        <h2 id="quem">1. Quem cuida dos seus dados</h2>
        <p>
            A <strong>Missão Evangélica Manancial da Esperança</strong> decide como os dados são usados (controladora).
            A equipe técnica que desenvolve e mantém a plataforma trata os dados em nome da igreja (operadora), seguindo esta política.
        </p>
        <p>
            Dúvidas, pedidos e reclamações sobre seus dados:
            <?php if ($contato_email !== '') { ?>
                <a class="auth-link" href="mailto:<?php echo htmlspecialchars($contato_email); ?>"><?php echo htmlspecialchars($contato_email); ?></a>
                ou pela página de contato.
            <?php } else { ?>
                pela página de contato do app.
            <?php } ?>
            Respondemos em até 15 dias.
        </p>

        <h2 id="dados">2. Quais dados guardamos</h2>
        <ul>
            <li><strong>Cadastro:</strong> nome, e-mail, WhatsApp, filial e, se você informar, data de nascimento e vínculo de membro.</li>
            <li><strong>Perfil (opcional):</strong> foto, CPF, cidade, estado, estado civil, se tem filhos, capa e medalhas em destaque.</li>
            <li><strong>Uso da plataforma:</strong> cursos e aulas assistidos, progresso, certificados, medalhas, dias de acesso (ofensiva), desafios, favoritos e histórico.</li>
            <li><strong>O que você escreve:</strong> comentários, chat das lives, pedidos de oração, testemunhos, resumos, devocionais e anotações das aulas.</li>
            <li><strong>Pagamentos:</strong> assinaturas e contribuições (valor, data, forma e situação). Os dados do cartão ficam só com o Asaas; o app nunca vê o número do cartão.</li>
            <li><strong>Dados técnicos:</strong> endereço IP, navegador e aparelho de cada acesso, para manter sua conta segura.</li>
        </ul>

        <h2 id="para-que">3. Para que usamos</h2>
        <div class="politica-tabela">
            <table>
                <thead><tr><th>Finalidade</th><th>Base legal (LGPD, art. 7º e 11)</th></tr></thead>
                <tbody>
                    <tr><td>Criar e manter sua conta, liberar cursos, gerar certificados</td><td>Execução do serviço que você pediu</td></tr>
                    <tr><td>Aprovação do cadastro pela igreja e vínculo de membro</td><td>Legítimo interesse da igreja / consentimento</td></tr>
                    <tr><td>Notificações, e-mails e lembrete diário</td><td>Consentimento (você liga e desliga em Configurações)</td></tr>
                    <tr><td>Cobranças, recibos e contribuições</td><td>Execução do contrato e obrigação legal</td></tr>
                    <tr><td>Segurança: bloqueio de tentativas, registro de acessos</td><td>Legítimo interesse e obrigação legal (Marco Civil)</td></tr>
                    <tr><td>Estatísticas para a igreja melhorar o conteúdo</td><td>Legítimo interesse, sempre de forma agregada</td></tr>
                </tbody>
            </table>
        </div>
        <p>
            Por ser uma plataforma de igreja, a participação revela convicção religiosa, que a LGPD trata como dado sensível.
            Ela é tratada com o seu consentimento, dado no cadastro, e só para as finalidades acima. Não vendemos dados e não usamos seus dados para propaganda.
        </p>

        <h2 id="compartilhamento">4. Com quem compartilhamos</h2>
        <p>Só com os serviços que fazem o app funcionar, cada um com a parte de que precisa:</p>
        <div class="politica-tabela">
            <table>
                <thead><tr><th>Serviço</th><th>Para quê</th><th>Onde</th></tr></thead>
                <tbody>
                    <tr><td>Supabase</td><td>Banco de dados</td><td>EUA</td></tr>
                    <tr><td>Render</td><td>Servidor do app</td><td>EUA</td></tr>
                    <tr><td>Cloudflare (R2)</td><td>Vídeos e imagens das aulas</td><td>Global</td></tr>
                    <tr><td>OneSignal</td><td>Notificações no celular (só com a sua permissão)</td><td>EUA</td></tr>
                    <tr><td>Brevo</td><td>E-mails (cadastro, senha, avisos)</td><td>União Europeia</td></tr>
                    <tr><td>Asaas</td><td>Pagamentos (Pix, boleto, cartão)</td><td>Brasil</td></tr>
                    <tr><td>YouTube (Google)</td><td>Vídeo das transmissões ao vivo</td><td>EUA</td></tr>
                    <tr><td>Formspree</td><td>Formulário de contato</td><td>EUA</td></tr>
                </tbody>
            </table>
        </div>
        <p>
            Os serviços fora do Brasil seguem contratos com cláusulas de proteção de dados (LGPD, art. 33).
            Outros fiéis veem só o seu nome curto (ex.: "Maria L.") e a foto, nos comentários, rankings e lista de quem está online;
            pedidos de oração e testemunhos podem ser enviados como anônimos. Os administradores da igreja veem seu cadastro completo.
        </p>

        <h2 id="prazo">5. Por quanto tempo guardamos</h2>
        <ul>
            <li>Enquanto sua conta existir. Ao excluir a conta, os dados pessoais são apagados na hora.</li>
            <li>Registros de pagamento: pelo prazo exigido pela lei fiscal (em geral 5 anos), sem ligação com seu nome.</li>
            <li>Registros de acesso (IP e aparelho): até 6 meses depois do fim da sessão, como pede o Marco Civil da Internet.</li>
            <li>Tentativas de login: 30 dias. Notificações já lidas: 180 dias.</li>
            <li>Registro das ações dos administradores: 2 anos.</li>
        </ul>

        <h2 id="menores">6. Crianças e adolescentes</h2>
        <p>
            Menores de 18 anos só se cadastram com o nome e a autorização do pai, mãe ou responsável, informados no cadastro.
            Eles não aparecem no ranking da ofensiva nem na lista de quem está online para outros fiéis.
            O responsável pode pedir a qualquer momento a correção ou a exclusão dos dados do menor.
        </p>

        <h2 id="cookies">7. Cookies e armazenamento no aparelho</h2>
        <ul>
            <li><strong>Cookie de sessão</strong> (essencial): mantém você conectado. Sem ele, o login não funciona.</li>
            <li><strong>Armazenamento local:</strong> tema claro/escuro e preferências do player, guardados só no seu aparelho.</li>
            <li><strong>Notificações:</strong> o OneSignal só é ativado se você permitir no navegador.</li>
        </ul>
        <p>Não usamos cookies de propaganda nem de rastreamento de terceiros.</p>

        <h2 id="direitos">8. Seus direitos</h2>
        <p>Pela LGPD (art. 18), você pode a qualquer momento:</p>
        <ul>
            <li>Saber quais dados temos e <strong>baixar uma cópia</strong> — em Configurações → Privacidade e dados → "Baixar meus dados".</li>
            <li><strong>Corrigir</strong> seus dados — em Configurações.</li>
            <li><strong>Excluir sua conta</strong> — em Configurações → Privacidade e dados → "Excluir minha conta".</li>
            <li>Retirar o consentimento das notificações — em Configurações → Notificações.</li>
            <li>Saber com quem compartilhamos (seção 4) e reclamar à Autoridade Nacional de Proteção de Dados (ANPD, gov.br/anpd).</li>
        </ul>

        <h2 id="seguranca">9. Segurança</h2>
        <p>
            Senhas guardadas só como código irreversível (bcrypt), conexão sempre criptografada (HTTPS), sessões que expiram e podem ser encerradas,
            limite de tentativas de login e acesso dos administradores por grupos de permissão, com registro das ações.
            Se acontecer um incidente que possa trazer risco a você, avisaremos você e a ANPD.
        </p>

        <h2>10. Mudanças nesta política</h2>
        <p>Quando algo importante mudar, avisaremos no app antes de valer.</p>
        <p class="politica-versao">Versão de <?php echo htmlspecialchars(date('d/m/Y', strtotime(PRIVACIDADE_VERSAO_ATUAL))); ?>.</p>
    </main>
<script src="js/pwa.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
</body>
</html>
