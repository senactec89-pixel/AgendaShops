<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($page['title']) ? $page['title'] . ' | AgendaShops' : 'AgendaShops') ?></title>
    <meta name="description" content="Encontre e agende serviços de beleza e bem-estar.">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#content">Pular para o conteúdo</a>
<header class="site-header">
    <a class="brand" href="/" aria-label="AgendaShops — início"><span class="brand-mark" aria-hidden="true">A</span>AgendaShops</a>
    <nav aria-label="Navegação principal">
        <a href="/explorar">Explorar</a><a href="/comparar">Comparar (<?= count($_SESSION['compare'] ?? []) ?>)</a>
        <?php if ($user): ?>
            <a href="/agendamentos">Agenda</a><a href="/mensagens">Mensagens</a><a href="/favoritos">Favoritos</a><a href="/parceiro">Parceiro</a>
            <?php if ($user['role'] === 'ADMIN'): ?><a href="/admin">Admin</a><?php endif; ?>
            <a href="/perfil">Minha conta</a>
            <?= \AgendaShops\Http\Forms::open('logout',[],'/') . \AgendaShops\Http\Forms::close('Sair') ?>
        <?php else: ?><a href="/entrar">Entrar</a><a class="nav-cta" href="/cadastro">Criar conta</a><?php endif; ?>
    </nav>
</header>
<?php if ($flash): ?><div class="notice" role="status"><?= e($flash) ?></div><?php endif; ?>
<div id="content"><?= $content ?></div>
<footer><p>AgendaShops · Beleza e bem-estar, no seu tempo.</p><a href="/termos">Termos de uso</a> · <a href="/privacidade">Privacidade</a></footer>
</body>
</html>
