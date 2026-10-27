<?php use AgendaShops\Http\Forms as F; $business = $page['business']; ?>
<main class="page-shell"><section class="card"><span class="pill">Estabelecimento</span><h1><?= e($business['name']) ?></h1>
<p><?= e($business['description']) ?></p><p><?= e($business['address'] . ' · ' . $business['city']) ?></p><p>Contato: <?= e($business['phone']) ?></p>
<p>Funcionamento: <?= e($business['opens'] . ' às ' . $business['closes']) ?> · <?= e($business['timezone']) ?></p>
<p>Dias: <?= e(strtr($business['weekdays'],['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','7'=>'Dom'])) ?></p>
<h2>Equipe</h2><p><?= e(implode(', ',array_column($page['professionals'],'name'))) ?: 'Equipe em atualização.' ?></p>
<h2>Política de atendimento</h2><p><?= e($business['policy']) ?></p>
<?php if ($user): ?>
<?= F::open('favorite',['business_id'=>$business['id']],'/estabelecimentos/' . $business['slug']) . F::close('Adicionar ou remover dos favoritos') ?>
<details><summary>Denunciar estabelecimento</summary><?= F::open('report',['target_type'=>'business','target_id'=>$business['id']],'/estabelecimentos/' . $business['slug']) . F::area('reason','Motivo') . F::close('Enviar denúncia') ?></details>
<?php endif; ?></section>
<h2>Serviços</h2><section class="service-grid"><?php foreach ($page['services'] as $service): require dirname(__DIR__, 2) . '/partials/service-card.php'; endforeach; ?></section>
<h2>Avaliações de atendimentos concluídos</h2>
<?php if (!$page['reviews']): ?><p>Ainda não há avaliações.</p><?php endif; ?>
<?php foreach ($page['reviews'] as $review): ?><article class="card"><h3><?= (int)$review['rating'] ?> / 5 · <?= e($review['name']) ?></h3><p><?= e($review['body']) ?></p>
<?php if ($user): ?><details><summary>Denunciar avaliação</summary><?= F::open('report',['target_type'=>'review','target_id'=>$review['id']],'/estabelecimentos/' . $business['slug']) . F::area('reason','Motivo') . F::close('Enviar denúncia') ?></details><?php endif; ?></article><?php endforeach; ?>
</main>
