<?php
// Partial: lista de contribuições com filtros (só superadmin), em
// Admin → Missões e Admin → Contribuições. Uso:
//   $cl = array(
//       'pagina' => 'admin_missoes.php',            // GET dos filtros e POST de "excluir oferta"
//       'filtros' => missao_contribuicoes_filtros($_GET),
//       'dados' => missao_contribuicoes_admin(...),
//       'projetos' => lista para o filtro de projeto, ou array() para esconder,
//   );
//   include __DIR__ . '/includes/contribuicoes_lista.php';
$cl_f = $cl['filtros'];
$cl_d = $cl['dados'];
$cl_formas = array('PIX' => 'Pix', 'CREDIT_CARD' => 'Cartão', 'BOLETO' => 'Boleto', 'MANUAL' => 'Fora do app');
$cl_filtrando = array_filter($cl_f) !== array();
?>
<div class="card mb-4" id="contribuicoes" style="background: var(--bg-light); border: 1px solid var(--border-color);">
    <div class="card-header" style="background: var(--bg-dark); border-bottom: 1px solid var(--border-color);">
        <h5 class="mb-0"><i class="fas fa-receipt me-2" style="color:#60a5fa;"></i>Contribuições <i class="fas fa-lock ms-1 small text-muted" title="Restrito"></i></h5>
        <small class="text-muted">Nome real mesmo de quem pediu anonimato. Totais e planilha em <a href="admin_financeiro.php">Financeiro</a>.</small>
    </div>
    <div class="card-body border-bottom" style="border-color: var(--border-color) !important;">
        <form method="GET" action="<?php echo htmlspecialchars($cl['pagina']); ?>#contribuicoes" class="row g-2 align-items-end campo-perfil">
            <?php if (!empty($cl['projetos'])) { ?>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label small" for="clProjeto">Projeto</label>
                    <select name="projeto" id="clProjeto" class="form-select form-select-sm custom-input">
                        <option value="0">Todos</option>
                        <?php foreach ($cl['projetos'] as $p) { ?><option value="<?php echo (int) $p['idmp_id']; ?>" <?php echo $cl_f['projeto'] === (int) $p['idmp_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['idmp_titulo']); ?></option><?php } ?>
                    </select>
                </div>
            <?php } ?>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="clSituacao">Situação</label>
                <select name="situacao" id="clSituacao" class="form-select form-select-sm custom-input">
                    <option value="">Todas</option>
                    <option value="pagas" <?php echo $cl_f['situacao'] === 'pagas' ? 'selected' : ''; ?>>Confirmadas</option>
                    <option value="pendentes" <?php echo $cl_f['situacao'] === 'pendentes' ? 'selected' : ''; ?>>Aguardando</option>
                    <option value="outras" <?php echo $cl_f['situacao'] === 'outras' ? 'selected' : ''; ?>>Vencidas/estornadas</option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small" for="clOrigem">Como chegou</label>
                <select name="origem" id="clOrigem" class="form-select form-select-sm custom-input">
                    <option value="">Todas</option>
                    <option value="app" <?php echo $cl_f['origem'] === 'app' ? 'selected' : ''; ?>>Pelo app (com nome)</option>
                    <option value="qrcode" <?php echo $cl_f['origem'] === 'qrcode' ? 'selected' : ''; ?>>Pix direto (QR Code)</option>
                    <option value="manual" <?php echo $cl_f['origem'] === 'manual' ? 'selected' : ''; ?>>Fora do app</option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-1">
                <label class="form-label small" for="clForma">Forma</label>
                <select name="forma" id="clForma" class="form-select form-select-sm custom-input">
                    <option value="">Todas</option>
                    <option value="PIX" <?php echo $cl_f['forma'] === 'PIX' ? 'selected' : ''; ?>>Pix</option>
                    <option value="CREDIT_CARD" <?php echo $cl_f['forma'] === 'CREDIT_CARD' ? 'selected' : ''; ?>>Cartão</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small" for="clDe">De</label>
                <input type="date" name="de" id="clDe" class="form-control form-control-sm custom-input" value="<?php echo htmlspecialchars($cl_f['de']); ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small" for="clAte">Até</label>
                <input type="date" name="ate" id="clAte" class="form-control form-control-sm custom-input" value="<?php echo htmlspecialchars($cl_f['ate']); ?>">
            </div>
            <div class="col-sm-8 col-lg-4">
                <label class="form-label small" for="clBusca">Buscar (nome, e-mail ou mensagem)</label>
                <input type="search" name="busca" id="clBusca" class="form-control form-control-sm custom-input" maxlength="100" value="<?php echo htmlspecialchars($cl_f['busca']); ?>">
            </div>
            <div class="col-sm-4 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-salvar flex-fill"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <?php if ($cl_filtrando) { ?><a href="<?php echo htmlspecialchars($cl['pagina']); ?>#contribuicoes" class="btn btn-sm btn-outline-secondary" title="Limpar filtros"><i class="fas fa-xmark"></i></a><?php } ?>
            </div>
        </form>
        <div class="admin-contrib-resumo mt-3">
            <div><strong><?php echo assinatura_formatar_valor($cl_d['pago_bruto']); ?></strong><small>confirmado (bruto)</small></div>
            <div><strong><?php echo assinatura_formatar_valor($cl_d['pago_liquido']); ?></strong><small>depois das taxas</small></div>
            <div><strong><?php echo (int) $cl_d['pago_qtd']; ?></strong><small><?php echo (int) $cl_d['pago_qtd'] === 1 ? 'contribuição confirmada' : 'contribuições confirmadas'; ?></small></div>
            <div><strong><?php echo (int) $cl_d['pendentes_qtd']; ?></strong><small>aguardando pagamento</small></div>
        </div>
        <?php if ($cl_filtrando) { ?><small class="text-muted d-block mt-2">Totais do filtro escolhido.</small><?php } ?>
    </div>
    <div class="card-body p-0">
        <?php if (!$cl_d['linhas']) { ?>
            <p class="text-muted p-3 mb-0"><?php echo $cl_filtrando ? 'Nenhuma contribuição com esses filtros.' : 'Nenhuma contribuição ainda.'; ?></p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th class="ps-3">Quando</th><th>Quem</th><?php if (!empty($cl['projetos'])) { ?><th>Projeto</th><?php } ?><th class="text-end">Valor</th><th>Forma</th><th>Situação</th><th class="pe-3"></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cl_d['linhas'] as $c) { $info = missao_status_info($c['idmc_status']); ?>
                            <tr>
                                <td class="ps-3"><?php echo date('d/m/Y', strtotime($c['idmc_pago_em'] ?: $c['idmc_criado_em'])); ?></td>
                                <td>
                                    <?php if ($c['idmc_origem'] === 'manual') { ?>
                                        <?php echo htmlspecialchars((string) ($c['idmc_nome_exibicao'] ?: 'Oferta fora do app')); ?><br><small class="text-muted">recebida pela <?php echo htmlspecialchars((string) $c['idmc_recebido_por']); ?></small>
                                    <?php } elseif ($c['idmc_origem'] === 'qrcode') { ?>
                                        Pix direto (QR Code)<br><small class="text-muted">quem paga assim não é identificado no app; o nome fica no painel do Asaas</small>
                                    <?php } else { ?>
                                        <?php echo htmlspecialchars((string) ($c['idf_nome'] ?? 'Conta apagada')); ?>
                                        <?php if ((int) $c['idmc_anonimo'] === 1) { ?><span class="badge bg-secondary ms-1">anônimo</span><?php } ?>
                                        <?php if (!empty($c['idf_email'])) { ?><br><small class="text-muted"><?php echo htmlspecialchars($c['idf_email']); ?></small><?php } ?>
                                    <?php } ?>
                                    <?php if (!empty($c['idmc_mensagem'])) { ?><br><small class="fst-italic">"<?php echo htmlspecialchars($c['idmc_mensagem']); ?>"</small><?php } ?>
                                </td>
                                <?php if (!empty($cl['projetos'])) { ?><td><?php echo htmlspecialchars($c['idmp_titulo']); ?></td><?php } ?>
                                <td class="text-end"><?php echo assinatura_formatar_valor($c['idmc_valor_centavos']); ?></td>
                                <td><?php echo $cl_formas[$c['idmc_forma']] ?? htmlspecialchars((string) $c['idmc_forma']); ?></td>
                                <td><span class="testemunho-status <?php echo $info[1]; ?>"><?php echo $info[0]; ?></span></td>
                                <td class="pe-3">
                                    <?php if (!empty($c['idmc_cancelado_em'])) { ?>
                                        <small class="text-muted">Cancelada em <?php echo date('d/m/Y', strtotime($c['idmc_cancelado_em'])); ?>: <?php echo htmlspecialchars((string) ($c['idmc_cancelado_motivo'] ?? '')); ?></small>
                                    <?php } elseif ($c['idmc_origem'] === 'manual') { ?>
                                        <form method="POST" action="<?php echo htmlspecialchars($cl['pagina']); ?>" class="d-flex gap-1" data-confirmar="Cancelar esta oferta lançada à mão? Ela continua na lista e sai dos totais.">
                                            <?php echo csrf_campo(); ?>
                                            <input type="hidden" name="acao" value="excluir_oferta">
                                            <input type="hidden" name="idmc_id" value="<?php echo (int) $c['idmc_id']; ?>">
                                            <?php if (tabela_tem_coluna($conn, 'id_missao_contribuicao', 'idmc_cancelado_em')) { ?>
                                                <input type="text" name="motivo" class="form-control form-control-sm" style="max-width: 150px;" placeholder="Motivo" maxlength="200" required aria-label="Motivo do cancelamento">
                                            <?php } ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar oferta" aria-label="Cancelar oferta"><i class="fas fa-ban" aria-hidden="true"></i></button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($cl_d['linhas']) >= 300) { ?>
                <p class="small text-muted p-3 mb-0">Mostrando as 300 mais recentes. Use os filtros (os totais acima valem para todas).</p>
            <?php } ?>
        <?php } ?>
    </div>
</div>
