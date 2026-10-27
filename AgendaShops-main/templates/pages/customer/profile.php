<?php use AgendaShops\Http\Forms as F; ?>
<main class="page-shell"><h1>Olá, <?= e($user['name']) ?></h1><div class="two-columns"><section class="card"><h2>Meus dados</h2><p><?= e($user['email']) ?></p>
<?= F::open('profile',[],'/perfil') . F::input('name','Nome',$user['name'],'text','required maxlength="100"') . F::input('phone','Telefone com DDD',$user['phone'] ?? '','tel','required') . F::close() ?>
<?php if (\AgendaShops\Modules\Identity\GoogleAuth::configured()): ?><h3>Login Google</h3><?= F::open('google',[],'/perfil') . F::close('Vincular minha conta Google') ?><?php endif; ?>
<p><a href="/recuperar">Definir uma nova senha por e-mail</a></p>
<details><summary>Excluir minha conta</summary><p>A conta será anonimizada. Histórico de atendimento é preservado. Cancele os agendamentos ativos antes.</p><?= F::open('delete-account',[],'/') . F::input('confirm','Digite EXCLUIR para confirmar','','text','required') . F::close('Excluir conta') ?></details></section>
<section class="card"><h2>Notificações</h2><?= F::open('notifications-read',[],'/perfil') . F::close('Marcar todas como lidas') ?>
<?php if (!$page['notifications']): ?><p>Nenhuma notificação por enquanto.</p><?php endif; ?>
<?php foreach ($page['notifications'] as $notice): ?><article class="notification"><p><?= $notice['read_at'] ? '' : '● ' ?><a href="<?= e($notice['link']) ?>"><?= e($notice['body']) ?></a></p><small><?= e(localDate($notice['created_at'])) ?></small></article><?php endforeach; ?></section></div></main>
