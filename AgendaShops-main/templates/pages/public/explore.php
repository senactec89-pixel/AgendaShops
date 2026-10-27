<?php use AgendaShops\Http\Forms as F; ?>
<main class="page-shell"><span class="pill">Beleza e bem-estar</span><h1><?= e($page['title']) ?></h1>
<form method="get" action="/explorar" class="card filters">
<?= F::input('q','Serviço ou estabelecimento',is_string($_GET['q'] ?? null) ? $_GET['q'] : '') ?>
<?= F::input('city','Cidade, bairro ou endereço',is_string($_GET['city'] ?? null) ? $_GET['city'] : '') ?>
<?= F::select('category','Categoria',[''=>'Todas'] + array_column($page['categories'],'name','id'),(string)($_GET['category'] ?? ''),'') ?>
<?= F::input('max_price','Preço máximo (R$)',(string)($_GET['max_price'] ?? ''),'number','min="0" step="0.01"') ?>
<?= F::input('min_price','Preço mínimo (R$)',(string)($_GET['min_price'] ?? ''),'number','min="0" step="0.01"') ?>
<?= F::select('rating','Avaliação mínima',[''=>'Qualquer','3'=>'3 estrelas','4'=>'4 estrelas','5'=>'5 estrelas'],(string)($_GET['rating'] ?? ''),'') ?>
<?= F::input('date','Disponível na data',(string)($_GET['date'] ?? ''),'date') ?>
<input type="hidden" name="lat" value="<?= e((string)($_GET['lat'] ?? '')) ?>"><input type="hidden" name="lng" value="<?= e((string)($_GET['lng'] ?? '')) ?>">
<button class="button button-primary">Buscar</button><button type="button" class="button button-secondary" data-locate>Usar minha localização</button>
<p class="help" data-location-status aria-live="polite">Localização é opcional. Você pode buscar pelo endereço.</p></form>
<?php if (!$page['services']): ?><section class="card empty"><h2>Nenhum serviço encontrado</h2><p>Altere os filtros ou volte mais tarde. Estabelecimentos precisam da aprovação da plataforma para aparecer.</p></section><?php endif; ?>
<section class="service-grid" aria-label="Serviços encontrados">
<?php foreach ($page['services'] as $service): require dirname(__DIR__, 2) . '/partials/service-card.php'; endforeach; ?>
</section></main>
