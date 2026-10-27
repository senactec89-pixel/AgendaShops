<?php use AgendaShops\Http\Forms as F; use AgendaShops\Modules\Bookings\BookingService as B; ?>
<main class="page-shell"><h1>Solicitações e agendamentos</h1><p>Uma solicitação só está confirmada após o aceite do parceiro.</p>
<?php if (!$page['bookings']): ?><section class="card"><p>Ainda não há agendamentos.</p><a class="button button-primary" href="/explorar">Explorar serviços</a></section><?php endif; ?>
<?php foreach ($page['bookings'] as $booking): $isCustomer=$booking['customer_id'] === $user['id']; ?>
<article class="card booking"><span class="badge"><?= e(B::LABELS[$booking['status']] ?? $booking['status']) ?></span><h2><?= e($booking['service_name']) ?></h2>
<p><?= e($booking['business_name']) ?> · <?= e($booking['professional_name']) ?></p><p>Cliente: <?= e($booking['customer_name']) ?></p>
<p><strong><?= e(localDate($booking['starts_at'],$booking['timezone'])) ?></strong> · <?= (int)$booking['duration_minutes'] ?> min · <?= money((int)$booking['price_cents']) ?></p>
<p>Fuso: <?= e($booking['timezone']) ?></p><p>Política: <?= e($booking['policy']) ?></p>
<?php if ($booking['expires_at']): ?><p>Prazo de resposta: <?= e(localDate($booking['expires_at'],$booking['timezone'])) ?></p><?php endif; ?>
<a class="button button-secondary" href="/mensagens?conversa=<?= e($booking['conversation']) ?>">Conversar com <?= $isCustomer ? 'o parceiro' : 'o cliente' ?></a>
<?php $targets = $isCustomer ? (in_array($booking['status'],['PENDING_PARTNER_APPROVAL','CONFIRMED'],true) ? ['CANCELED_BY_CUSTOMER'=>'Cancelar solicitação ou agendamento'] : []) : match($booking['status']) { 'PENDING_PARTNER_APPROVAL'=>['CONFIRMED'=>'Aceitar solicitação','REJECTED_BY_PARTNER'=>'Recusar solicitação'], 'CONFIRMED'=>['IN_PROGRESS'=>'Iniciar atendimento','NO_SHOW'=>'Registrar não comparecimento','CANCELED_BY_PARTNER'=>'Cancelar agendamento'], 'IN_PROGRESS'=>['COMPLETED'=>'Concluir atendimento'], default=>[] }; ?>
<?php if ($targets): ?>
<?= F::open('transition',['booking_id'=>$booking['id']],'/agendamentos') . F::select('status','Ação',$targets) . F::input('reason','Motivo ou observação (opcional)','','text','maxlength="1000"') . F::close('Aplicar ação') ?>
<?php endif; ?>
<?php if ($isCustomer && in_array($booking['status'],['PENDING_PARTNER_APPROVAL','CONFIRMED'],true)): ?>
<details><summary>Solicitar reagendamento</summary><p>O novo horário depende de outro aceite do parceiro. Se a solicitação for criada, o horário anterior será liberado.</p>
<?= F::open('reschedule',['booking_id'=>$booking['id'],'professional_id'=>$booking['professional_id'],'request_key'=>\AgendaShops\Infrastructure\Database::id()],'/agendamentos') . F::input('starts_at','Novo horário (fuso do estabelecimento)','','datetime-local','required') . F::close('Solicitar novo horário') ?></details>
<?php endif; ?>
<?php if ($isCustomer && $booking['status'] === 'COMPLETED' && !$booking['reviewed']): ?>
<details><summary>Avaliar atendimento</summary><?= F::open('review',['booking_id'=>$booking['id']],'/agendamentos') . F::select('rating','Nota',['5'=>'5 — Excelente','4'=>'4 — Muito bom','3'=>'3 — Bom','2'=>'2 — Regular','1'=>'1 — Ruim']) . F::area('body','Como foi sua experiência?','','required minlength="3" maxlength="2000"') . F::close('Publicar avaliação') ?></details>
<?php endif; ?>
<details><summary>Histórico</summary><ol><?php foreach ($booking['history'] as $history): ?><li><?= e(B::LABELS[$history['status']] ?? $history['status']) ?> · <?= e(localDate($history['created_at'],$booking['timezone'])) ?><p><?= e($history['reason']) ?></p></li><?php endforeach; ?></ol></details>
</article><?php endforeach; ?></main>
