<?php
// Partial: escolha da filial (lista de Admin → Filiais + "Nenhuma filial").
// Usado no cadastro (register.php) e no perfil (perfil.php). Ver "FILIAIS"
// em php/conexao.php.
// Uso:
//   $campo_filial = array(
//       'filiais' => filiais_listar($conn),
//       'escolha' => '12' | FILIAL_NENHUMA | '',   // o que vem marcado
//       'atual' => '12' | FILIAL_NENHUMA | '',     // o que o fiel já tem (filial desativada só aparece se for a dele)
//   );
//   include __DIR__ . '/includes/campo_filial.php';
// Envia IDF_FILIAL_ESCOLHA.

$cf = isset($campo_filial) && is_array($campo_filial) ? $campo_filial : array();
$cf_filiais = $cf['filiais'] ?? array();
$cf_escolha = (string) ($cf['escolha'] ?? '');
$cf_atual = (string) ($cf['atual'] ?? '');
$cf_id = 'filial' . substr(md5(uniqid('', true)), 0, 6);
?>
<div class="campo-filial">
    <label class="visually-hidden" for="<?php echo $cf_id; ?>">Filial</label>
    <select name="IDF_FILIAL_ESCOLHA" id="<?php echo $cf_id; ?>" class="form-select custom-input" required aria-describedby="<?php echo $cf_id; ?>Ajuda">
        <option value="" <?php echo $cf_escolha === '' ? 'selected' : ''; ?> disabled>Selecione sua filial</option>
        <optgroup label="Filiais">
            <?php foreach ($cf_filiais as $f) {
                if (!$f['ativa'] && (string) $f['id'] !== $cf_atual) {
                    continue;
                }
            ?>
                <option value="<?php echo (int) $f['id']; ?>" <?php echo (string) $f['id'] === $cf_escolha ? 'selected' : ''; ?>><?php echo htmlspecialchars($f['nome']); ?></option>
            <?php } ?>
        </optgroup>
        <option value="<?php echo FILIAL_NENHUMA; ?>" <?php echo $cf_escolha === FILIAL_NENHUMA ? 'selected' : ''; ?>><?php echo FILIAL_NENHUMA_ROTULO; ?> (não frequento a igreja)</option>
    </select>
    <small id="<?php echo $cf_id; ?>Ajuda" class="campo-filial-ajuda">Não frequenta a igreja, mas gostou da plataforma? Escolha "<?php echo FILIAL_NENHUMA_ROTULO; ?>".</small>
</div>
