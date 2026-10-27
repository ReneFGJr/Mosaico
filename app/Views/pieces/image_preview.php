<a class="image-fullscreen-trigger" href="<?= site_url('pecas/' . $piece['id'] . '/preview') ?>" target="_blank" rel="noopener" aria-label="Abrir imagem em tela cheia" aria-haspopup="dialog" aria-controls="piece-image-fullscreen" data-image-fullscreen-open>
    <img src="<?= site_url('pecas/' . $piece['id'] . '/preview') ?>" alt="Pré-visualização de <?= esc($piece['title']) ?>">
</a>
<dialog id="piece-image-fullscreen" class="image-fullscreen" aria-label="Imagem em tela cheia" data-image-fullscreen>
    <form method="dialog" class="image-fullscreen-controls">
        <button type="button" class="button" data-image-zoom-out aria-label="Diminuir zoom">−</button>
        <output data-image-zoom-level aria-label="Nível de zoom">100%</output>
        <button type="button" class="button" data-image-zoom-in aria-label="Aumentar zoom">+</button>
        <button type="button" class="button" data-image-zoom-fit>Ajustar</button>
        <button type="submit" class="button" autofocus aria-label="Fechar imagem em tela cheia">Fechar ×</button>
    </form>
    <div class="image-fullscreen-viewport" data-image-zoom-viewport title="Use a roda do mouse para dar zoom. Clique duas vezes para fechar.">
        <div class="image-fullscreen-stage" data-image-zoom-stage>
            <img src="<?= site_url('pecas/' . $piece['id'] . '/preview') ?>" alt="<?= esc($piece['title']) ?>" loading="lazy">
        </div>
    </div>
</dialog>
<script src="<?= base_url('assets/js/image-fullscreen.js') ?>" defer></script>
