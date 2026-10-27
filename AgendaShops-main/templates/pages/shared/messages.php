<?php use AgendaShops\Http\Forms as F; ?>
<main class="page-shell"><h1>Mensagens</h1><p>As conversas são vinculadas a solicitações e agendamentos.</p><div class="two-columns"><aside class="card"><h2>Conversas</h2>
<?php if (!$page['conversations']): ?><p>Suas conversas aparecerão após solicitar um horário.</p><?php endif; ?>
<?php foreach ($page['conversations'] as $c): ?><p><a href="/mensagens?conversa=<?= e($c['id']) ?>"><?= e($c['service_name'] . ' · ' . $c['business_name']) ?> (<?= (int)$c['unread'] ?> não lidas)</a></p><?php endforeach; ?></aside>
<section class="card"><h2>Conversa selecionada</h2><?php if (!$page['conversation']): ?><p>Selecione uma conversa.</p><?php else: ?>
<a href="/mensagens?conversa=<?= e($page['conversation']['id']) ?>">Atualizar mensagens</a>
<div class="chat-log" aria-label="Histórico da conversa"><?php foreach ($page['messages'] as $message): ?><article class="message <?= $message['is_system'] ? 'system' : '' ?>"><strong><?= $message['is_system'] ? 'AgendaShops' : e($message['sender_name'] ?? 'Conta excluída') ?></strong><p><?= nl2br(e($message['body'])) ?></p><small><?= e(localDate($message['created_at'])) ?></small></article><?php endforeach; ?></div>
<?php foreach ($page['reads'] as $read): if (!$read['read_at']) continue; ?><p><small>Última leitura de <?= e($read['name']) ?>: <?= e(localDate($read['read_at'])) ?></small></p><?php endforeach; ?>
<?php if ($page['conversation']['blocked']): ?><p role="alert">Conversa bloqueada pela administração.</p><?php else: ?><?= F::open('message',['conversation_id'=>$page['conversation']['id']],'/mensagens?conversa=' . $page['conversation']['id']) . F::area('body','Sua mensagem','','required maxlength="3000"') . F::close('Enviar mensagem') ?><?php endif; ?>
<details><summary>Denunciar conversa</summary><?= F::open('report',['target_type'=>'conversation','target_id'=>$page['conversation']['id']],'/mensagens?conversa=' . $page['conversation']['id']) . F::area('reason','Motivo') . F::close('Enviar denúncia') ?></details>
<?php endif; ?></section></div></main>
