<?php
$highlights = [
    ['eyebrow' => 'Descoberta', 'title' => 'Encontre o cuidado certo', 'text' => 'Busque serviços, compare opções e descubra profissionais perto de você.'],
    ['eyebrow' => 'Agenda', 'title' => 'Solicite o melhor horário', 'text' => 'Escolha serviço, profissional, data e hora. O parceiro confirma a solicitação.'],
    ['eyebrow' => 'Conversa', 'title' => 'Combine os detalhes', 'text' => 'Após solicitar um horário, converse com o estabelecimento no mesmo lugar.'],
];
?>
<main>

      <section class="hero">
        <div class="hero-copy">
          <span class="pill">Beleza e bem-estar, no seu tempo</span>
          <h1>Seu próximo cuidado começa com um horário.</h1>
          <p>
            Descubra salões, estúdios e profissionais, compare serviços e envie
            sua solicitação de agendamento em poucos passos.
          </p>
          <div class="hero-actions">
            <a class="button button-primary" href="/explorar">Explorar serviços</a>
            <a class="button button-secondary" href="/parceiro">Sou parceiro</a>
          </div>
        </div>
        <div class="hero-panel" aria-label="Resumo do fluxo de agendamento">
          <span class="panel-kicker">Como funciona</span>
          <ol>
            <li><strong>Encontre</strong><span>serviços e estabelecimentos</span></li>
            <li><strong>Compare</strong><span>preço, avaliação e horário</span></li>
            <li><strong>Solicite</strong><span>o profissional e o melhor momento</span></li>
            <li><strong>Confirme</strong><span>após o aceite do parceiro</span></li>
          </ol>
        </div>
      </section>

      <section class="feature-grid" aria-label="Principais recursos">
        <?php foreach ($highlights as $item): ?>
          <article class="feature-card">
            <span><?= e($item['eyebrow']) ?></span>
            <h2><?= e($item['title']) ?></h2>
            <p><?= e($item['text']) ?></p>
          </article>
        <?php endforeach; ?>
      </section>
    </main>
