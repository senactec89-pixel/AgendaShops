<?php use AgendaShops\Http\Forms as CardForms; ?>
<article class="card service-card">
<?php if ($service['image']): ?><img src="/media/<?= e($service['image']) ?>" alt="<?= e($service['name']) ?>" loading="lazy"><?php endif; ?>
<span class="pill"><?= e($service['category_name']) ?></span><h2><a href="/servicos/<?= e($service['id']) ?>"><?= e($service['name']) ?></a></h2>
<p><a href="/estabelecimentos/<?= e($service['business_slug']) ?>"><?= e($service['business_name']) ?></a> · <?= e($service['city']) ?></p>
<p><?= e($service['description']) ?></p><p><strong><?= money((int)$service['effective_price']) ?></strong> · <?= (int)$service['duration_minutes'] ?> min
<?php if ($service['discount_percent']): ?><span class="badge"><?= (int)$service['discount_percent'] ?>% de desconto</span><?php endif; ?></p>
<?php if (isset($service['rating'])): ?><p><?= number_format((float)$service['rating'],1,',','.') ?> / 5 · <?= (int)$service['review_count'] ?> avaliações<?php if ($service['distance'] !== null): ?> · <?= e((string)$service['distance']) ?> km<?php endif; ?></p><?php endif; ?>
<a class="button button-primary" href="/servicos/<?= e($service['id']) ?>">Ver horários</a>
<?php if (array_key_exists('next_slot',$service)): ?><p>Próxima disponibilidade: <?= $service['next_slot'] ? e(localDate($service['next_slot']['starts_at'],$service['timezone'])) : 'Consulte outras datas' ?></p><?php endif; ?>
<?= CardForms::open('compare',['service_id'=>$service['id']],'/comparar') . CardForms::close(in_array($service['id'],$_SESSION['compare'] ?? [],true) ? 'Remover da comparação' : 'Comparar serviço') ?>
</article>
