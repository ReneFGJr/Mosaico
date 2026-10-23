<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div><p class="eyebrow">Minha conta</p><h1>Meu perfil</h1></div>
</header>
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
            <p id="password-help">Use pelo menos 12 caracteres. Limite de 72 bytes (letras acentuadas e símbolos podem ocupar mais de um byte).</p>
            <label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>Nova senha<input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="password-help" required></label>
            <label>Confirmar nova senha<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required></label>
            <button type="submit" class="button">Salvar nova senha</button>
        </form>
    </details>
</section>
<?= $this->endSection() ?>
