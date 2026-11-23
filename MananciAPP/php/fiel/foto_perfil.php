<?php
// Serve a foto de perfil de um fiel (tabela ID_FIEL_FOTO, seção 49 do
// bancosupabase.md) só para quem está logado. A URL leva a versão (?v=),
// então o navegador pode guardar a imagem por muito tempo: trocar a foto
// muda a URL. Ver fiel_foto_url() em conexao.php.
include("../conexao.php");
auth_require();

$fiel_id = (int) ($_GET['id'] ?? 0);
$linha = null;
if ($fiel_id > 0 && fiel_foto_disponivel($conn)) {
    // Para outros fiéis, só a foto de conta aprovada e ativa: os ids são
    // sequenciais e qualquer logado baixava a foto de contas pendentes,
    // negadas ou desativadas, que não aparecem em lugar nenhum do app. A
    // própria foto e o admin continuam vendo sempre.
    $ve_tudo = $fiel_id === auth_resolve_fiel_id() || ($_SESSION['Usuario_tipo'] ?? '') === 'admin';
    $filtro_conta = $ve_tudo ? "" : " AND f.IDF_STATUS = 'aprovado' AND COALESCE(f.IDF_ATIVO, 1) = 1";
    // Menor de idade (LGPD art. 14): a foto só aparece para ele mesmo e para
    // o admin, como no ranking e na lista de online. Antes qualquer logado
    // baixava a foto de todos trocando o id.
    if (!$ve_tudo && tabela_tem_coluna($conn, 'id_fiel', 'idf_nascimento')) {
        $filtro_conta .= " AND (f.IDF_NASCIMENTO IS NULL OR f.IDF_NASCIMENTO <= (NOW() AT TIME ZONE 'America/Sao_Paulo')::date - INTERVAL '" . (int) LGPD_MAIORIDADE . " years')";
    }
    $res = mysqli_query($conn, "SELECT ff.idff_dados FROM id_fiel_foto ff JOIN ID_FIEL f ON f.IDF_ID = ff.idf_id WHERE ff.idf_id = $fiel_id$filtro_conta LIMIT 1");
    $linha = $res ? mysqli_fetch_assoc($res) : null;
}

$binario = $linha ? base64_decode((string) $linha['idff_dados'], true) : false;
if ($binario === false || $binario === '') {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

// A sessão manda "Pragma: no-cache"/"Expires" por padrão; aqui o cache é
// desejado.
header_remove('Pragma');
header_remove('Expires');
header('Content-Type: image/jpeg');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($binario));
header('Cache-Control: private, max-age=31536000, immutable');
echo $binario;
