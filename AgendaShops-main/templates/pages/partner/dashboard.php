<?php use AgendaShops\Http\Forms as F; $b=$page['business']; $return=$b ? '/parceiro?business=' . $b['id'] : '/parceiro'; ?>
<main class="page-shell"><span class="pill">Parceiro</span><h1>Seu estabelecimento, sua agenda</h1><p><a class="button button-primary" href="/agendamentos">Gerenciar solicitações e atendimentos</a> <a class="button button-secondary" href="/mensagens">Conversar com clientes</a></p>
<?php if ($page['businesses']): ?><form method="get"><?= F::select('business','Estabelecimento',array_column($page['businesses'],'name','id'),$b['id'] ?? '') ?><button>Selecionar</button></form><?php endif; ?>
<?php if ($b): ?><section class="card"><h2><?= e($b['name']) ?></h2><p>Status: <strong><?= e(['PENDING'=>'Aguardando aprovação','APPROVED'=>'Aprovado','REJECTED'=>'Recusado','SUSPENDED'=>'Suspenso'][$b['status']] ?? $b['status']) ?></strong></p><p>Horários no fuso <?= e($b['timezone']) ?>.</p>
<?php if ($b['status'] === 'APPROVED'): ?><a href="/estabelecimentos/<?= e($b['slug']) ?>">Ver página pública</a><?php endif; ?>
</section>
<?php if ($b['membership_role'] === 'PARTNER_OWNER'): ?>
<details class="card"><summary>Dados, funcionamento e política</summary>
<?= F::open('business-save',['business_id'=>$b['id']],$return) . F::input('name','Nome',$b['name'],'text','required') . F::area('description','Descrição',$b['description']) . F::input('address','Endereço',$b['address'],'text','required') . F::input('city','Cidade',$b['city'],'text','required') . F::input('phone','Telefone',$b['phone'],'tel','required') . F::area('policy','Política de cancelamento e atendimento',$b['policy']) . F::input('opens','Abertura',$b['opens'],'time','required') . F::input('closes','Fechamento',$b['closes'],'time','required') . F::days($b['weekdays']) . F::input('pending_minutes','Prazo de aceite em minutos (0 = sem expiração automática)',(string)$b['pending_minutes'],'number','required min="0" max="10080"') . F::input('latitude','Latitude (opcional)',$b['latitude'] ?? '','number','min="-90" max="90" step="any"') . F::input('longitude','Longitude (opcional)',$b['longitude'] ?? '','number','min="-180" max="180" step="any"') . F::close() ?>
</details>
<section class="card"><h2>Profissionais e jornadas</h2>
<?php foreach (array_merge($page['professionals'],[['id'=>'','name'=>'','opens'=>'09:00','closes'=>'18:00','weekdays'=>'1,2,3,4,5,6','active'=>1]]) as $p): ?>
<details><summary><?= $p['id'] ? e($p['name']) : 'Adicionar profissional' ?></summary>
<?= F::open('professional-save',['business_id'=>$b['id'],'professional_id'=>$p['id']],$return) . F::input('name','Nome',$p['name'],'text','required') . F::input('opens','Início da jornada',$p['opens'],'time','required') . F::input('closes','Fim da jornada',$p['closes'],'time','required') . F::days($p['weekdays']) . F::check('active','Profissional ativo',(bool)$p['active']) . F::close() ?>
</details><?php endforeach; ?></section>
<section class="card"><h2>Serviços</h2>
<?php if (!$page['categories']): ?><p>Peça à administração para cadastrar categorias antes de publicar serviços.</p><?php endif; ?>
<?php foreach (array_merge($page['services'],[['id'=>'','name'=>'','category_id'=>'','description'=>'','duration_minutes'=>30,'price_cents'=>0,'active'=>1]]) as $s): ?>
<details><summary><?= $s['id'] ? e($s['name']) : 'Adicionar serviço' ?></summary>
<?= F::open('service-save',['business_id'=>$b['id'],'service_id'=>$s['id']],$return) . F::input('name','Nome do serviço',$s['name'],'text','required') . F::area('description','Descrição',$s['description']) . F::select('category_id','Categoria',array_column($page['categories'],'name','id'),$s['category_id']) . F::input('duration_minutes','Duração em minutos',(string)$s['duration_minutes'],'number','required min="5" max="720"') . F::input('price','Preço em reais',number_format((int)$s['price_cents']/100,2,',',''),'text','required inputmode="decimal"') ?>
<fieldset><legend>Profissionais habilitados</legend><?php $linked=$s['linked_professionals'] ?? []; foreach ($page['professionals'] as $p): if (!$p['active']) continue; ?><?= F::check('professionals[]',$p['name'],in_array($p['id'],$linked,true),$p['id']) ?><?php endforeach; ?></fieldset>
<?= F::check('active','Serviço publicado',(bool)$s['active']) . F::close() ?>
<?php if ($s['id']): ?>
<h3>Foto do serviço</h3><?= F::open('upload',['service_id'=>$s['id']],$return,true) . F::input('image','JPG, PNG ou WebP até 4 MB','','file','required accept="image/jpeg,image/png,image/webp"') . F::close('Enviar imagem') ?>
<h3>Promoção</h3><?= F::open('promotion',['service_id'=>$s['id']],$return) . F::input('percent','Desconto percentual','','number','min="1" max="90"') . F::input('starts_at','Início','','datetime-local') . F::input('ends_at','Fim','','datetime-local') . F::check('remove','Remover a promoção atual') . F::close('Atualizar promoção') ?>
<?php endif; ?></details><?php endforeach; ?></section>
<section class="card"><h2>Equipe com acesso ao portal</h2><ul><?php foreach ($page['members'] as $m): ?><li><?= e($m['name'] . ' · ' . $m['email'] . ' · ' . $m['role']) ?></li><?php endforeach; ?></ul><p>O funcionário deve criar uma conta antes de ser vinculado. Pode operar agendamentos, bloqueios e mensagens.</p>
<?= F::open('staff',['business_id'=>$b['id']],$return) . F::input('email','E-mail do funcionário','','email','required') . F::check('remove','Remover acesso deste funcionário') . F::close('Atualizar acesso') ?></section>
<?php endif; ?>
<section class="card"><h2>Intervalos, folgas e bloqueios</h2>
<?= F::open('block',[],$return) . F::select('professional_id','Profissional',array_column($page['professionals'],'name','id')) . F::input('starts_at','Início','','datetime-local','required') . F::input('ends_at','Fim','','datetime-local','required') . F::input('reason','Motivo','','text','required minlength="3"') . F::close('Bloquear intervalo') ?>
<?php foreach ($page['blocks'] as $block): ?><p><?= e($block['name'] . ' · ' . localDate($block['starts_at'],$b['timezone']) . ' a ' . localDate($block['ends_at'],$b['timezone']) . ' · ' . $block['reason']) ?></p><?= F::open('block',['professional_id'=>$block['professional_id'],'block_id'=>$block['id'],'remove'=>'1'],$return) . F::close('Remover bloqueio') ?><?php endforeach; ?></section>
<?php endif; ?>
<details class="card" <?= !$b ? 'open' : '' ?>><summary>Cadastrar estabelecimento</summary><p>O estabelecimento só aparecerá na busca após aprovação administrativa.</p>
<?= F::open('business-create',[],'/parceiro') . F::input('name','Nome do estabelecimento','','text','required') . F::area('description','Descrição') . F::input('address','Endereço','','text','required') . F::input('city','Cidade','','text','required') . F::input('phone','Telefone com DDD','','tel','required') . F::select('timezone','Fuso horário',['America/Sao_Paulo'=>'Brasília (São Paulo)','America/Manaus'=>'Manaus','America/Rio_Branco'=>'Rio Branco','America/Noronha'=>'Fernando de Noronha']) . F::area('policy','Política de cancelamento e atendimento') . F::close('Enviar para aprovação') ?></details>
</main>
