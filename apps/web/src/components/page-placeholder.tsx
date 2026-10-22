import Link from "next/link";

interface PagePlaceholderProps {
  eyebrow: string;
  title: string;
  description: string;
}

export function PagePlaceholder({ eyebrow, title, description }: PagePlaceholderProps) {
  return (
    <main className="placeholder-shell">
      <Link className="brand" href="/">
        <span className="brand-mark" aria-hidden="true">A</span>
        <span>AgendaShops</span>
      </Link>
      <section className="placeholder-card">
        <span className="pill">{eyebrow}</span>
        <h1>{title}</h1>
        <p>{description}</p>
        <Link className="button button-secondary" href="/">Voltar ao início</Link>
      </section>
    </main>
  );
}

