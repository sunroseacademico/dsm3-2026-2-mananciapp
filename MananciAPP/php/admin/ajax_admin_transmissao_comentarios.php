<?php
// Endpoint só para admin — lista os comentários de uma transmissão PRA
// MODERAÇÃO, incluindo os ocultos (a moderação precisa continuar vendo o
// que já escondeu) e se o autor já está bloqueado do chat. Usado pelo
// polling de admin_transmissao.php, pra atualizar a lista a cada poucos
// segundos sem recarregar a página inteira (e sem reiniciar o vídeo).
include("../conexao.php");
auth_require(array('admin'));
// Só lê o banco daqui em diante: solta a trava da sessão para o polling (a
// cada 5 s) não segurar as ações do admin na mesma tela.
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$transmissao_id = isset($_GET['idtr_id']) ? (int) $_GET['idtr_id'] : 0;
if ($transmissao_id <= 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'erro' => 'Transmissão inválida.'));
    exit;
}

// Mesma consulta usada na renderização inicial da tela (ver
// admin_transmissao.php) — mantenha as duas em sincronia se mudar.
$res = mysqli_query($conn, "
    SELECT c.idtc_id, c.idtc_mensagem, c.idtc_oculto, c.idtc_criado_em, f.IDF_NOME, f.IDF_ID,
        EXISTS(
            SELECT 1 FROM id_fiel_bloqueio_chat b
            WHERE b.idf_id = f.IDF_ID AND (b.idbc_expira_em IS NULL OR b.idbc_expira_em > NOW())
        ) AS ja_bloqueado
    FROM id_transmissao_comentario c
    JOIN ID_FIEL f ON f.IDF_ID = c.idf_id
    WHERE c.idtr_id = $transmissao_id
    ORDER BY c.idtc_id DESC
    LIMIT 200
");

$comentarios = array();
if ($res) {
    while ($linha = mysqli_fetch_assoc($res)) {
        $comentarios[] = array(
            'id' => (int) $linha['idtc_id'],
            'nome' => $linha['IDF_NOME'],
            'idf_id' => (int) $linha['IDF_ID'],
            'mensagem' => $linha['idtc_mensagem'],
            'criado_em' => site_data_hora($linha['idtc_criado_em'], 'd/m H:i'),
            'oculto' => (int) $linha['idtc_oculto'] === 1,
            'bloqueado' => !empty($linha['ja_bloqueado']),
        );
    }
}

echo json_encode(array('ok' => true, 'comentarios' => $comentarios));
