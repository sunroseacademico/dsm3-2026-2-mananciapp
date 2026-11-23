<?php
// Identidade da igreja: nome, quem somos, pastor, horários dos cultos,
// endereço, contatos, redes e quem recebe as contribuições. Aparece na home
// pública, no Sobre, no rodapé, no Contato e na tela de contribuição. Tudo
// em id_configuracao (chaves "igreja_*"): ver "IDENTIDADE DA IGREJA" em
// php/conexao.php.
include("../conexao.php");
require_once __DIR__ . '/admin_layout.php';

auth_require(array('admin'));

$mensagem = $_SESSION['igreja_ok'] ?? '';
unset($_SESSION['igreja_ok']);
$erro = '';
$campos = igreja_campos();
$valores = igreja_info();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novos = array();
    $erros = array();
    foreach ($campos as $campo => $def) {
        list($valor, $erro_campo) = igreja_validar_campo($campo, site_entrada_texto($_POST['igreja_' . $campo] ?? ''));
        $novos[$campo] = $valor;
        if ($erro_campo !== '') {
            $erros[] = $erro_campo;
        }
    }
    if ($novos['nome'] === '') {
        $erros[] = 'Informe o nome curto da igreja.';
    }
    if (!csrf_validar()) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página e tente de novo.';
        $valores = $novos;
    } elseif ($erros) {
        // Devolve o que foi digitado: nada se perde por causa de um campo.
        $erro = implode(' ', $erros);
        $valores = $novos;
    } else {
        $ok = true;
        $mudou = array();
        foreach ($novos as $campo => $valor) {
            if ($valor !== trim((string) site_config('igreja_' . $campo, ''))) {
                $mudou[] = $campo;
                $ok = site_config_salvar($conn, 'igreja_' . $campo, $valor) && $ok;
            }
        }
        if ($ok) {
            // A home pública guarda esses dados 10 min (ajax_igreja_publica.php).
            site_cache_apagar('igreja_publica');
            if ($mudou) {
                admin_log($conn, 'igreja.editar', null, null, array('campos' => $mudou));
            }
            $_SESSION['igreja_ok'] = $mudou ? 'Identidade da igreja salva. Já aparece no site.' : 'Nada mudou.';
            header('Location: admin_igreja.php');
            exit;
        }
        $erro = site_erro_banco('Não foi possível salvar.');
        $valores = $novos;
    }
}

// Grupos da tela (a ordem em que a igreja pensa nos dados).
$grupos = array(
    'A igreja' => array('nome', 'nome_completo', 'frase', 'quem_somos', 'pastor'),
    'Cultos e endereço' => array('horarios', 'endereco', 'mapa_url'),
    'Contato e redes' => array('whatsapp', 'telefone', 'email', 'instagram', 'youtube', 'facebook', 'site'),
    'Contribuições' => array('recebedor', 'cnpj'),
);
$ajudas = array(
    'frase' => 'Uma linha que aparece no topo da página inicial, ex.: "Uma igreja para toda a família, em Campinas".',
    'quem_somos' => 'A história e a missão da igreja, em poucas linhas. Aparece na página "Sobre".',
    'horarios' => 'Aparece na página inicial e no "Sobre", em "Venha nos visitar".',
    'mapa_url' => 'No Google Maps: procure a igreja → Compartilhar → Copiar link.',
    'whatsapp' => 'Vira o botão "Falar no WhatsApp" no rodapé, no Contato e na tela de espera do cadastro.',
    'recebedor' => 'Aparece para o fiel na tela de contribuição ("Você está contribuindo para…"), com o CNPJ. Passa confiança na hora do Pix.',
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão Manancial | Identidade da igreja</title>
    <link href="../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <link rel="stylesheet" href="../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo site_versao_estatico(); ?>">
    <script src="../../js/theme.js?v=<?php echo site_versao_estatico(); ?>"></script>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>
</head>
<body class="admin-layout admin-shell">
    <?php admin_layout_abrir($conn, 'igreja', 'Identidade da igreja', 'fa-church'); ?>

    <?php if ($mensagem !== '') { ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensagem); ?></div>
    <?php } ?>
    <?php if ($erro !== '') { ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erro); ?></div>
    <?php } ?>

    <p class="text-muted">Estes dados aparecem na página inicial, no "Sobre", no rodapé, no "Fale conosco" e na tela de contribuição. Campo vazio não aparece em lugar nenhum. <a href="../../index.php" target="_blank" rel="noopener">Ver o site <i class="fas fa-arrow-up-right-from-square ms-1" aria-hidden="true"></i></a></p>

    <form method="POST" data-avisar-ao-sair>
        <?php echo csrf_campo(); ?>
        <?php foreach ($grupos as $titulo => $lista) { ?>
            <div class="card admin-card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?php echo htmlspecialchars($titulo); ?></h2>
                    <div class="row g-3">
                        <?php foreach ($lista as $campo) {
                            list($rotulo, $tipo, $maximo) = $campos[$campo];
                            $id = 'igreja_' . $campo;
                            $largura = $tipo === 'longo' || in_array($campo, array('frase', 'endereco', 'mapa_url', 'nome_completo'), true) ? 'col-12' : 'col-md-6';
                            ?>
                            <div class="<?php echo $largura; ?>">
                                <label class="form-label" for="<?php echo $id; ?>"><?php echo htmlspecialchars($rotulo); ?></label>
                                <?php if ($tipo === 'longo') { ?>
                                    <textarea class="form-control custom-input" id="<?php echo $id; ?>" name="<?php echo $id; ?>" rows="<?php echo $campo === 'quem_somos' ? 6 : 4; ?>" maxlength="<?php echo (int) $maximo; ?>"><?php echo htmlspecialchars($valores[$campo] ?? ''); ?></textarea>
                                <?php } else { ?>
                                    <input class="form-control custom-input" id="<?php echo $id; ?>" name="<?php echo $id; ?>"
                                        type="<?php echo $tipo === 'url' ? 'url' : ($tipo === 'email' ? 'email' : 'text'); ?>"
                                        <?php echo $tipo === 'url' ? 'placeholder="https://"' : ''; ?>
                                        maxlength="<?php echo (int) $maximo; ?>" value="<?php echo htmlspecialchars($valores[$campo] ?? ''); ?>"<?php echo $campo === 'nome' ? ' required' : ''; ?>>
                                <?php } ?>
                                <?php if (isset($ajudas[$campo])) { ?>
                                    <div class="form-text"><?php echo htmlspecialchars($ajudas[$campo]); ?></div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
        <button type="submit" class="btn btn-salvar"><i class="fas fa-save me-2" aria-hidden="true"></i>Salvar</button>
    </form>

    <?php admin_layout_fechar(); ?>
    <script src="../../js/vendor/bootstrap-5.3.2.bundle.min.js?v=5.3.2"></script>
</body>
</html>
