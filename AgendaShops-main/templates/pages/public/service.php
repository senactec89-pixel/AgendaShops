<?php use AgendaShops\Http\Forms as F; ?>
<main class="page-shell"><a href="/explorar">← Explorar serviços</a>
<?php $service = $page['service']; ?>
<section class="card"><span class="pill"><?= e($service['category_name']) ?></span><h1><?= e($service['name']) ?></h1>
<?php if ($service['image']): ?><img class="detail-image" src="/media/<?= e($service['image']) ?>" alt="<?= e($service['name']) ?>"><?php endif; ?>
<p><a href="/estabelecimentos/<?= e($service['business_slug']) ?>"><?= e($service['business_name']) ?></a> · <?= e($service['city']) ?></p>
<p><?= e($service['description']) ?></p><p><strong><?= money((int)$service['effective_price']) ?></strong> · <?= (int)$service['duration_minutes'] ?> minutos</p>
<h2>Política do estabelecimento</h2><p><?= e($service['policy']) ?></p>
<?= F::open('compare',['service_id'=>$service['id']],'/comparar') . F::close('Adicionar ou remover da comparação') ?>
</section>
<section class="card"><h2>Escolha seu horário</h2><p>Horários no fuso <?= e($service['timezone']) ?>. A solicitação depende do aceite do parceiro. Preço e duração serão revalidados no aceite.</p>
<form method="get"><?= F::input('date','Data',$page['date'],'date','required') ?><button type="submit">Consultar disponibilidade</button></form>
<?php if (!$user): ?><p><a class="button button-primary" href="/entrar">Entre para solicitar um horário</a></p><?php endif; ?>
<?php if (!$page['slots']): ?><p>Nenhum horário disponível nesta data. Consulte outra data.</p><?php endif; ?>
<?php if ($user && $page['slots']): ?>
<?= F::open('booking',['service_id'=>$service['id'],'request_key'=>\AgendaShops\Infrastructure\Database::id()],'/agendamentos') ?>
<fieldset><legend>Profissional e horário</legend><div class="slots">
<?php foreach ($page['slots'] as $slot): ?>
<label class="slot"><input type="radio" name="slot" value="<?= e($slot['professional_id'] . '|' . $slot['local_start']) ?>" required> <?= e($slot['time'] . ' · ' . $slot['professional_name']) ?></label>
<?php endforeach; ?></div></fieldset>
<?= F::close('Solicitar horário') ?>
<?php elseif ($page['slots']): ?><p><?= count($page['slots']) ?> horários disponíveis para consulta após entrar.</p><?php endif; ?>
</section></main>
