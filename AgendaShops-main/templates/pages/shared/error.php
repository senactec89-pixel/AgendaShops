<main class="page-shell"><section class="card"><span class="pill"><?= (int)$page['code'] ?></span>
<h1><?= e($page['title']) ?></h1><p role="alert"><?= e($page['message']) ?></p>
<?php if ($page['code'] === 401): ?><a class="button button-primary" href="/entrar">Entrar</a><?php endif; ?>
<a class="button button-secondary" href="/explorar">Explorar serviços</a><button type="button" data-back>Voltar e corrigir</button></section></main>
