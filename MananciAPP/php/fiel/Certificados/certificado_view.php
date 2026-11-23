<?php
include("../../conexao.php");
auth_require();

$fiel_id = auth_resolve_fiel_id();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$sql = "
    SELECT cert.*, c.IDC_TITULO, f.IDF_NOME
    FROM ID_CERTIFICADO cert
    JOIN ID_CURSO c ON c.IDC_ID = cert.IDC_ID
    JOIN ID_FIEL f ON f.IDF_ID = cert.IDF_ID
    WHERE cert.IDCERT_ID = $id AND cert.IDF_ID = " . (int) $fiel_id . "
    LIMIT 1
";
$res = mysqli_query($conn, $sql);
$certificado = $res ? mysqli_fetch_assoc($res) : null;

if (!$certificado) {
    header('Location: certificados.php');
    exit;
}

// Boas-vindas: o certificado é a arte desenhada (PDF com o nome e a data do
// fiel), não a versão simples abaixo. ?baixar=1 baixa; sem ele, abre no
// visualizador de PDF do navegador.
if ((int) $certificado['IDC_ID'] === certificado_boas_vindas_curso_id($conn)) {
    $arquivo = certificado_boas_vindas_pdf($certificado['IDF_NOME'], $certificado['IDCERT_DATA_CONCLUSAO']);
    if ($arquivo !== null) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (isset($_GET['baixar']) ? 'attachment' : 'inline') . '; filename="Certificado-Boas-Vindas.pdf"');
        header('Content-Length: ' . strlen($arquivo));
        header('Cache-Control: private, no-store');
        echo $arquivo;
        exit;
    }
    error_log('certificado_boas_vindas_pdf: a arte assets/Certificados/BoasVindas.pdf não tem o formato esperado.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado | <?php echo htmlspecialchars($certificado['IDC_TITULO']); ?></title>
    <link href="../../../css/vendor/bootstrap-5.3.2.min.css?v=5.3.2" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/styles.css?v=<?php echo site_versao_estatico(); ?>">
    <!-- Botão "Imprimir" (data-imprimir): esta página não usa o pwa_head.php. -->
    <script src="../../../js/acoes.js?v=<?php echo site_versao_estatico(); ?>" defer></script>
    <link rel="stylesheet" href="../../../css/vendor/fontawesome-6.5.1/css/all.min.css?v=6.5.1">
    <style>
        body { background: var(--bg-dark); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
        .certificate {
            max-width: 800px; width: 100%; background: linear-gradient(135deg, var(--bg-light), rgba(96,165,250,0.06));
            border: 3px solid var(--primary-light); border-radius: 20px; padding: 3.5rem; text-align: center;
            color: var(--text-main);
        }
        .certificate h1 { font-size: 2rem; font-weight: 800; color: var(--primary-light); margin-bottom: 0.5rem; }
        .certificate .aluno-nome { font-size: 1.6rem; font-weight: 700; margin: 1.5rem 0; }
        .certificate .curso-nome { font-size: 1.2rem; color: var(--text-muted); margin-bottom: 2rem; }
        .certificate .codigo { font-size: 0.85rem; color: var(--text-muted); margin-top: 2rem; }
        .no-print { margin-top: 2rem; }
        /* Na impressão/PDF as cores do tema (texto branco do tema escuro) saíam
           em papel branco: cores fixas escuras, sem depender do fundo. */
        @media print {
            .no-print { display: none; }
            body { background: #fff; padding: 0; min-height: 0; }
            .certificate { background: #fff !important; color: #111827 !important; border-color: #1e3a8a !important; box-shadow: none !important; }
            .certificate h1 { color: #1e3a8a !important; }
            .certificate .curso-nome, .certificate .codigo { color: #374151 !important; }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <i class="fas fa-award" style="font-size: 3rem; color: var(--accent);"></i>
        <h1>Certificado de Conclusão</h1>
        <p>Certificamos que</p>
        <div class="aluno-nome"><?php echo htmlspecialchars($certificado['IDF_NOME']); ?></div>
        <p>concluiu com sucesso o curso</p>
        <div class="curso-nome"><?php echo htmlspecialchars($certificado['IDC_TITULO']); ?></div>
        <p>em <?php echo date('d/m/Y', strtotime($certificado['IDCERT_DATA_CONCLUSAO'])); ?></p>
        <div class="codigo">Código de verificação: <?php echo htmlspecialchars($certificado['IDCERT_NUMERO']); ?></div>
        <div class="no-print">
            <button type="button" class="btn btn-light" data-imprimir><i class="fas fa-print me-2"></i>Imprimir / Salvar PDF</button>
            <a href="certificados.php" class="btn btn-outline-light">Voltar</a>
        </div>
    </div>
</body>
</html>
