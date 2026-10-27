<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div><p class="eyebrow">Minha conta</p><h1>Meu perfil</h1></div>
</header>
<div class="profile-layout">
<section class="form-card profile-card" aria-label="Dados do usuário">
    <h2>Dados do usuário</h2>
    <dl class="profile-data">
        <div><dt>Nome</dt><dd><?= esc($user['display_name']) ?></dd></div>
        <div><dt>E-mail</dt><dd><?= esc($user['email']) ?></dd></div>
    </dl>
    <details class="password-change" <?= session('password_form_open') ? 'open' : '' ?>>
        <summary class="button">Trocar senha</summary>
        <form method="post" action="<?= site_url('profile/password') ?>" class="stack">
            <?= csrf_field() ?>
            <p id="password-help">Use pelo menos 8 caracteres, incluindo letras e números. Limite de 72 bytes (letras acentuadas e símbolos podem ocupar mais de um byte).</p>
            <label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>Nova senha<input type="password" name="new_password" autocomplete="new-password" minlength="8" maxlength="72" pattern="(?=.*\p{L})(?=.*[0-9]).*" title="Use pelo menos 8 caracteres, incluindo letras e números." aria-describedby="password-help" required></label>
            <label>Confirmar nova senha<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="72" required></label>
            <button type="submit" class="button">Salvar nova senha</button>
        </form>
    </details>
</section>
<section class="form-card profile-card" aria-labelledby="profile-links-title">
    <h2 id="profile-links-title">Comunidades e coleções vinculadas</h2>
    <p>As coleções abaixo pertencem às comunidades às quais você está vinculado.</p>
    <?php if ($communities === []): ?>
        <p>Você ainda não está vinculado a nenhuma comunidade.</p>
    <?php else: ?>
        <?php foreach ($communities as $community): ?>
            <div class="profile-community">
                <h3><a href="<?= site_url('comunidades/' . $community['id']) ?>"><?= esc($community['name']) ?></a></h3>
                <?php $linkedCollections = array_filter($collections, static fn (array $collection): bool => $collection['project_id'] === $community['id']); ?>
                <?php if ($linkedCollections === []): ?>
                    <p>Nenhuma coleção vinculada a esta comunidade.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($linkedCollections as $collection): ?>
                            <li><a href="<?= site_url('colecoes/' . $collection['id']) ?>"><?= esc($collection['name']) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</section>
</div>
<?= $this->endSection() ?>
