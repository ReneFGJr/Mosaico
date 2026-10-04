<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="auth-card">
    <p class="eyebrow">Acesso seguro</p><h1>Bem-vindo ao MOSAICO</h1>
    <p>Entre para organizar, preservar e acompanhar os ativos digitais.</p>
    <form method="post" action="<?= site_url('login') ?>" class="stack"><?= csrf_field() ?>
        <label>E-mail<input type="email" name="email" value="<?= esc(old('email')) ?>" autocomplete="username" required autofocus></label>
        <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit" class="button">Entrar</button>
    </form>
</section>
<?= $this->endSection() ?>
