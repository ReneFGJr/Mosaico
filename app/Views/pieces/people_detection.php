<section class="image-description people-detection" aria-labelledby="people-detection-title">
    <h2 id="people-detection-title">Pessoas na imagem</h2>
    <?php if ($canManageKeywords): ?>
        <form method="post" action="<?= site_url('pecas/' . $piece['id'] . '/detectar-pessoas') ?>" data-describe-image data-progress-label="Detectando pessoas…" data-progress-status="Detectando pessoas na imagem. Aguarde…">
            <?= csrf_field() ?>
            <input type="hidden" name="source_file_id" value="<?= esc($previewFile['id']) ?>">
            <button type="submit" class="button">Detectar pessoas</button>
        </form>
    <?php endif ?>
    <?php if ($peopleDetection): ?>
        <?php $detectionResult = json_decode($peopleDetection['result_json'], true, 16, JSON_THROW_ON_ERROR); ?>
        <?php $manualNames = json_decode($peopleDetection['manual_names'] ?? '[]', true, 16, JSON_THROW_ON_ERROR); ?>
        <p><strong>Quantidade estimada:</strong> <?= $detectionResult['count_estimate'] === null ? 'Não foi possível estimar' : esc($detectionResult['count_estimate']) ?></p>
        <p class="form-help">Estimativa e descrições geradas por IA. Revise o resultado. São listadas até 20 pessoas distinguíveis.</p>
        <?php if ($detectionResult['notes'] !== ''): ?><p class="image-description-text"><?= esc($detectionResult['notes']) ?></p><?php endif ?>
        <?php if ($detectionResult['people']): ?>
            <?php if ($canManageKeywords): ?>
                <form method="post" action="<?= site_url('pecas/' . $piece['id'] . '/pessoas/nomes') ?>" class="stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="source_file_id" value="<?= esc($previewFile['id']) ?>">
                    <input type="hidden" name="detection_id" value="<?= esc($peopleDetection['id']) ?>">
                    <p class="form-help">Selecione uma pessoa do controle de autoridade da comunidade ou cadastre um novo nome. O qualificador é opcional e ajuda a distinguir pessoas com o mesmo nome. Uma nova detecção gera outra lista para associação.</p>
            <?php endif ?>
            <ol class="detected-people">
                <?php foreach ($detectionResult['people'] as $index => $person): ?>
                    <?php $authorityLink = $personAuthorityLinks[$index] ?? null; ?>
                    <li>
                        <h3>Pessoa <?= esc($index + 1) ?></h3>
                        <dl>
                            <div><dt>Posição</dt><dd><?= esc($person['position']) ?></dd></div>
                            <div><dt>Roupas</dt><dd><?= esc($person['clothing']) ?></dd></div>
                            <div><dt>Ação</dt><dd><?= esc($person['activity']) ?></dd></div>
                        </dl>
                        <?php if ($canManageKeywords): ?>
                            <label>Autoridade da comunidade — pessoa <?= esc($index + 1) ?>
                                <select name="authority_ids[<?= esc($index) ?>]">
                                    <option value="">Sem associação / cadastrar novo nome</option>
                                    <?php foreach ($personAuthorities as $authority): ?>
                                        <option value="<?= esc($authority['id']) ?>" <?= ($authorityLink['id'] ?? '') === $authority['id'] ? 'selected' : '' ?>><?= esc($authority['preferred_name'] . ($authority['qualifier'] !== '' ? ' — ' . $authority['qualifier'] : '')) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </label>
                            <label>Novo nome<input type="text" name="names[<?= esc($index) ?>]" value="<?= esc($authorityLink ? '' : ($manualNames[$index] ?? '')) ?>" maxlength="190" autocomplete="off" placeholder="Preencha apenas para cadastrar"></label>
                            <label>Qualificador do novo nome<input type="text" name="qualifiers[<?= esc($index) ?>]" maxlength="190" placeholder="Ex.: instituição ou área de atuação (opcional)"></label>
                        <?php elseif ($authorityLink): ?>
                            <p><strong>Nome associado:</strong> <?= esc($authorityLink['preferred_name'] . ($authorityLink['qualifier'] !== '' ? ' — ' . $authorityLink['qualifier'] : '')) ?></p>
                        <?php elseif (($manualNames[$index] ?? '') !== ''): ?>
                            <p><strong>Nome associado:</strong> <?= esc($manualNames[$index]) ?></p>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ol>
            <?php if ($canManageKeywords): ?>
                    <button type="submit" class="button">Salvar nomes</button>
                </form>
            <?php endif ?>
        <?php elseif ($detectionResult['count_estimate'] === 0): ?>
            <p>Nenhuma pessoa detectada.</p>
        <?php else: ?>
            <p>Não foi possível distinguir pessoas individualmente nesta imagem.</p>
        <?php endif ?>
    <?php else: ?>
        <p class="muted">A detecção de pessoas ainda não foi realizada.</p>
    <?php endif ?>
</section>
