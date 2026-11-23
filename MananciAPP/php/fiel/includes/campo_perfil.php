<?php
// Partial: campos de perfil da T11 — WhatsApp (obrigatório), data de
// nascimento e contexto familiar (opcionais). Usado no cadastro
// (register.php, só identidade) e no perfil (perfil.php, tudo). Ver
// fiel_whatsapp_normalizar(), fiel_nascimento_validar() e
// fiel_contexto_validar() em conexao.php.
// Uso:
//   $campo_perfil = array(
//       'whatsapp' => string, 'nascimento' => 'AAAA-MM-DD'|'',
//       'estado_civil' => string, 'tem_filhos' => '1'|'0'|'', 'cidade' => string, 'uf' => string,
//       'com_nascimento' => bool,  // colunas da seção 49 existem
//       'com_contexto' => bool,    // mostra o bloco "Contexto familiar"
//   );
//   include __DIR__ . '/includes/campo_perfil.php';
// Envia IDF_WHATSAPP, IDF_NASCIMENTO, IDF_ESTADO_CIVIL, IDF_TEM_FILHOS,
// IDF_CIDADE e IDF_UF.

$cp = isset($campo_perfil) && is_array($campo_perfil) ? $campo_perfil : array();
$cp_id = 'perfil' . substr(md5(uniqid('', true)), 0, 6);
$cp_whatsapp = (string) ($cp['whatsapp'] ?? '');
$cp_whatsapp_exibir = fiel_whatsapp_normalizar($cp_whatsapp) ? fiel_whatsapp_formatar($cp_whatsapp) : $cp_whatsapp;
$cp_com_nascimento = !empty($cp['com_nascimento']);
// No cadastro a data é obrigatória: é ela que diz se o menor precisa do
// responsável (LGPD art. 14). Em branco, o menor passava sem ninguém.
$cp_nascimento_obrigatorio = !empty($cp['nascimento_obrigatorio']);
$cp_com_contexto = !empty($cp['com_contexto']);
$cp_filhos = (string) ($cp['tem_filhos'] ?? '');
?>
<div class="campo-perfil" id="<?php echo $cp_id; ?>">
    <?php if ($cp_com_contexto) { ?>
        <div class="campo-perfil-grupo-titulo">Identidade</div>
    <?php } ?>
    <div class="auth-form-grid auth-form-grid--two">
        <div>
            <label class="campo-perfil-rotulo" for="<?php echo $cp_id; ?>Whats">WhatsApp</label>
            <input type="tel" id="<?php echo $cp_id; ?>Whats" name="IDF_WHATSAPP" class="form-control custom-input campo-whatsapp" placeholder="(11) 9 9999-9999" value="<?php echo htmlspecialchars($cp_whatsapp_exibir); ?>" inputmode="numeric" autocomplete="tel-national" maxlength="20" pattern="\D*(5\D*5\D*)?([1-9]\D*){2}9(\D*\d){8}\D*" title="Celular com DDD, ex.: (11) 9 9999-9999" required>
        </div>
        <?php if ($cp_com_nascimento) { ?>
            <div>
                <label class="campo-perfil-rotulo" for="<?php echo $cp_id; ?>Nasc">Data de nascimento<?php if (!$cp_nascimento_obrigatorio) { ?> <small>(opcional)</small><?php } ?></label>
                <input type="date" id="<?php echo $cp_id; ?>Nasc" name="IDF_NASCIMENTO" class="form-control custom-input" value="<?php echo htmlspecialchars((string) ($cp['nascimento'] ?? '')); ?>" min="1900-01-01" max="<?php echo date('Y-m-d'); ?>" autocomplete="bday"<?php echo $cp_nascimento_obrigatorio ? ' required' : ''; ?>>
            </div>
        <?php } ?>
    </div>

    <?php if ($cp_com_contexto) { ?>
        <div class="campo-perfil-grupo-titulo">Contexto familiar <small class="text-muted text-lowercase fw-normal">(opcional)</small></div>
        <div class="auth-form-grid auth-form-grid--two">
            <div>
                <label class="campo-perfil-rotulo" for="<?php echo $cp_id; ?>Civil">Estado civil</label>
                <select id="<?php echo $cp_id; ?>Civil" name="IDF_ESTADO_CIVIL" class="form-select custom-input">
                    <option value="">Prefiro não informar</option>
                    <?php foreach (fiel_estados_civis() as $codigo => $rotulo) { ?>
                        <option value="<?php echo $codigo; ?>" <?php echo ($cp['estado_civil'] ?? '') === $codigo ? 'selected' : ''; ?>><?php echo htmlspecialchars($rotulo); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="campo-perfil-rotulo" for="<?php echo $cp_id; ?>Filhos">Tem filhos?</label>
                <select id="<?php echo $cp_id; ?>Filhos" name="IDF_TEM_FILHOS" class="form-select custom-input">
                    <option value="">Prefiro não informar</option>
                    <option value="1" <?php echo $cp_filhos === '1' ? 'selected' : ''; ?>>Sim</option>
                    <option value="0" <?php echo $cp_filhos === '0' ? 'selected' : ''; ?>>Não</option>
                </select>
            </div>
            <div>
                <label class="campo-perfil-rotulo" for="<?php echo $cp_id; ?>Cidade">Cidade</label>
                <input type="text" id="<?php echo $cp_id; ?>Cidade" name="IDF_CIDADE" class="form-control custom-input" value="<?php echo htmlspecialchars((string) ($cp['cidade'] ?? '')); ?>" maxlength="100" autocomplete="address-level2" pattern="[^0-9<>]*" title="Somente o nome da cidade, sem números">
            </div>
            <div>
                <label class="campo-perfil-rotulo" for="<?php echo $cp_id; ?>Uf">Estado</label>
                <select id="<?php echo $cp_id; ?>Uf" name="IDF_UF" class="form-select custom-input" autocomplete="address-level1">
                    <option value="">—</option>
                    <?php foreach (fiel_ufs() as $uf) { ?>
                        <option value="<?php echo $uf; ?>" <?php echo ($cp['uf'] ?? '') === $uf ? 'selected' : ''; ?>><?php echo $uf; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    <?php } ?>
</div>
<?php if (empty($GLOBALS['campo_perfil_js_incluido'])) { $GLOBALS['campo_perfil_js_incluido'] = true; ?>
<script nonce="<?php echo site_csp_nonce(); ?>">
    // Máscara do WhatsApp: "(11) 9 9999-9999" enquanto digita. Aceita colar
    // com +55. O servidor normaliza de novo (só os dígitos importam).
    (function () {
        function formatar(valor) {
            var d = valor.replace(/\D/g, '');
            if (d.length > 11 && d.indexOf('55') === 0) d = d.slice(2);
            d = d.slice(0, 11);
            if (d.length <= 2) return d.length ? '(' + d : '';
            var s = '(' + d.slice(0, 2) + ') ' + d.slice(2, 3);
            if (d.length > 3) s += ' ' + d.slice(3, 7);
            if (d.length > 7) s += '-' + d.slice(7);
            return s;
        }
        document.querySelectorAll('input.campo-whatsapp').forEach(function (input) {
            input.addEventListener('input', function () {
                var antes = input.value;
                var depois = formatar(antes);
                if (antes !== depois) input.value = depois;
            });
        });
    })();
</script>
<?php } ?>
