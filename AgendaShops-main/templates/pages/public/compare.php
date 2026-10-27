<?php use AgendaShops\Http\Forms as F; ?>
<main class="page-shell"><h1>Compare antes de solicitar</h1><p>Selecione até três serviços da mesma categoria. A disponibilidade será revalidada ao solicitar e no aceite.</p>
<?php if (!$page['services']): ?><section class="card"><p>Nenhum serviço selecionado.</p><a class="button button-primary" href="/explorar">Encontrar serviços</a></section><?php else: ?>
<div class="table-scroll"><table><caption>Comparação de serviços</caption><thead><tr><th>Critério</th><?php foreach ($page['services'] as $s): ?><th><?= e($s['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach (['business_name'=>'Estabelecimento','city'=>'Localização','duration_minutes'=>'Duração (minutos)','category_name'=>'Categoria'] as $key=>$label): ?><tr><th scope="row"><?= e($label) ?></th><?php foreach ($page['services'] as $s): ?><td><?= e((string)$s[$key]) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<tr><th scope="row">Preço</th><?php foreach ($page['services'] as $s): ?><td><?= money((int)$s['effective_price']) ?></td><?php endforeach; ?></tr>
<tr><th scope="row">Promoção vigente</th><?php foreach ($page['services'] as $s): ?><td><?= (int)$s['discount_percent'] ?>%</td><?php endforeach; ?></tr>
<tr><th scope="row">Avaliação</th><?php foreach ($page['services'] as $s): ?><td><?= $s['review_count'] ? number_format($s['rating'],1,',','.') . ' / 5 (' . $s['review_count'] . ')' : 'Sem avaliações' ?></td><?php endforeach; ?></tr>
<tr><th scope="row">Distância aproximada</th><?php foreach ($page['services'] as $s): ?><td><?= $s['distance'] !== null ? e((string)$s['distance']) . ' km' : 'Ative a localização na busca para consultar' ?></td><?php endforeach; ?></tr>
<tr><th scope="row">Profissionais</th><?php foreach ($page['services'] as $s): ?><td><?= e(implode(', ',array_column($s['professionals'],'name'))) ?></td><?php endforeach; ?></tr>
<tr><th scope="row">Próxima disponibilidade (7 dias)</th><?php foreach ($page['services'] as $s): ?><td><?= $s['next_slot'] ? e(localDate($s['next_slot']['starts_at'],$s['timezone'])) : 'Consulte outras datas' ?></td><?php endforeach; ?></tr>
<tr><th scope="row">Escolher</th><?php foreach ($page['services'] as $s): ?><td><a class="button button-primary" href="/servicos/<?= e($s['id']) ?>">Ver horários</a><?= F::open('compare',['service_id'=>$s['id']],'/comparar') . F::close('Remover') ?></td><?php endforeach; ?></tr>
</tbody></table></div><?php endif; ?></main>
