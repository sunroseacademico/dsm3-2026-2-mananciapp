<?php
// Partial: pergunta "Você é membro da igreja?" + mês/ano de entrada.
// Usado no cadastro (register.php) e no perfil (perfil.php). Ver
// fiel_membro_validar() em conexao.php.
// Uso:
//   $campo_membro = array('membro' => '1'|'0'|'', 'mes' => int, 'ano' => int);
//   include __DIR__ . '/includes/campo_membro.php';
// Envia IDF_MEMBRO, IDF_MEMBRO_MES e IDF_MEMBRO_ANO.

$cm = isset($campo_membro) && is_array($campo_membro) ? $campo_membro : array();
$cm_membro = (string) ($cm['membro'] ?? '');
$cm_mes = (int) ($cm['mes'] ?? 0);
$cm_ano = (int) ($cm['ano'] ?? 0);
$cm_id = 'membro' . substr(md5(uniqid('', true)), 0, 6);
$cm_ano_atual = (int) date('Y');
?>
<fieldset class="campo-membro" id="<?php echo $cm_id; ?>">
    <legend class="campo-membro-titulo">Você é membro da igreja?</legend>
    <div class="campo-membro-opcoes">
        <input type="radio" class="btn-check" name="IDF_MEMBRO" id="<?php echo $cm_id; ?>Sim" value="1" required <?php echo $cm_membro === '1' ? 'checked' : ''; ?>>
        <label class="btn btn-outline-light" for="<?php echo $cm_id; ?>Sim"><i class="fas fa-church me-2"></i>Sim, sou membro</label>
        <input type="radio" class="btn-check" name="IDF_MEMBRO" id="<?php echo $cm_id; ?>Nao" value="0" required <?php echo $cm_membro === '0' ? 'checked' : ''; ?>>
        <label class="btn btn-outline-light" for="<?php echo $cm_id; ?>Nao">Não sou membro</label>
    </div>
    <div class="campo-membro-desde" <?php echo $cm_membro === '1' ? '' : 'hidden'; ?>>
        <small class="campo-membro-ajuda">Desde quando você é membro?</small>
        <div class="campo-membro-datas">
            <select name="IDF_MEMBRO_MES" class="form-select custom-input" aria-label="Mês em que se tornou membro" <?php echo $cm_membro === '1' ? 'required' : ''; ?>>
                <option value="">Mês</option>
                <?php foreach (fiel_membro_meses() as $num => $nome_mes) { ?>
                    <option value="<?php echo $num; ?>" <?php echo $cm_mes === $num ? 'selected' : ''; ?>><?php echo ucfirst($nome_mes); ?></option>
                <?php } ?>
            </select>
            <select name="IDF_MEMBRO_ANO" class="form-select custom-input" aria-label="Ano em que se tornou membro" <?php echo $cm_membro === '1' ? 'required' : ''; ?>>
                <option value="">Ano</option>
                <?php for ($a = $cm_ano_atual; $a >= 1940; $a--) { ?>
                    <option value="<?php echo $a; ?>" <?php echo $cm_ano === $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
</fieldset>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Mês/ano só aparecem (e só são obrigatórios) para quem é membro.
    (function () {
        var campo = document.getElementById(<?php echo json_encode($cm_id); ?>);
        if (!campo) return;
        var desde = campo.querySelector('.campo-membro-desde');
        var selects = desde.querySelectorAll('select');
        campo.querySelectorAll('input[name="IDF_MEMBRO"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var ehMembro = radio.value === '1' && radio.checked;
                desde.hidden = !ehMembro;
                selects.forEach(function (s) { s.required = ehMembro; });
            });
        });
    })();
</script>
