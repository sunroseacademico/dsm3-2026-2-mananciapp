<?php
// Partial: formulário de um projeto (Admin → Missões e, para a oferta
// geral, Admin → Contribuições). Salva com missao_projeto_salvar() em
// php/conexao.php. Uso:
//   $mpf = array(
//       'projeto' => linha de missao_projeto() ou null (novo),
//       'action' => 'admin_missoes.php',
//       'geral' => true|false,   // oferta geral: textos de oferta
//   );
//   include __DIR__ . '/includes/missao_projeto_form.php';
// Envia acao=salvar_projeto.
$mpf_p = $mpf['projeto'] ?? null;
$mpf_geral = !empty($mpf['geral']);
// Projeto novo: tudo à mostra, menos o valor de cada contribuição.
$mpf_exibe = $mpf_p ? missao_exibicao($mpf_p) : array('total' => true, 'apoiadores' => true, 'quadro' => true, 'valores' => false);
$mpf_interruptores = missoes_exibicao_disponivel($conn);
?>
<form method="POST" action="<?php echo htmlspecialchars($mpf['action']); ?>" enctype="multipart/form-data" class="row g-3">
    <?php echo csrf_campo(); ?>
    <input type="hidden" name="acao" value="salvar_projeto">
    <input type="hidden" name="idmp_id" value="<?php echo (int) ($mpf_p['idmp_id'] ?? 0); ?>">
    <div class="col-md-7">
        <label class="form-label" for="titulo">Nome</label>
        <input type="text" name="titulo" id="titulo" class="form-control custom-input" maxlength="120" required placeholder="<?php echo $mpf_geral ? 'Ex.: Ajude o site da igreja a crescer' : 'Ex.: Poço artesiano na aldeia'; ?>" value="<?php echo htmlspecialchars((string) ($mpf_p['idmp_titulo'] ?? '')); ?>">
    </div>
    <div class="col-md-5">
        <label class="form-label" for="local">Local (opcional)</label>
        <input type="text" name="local" id="local" class="form-control custom-input" maxlength="100" placeholder="<?php echo $mpf_geral ? '' : 'Ex.: Moçambique'; ?>" value="<?php echo htmlspecialchars((string) ($mpf_p['idmp_local'] ?? '')); ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="descricao"><?php echo $mpf_geral ? 'Para onde vai a oferta' : 'Descrição'; ?></label>
        <textarea name="descricao" id="descricao" class="form-control custom-input" rows="6" maxlength="5000" placeholder="<?php echo $mpf_geral ? 'Explique como a oferta ajuda a manter o app.' : 'Conte a história: quem será ajudado, o que vai ser feito e como o dinheiro será usado.'; ?>"><?php echo htmlspecialchars((string) ($mpf_p['idmp_descricao'] ?? '')); ?></textarea>
    </div>
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="meta">Meta (R$, opcional)</label>
        <input type="text" name="meta" id="meta" class="form-control custom-input" inputmode="decimal" placeholder="Ex.: 10.000,00" value="<?php echo !empty($mpf_p['idmp_meta_centavos']) ? number_format($mpf_p['idmp_meta_centavos'] / 100, 2, ',', '.') : ''; ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="data_fim">Encerra em (opcional)</label>
        <input type="date" name="data_fim" id="data_fim" class="form-control custom-input" value="<?php echo htmlspecialchars((string) ($mpf_p['idmp_data_fim'] ?? '')); ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="status">Situação</label>
        <select name="status" id="status" class="form-select custom-input">
            <?php foreach (array('rascunho' => 'Rascunho (só o admin vê)', 'ativo' => 'Recebendo', 'encerrado' => 'Encerrado') as $valor => $rotulo) { ?>
                <option value="<?php echo $valor; ?>" <?php echo ($mpf_p['idmp_status'] ?? 'rascunho') === $valor ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="capa">Foto de capa<?php echo !empty($mpf_p['idmp_capa_url']) ? ' (trocar)' : ''; ?></label>
        <input type="file" name="capa" id="capa" class="form-control custom-input" accept="image/*">
    </div>

    <div class="col-12">
        <fieldset class="admin-exibicao">
            <legend class="form-label fw-semibold mb-1">O que o fiel vê na página</legend>
            <small class="text-muted d-block mb-2">O dinheiro aparece sempre para a equipe (aqui e no Financeiro). Estas opções valem só para a página do fiel e para a aba Contribuir.</small>
            <?php if ($mpf_interruptores) { ?>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="mostrar_total" id="mostrar_total" <?php echo $mpf_exibe['total'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mostrar_total"><strong>Total arrecadado</strong> <span class="text-muted">— com a barra e a % da meta, se houver</span></label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="mostrar_apoiadores" id="mostrar_apoiadores" <?php echo $mpf_exibe['apoiadores'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mostrar_apoiadores"><strong>Número de apoiadores</strong></label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="mostrar_quadro" id="mostrar_quadro" <?php echo $mpf_exibe['quadro'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="mostrar_quadro"><strong>Quadro de apoiadores</strong> <span class="text-muted">— nomes (ou "anônimo") e mensagens</span></label>
                </div>
            <?php } ?>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="mostrar_valores" id="mostrar_valores" <?php echo !empty($mpf_p['idmp_mostrar_valores']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="mostrar_valores"><strong>Valor de cada contribuição</strong> no quadro de apoiadores</label>
            </div>
            <?php if (!$mpf_interruptores) { ?>
                <small class="text-muted d-block mt-1">Para escolher também o total, o número de apoiadores e o quadro, rode a seção 78 do <code>database/bancosupabase.md</code>.</small>
            <?php } ?>
        </fieldset>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-salvar"><i class="fas fa-save me-1"></i><?php echo $mpf_p ? 'Salvar alterações' : 'Criar projeto'; ?></button>
        <?php if ($mpf_p && !$mpf_geral) { ?><a href="<?php echo htmlspecialchars($mpf['action']); ?>" class="btn btn-outline-secondary">Cancelar</a><?php } ?>
    </div>
</form>
