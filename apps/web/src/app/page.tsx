import Link from "next/link";

const highlights = [
  {
    eyebrow: "Descoberta",
    title: "Encontre o cuidado certo",
    text: "Busque serviços, compare opções e descubra profissionais perto de você.",
  },
  {
    eyebrow: "Agenda",
    title: "Solicite o melhor horário",
    text: "Escolha serviço, profissional, data e hora. O parceiro confirma a solicitação.",
  },
  {
    eyebrow: "Conversa",
    title: "Combine os detalhes",
    text: "Após solicitar um horário, converse com o estabelecimento no mesmo lugar.",
  },
];

export default function HomePage() {
  return (
    <main>
      <header className="site-header">
        <Link className="brand" href="/" aria-label="AgendaShops — início">
          <span className="brand-mark" aria-hidden="true">A</span>
          <span>AgendaShops</span>
        </Link>
        <nav aria-label="Navegação principal">
          <Link href="/explorar">Explorar</Link>
          <Link href="/entrar">Entrar</Link>
          <Link className="nav-cta" href="/cadastro">Criar conta</Link>
        </nav>
      </header>

      <section className="hero">
        <div className="hero-copy">
          <span className="pill">Beleza e bem-estar, no seu tempo</span>
          <h1>Seu próximo cuidado começa com um horário.</h1>
          <p>
            Descubra salões, estúdios e profissionais, compare serviços e envie
            sua solicitação de agendamento em poucos passos.
          </p>
          <div className="hero-actions">
            <Link className="button button-primary" href="/explorar">Explorar serviços</Link>
            <Link className="button button-secondary" href="/parceiro">Sou parceiro</Link>
          </div>
        </div>
        <div className="hero-panel" aria-label="Resumo do fluxo de agendamento">
          <span className="panel-kicker">Como funciona</span>
          <ol>
            <li><strong>Encontre</strong><span>serviços e estabelecimentos</span></li>
            <li><strong>Compare</strong><span>preço, avaliação e horário</span></li>
            <li><strong>Solicite</strong><span>o profissional e o melhor momento</span></li>
            <li><strong>Confirme</strong><span>após o aceite do parceiro</span></li>
          </ol>
        </div>
      </section>

      <section className="feature-grid" aria-label="Principais recursos">
        {highlights.map((item) => (
          <article className="feature-card" key={item.title}>
            <span>{item.eyebrow}</span>
            <h2>{item.title}</h2>
            <p>{item.text}</p>
          </article>
        ))}
      </section>
    </main>
  );
}

