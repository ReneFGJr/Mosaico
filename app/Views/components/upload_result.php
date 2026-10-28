<dialog class="upload-result" data-upload-result aria-labelledby="upload-result-title">
    <header><h2 id="upload-result-title"><?= $result['errors'] ? 'Envio finalizado com pendências' : 'Envio concluído' ?></h2></header>
    <div class="upload-result-body" tabindex="0" role="region" aria-label="Resultado do envio">
        <p><?= (int) $result['created'] ?> <?= $result['created'] === 1 ? 'arquivo enviado com sucesso.' : 'arquivos enviados com sucesso.' ?></p>
        <?php if ($result['restored']): ?>
            <h3><?= (int) $result['restored'] ?> <?= $result['restored'] === 1 ? 'arquivo já existe na comunidade' : 'arquivos já existem na comunidade' ?></h3>
            <p>Encontramos os mesmos conteúdos no acervo. As peças existentes foram restauradas para rascunho (Draft) e estão disponíveis nesta coleção. Nenhuma cópia extra foi criada.</p>
            <ul class="upload-result-files">
                <?php foreach ($result['duplicates'] as $file): ?>
                    <li><strong><?= esc($file['submitted']) ?></strong><span>Mesmo conteúdo de <?= esc($file['existing']) ?>.</span></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
        <?php if ($result['errors']): ?>
            <h3>Arquivos que não puderam ser enviados</h3>
            <p>Confira os motivos abaixo e tente enviar esses arquivos novamente.</p>
            <ul class="upload-result-files"><?php foreach ($result['errors'] as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul>
        <?php endif ?>
    </div>
    <form method="dialog" class="upload-result-actions"><button class="button" autofocus>Fechar</button></form>
</dialog>
