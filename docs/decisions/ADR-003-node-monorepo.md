# ADR-003 — Monorepo web e API Node.js

- Estado: aceito.
- Data: 23/10/2026.

## Contexto

O projeto terá uma interface web pública, áreas autenticadas e uma API que poderá
atender clientes futuros sem duplicar regras.

## Decisão

Usar monorepo TypeScript com Next.js/React no frontend e API Node.js organizada
com NestJS. PostgreSQL/PostGIS será a fonte de dados e Redis será auxiliar.

## Consequências

- Contratos e componentes podem ser compartilhados por packages.
- A API continua independente da renderização web.
- NestJS organiza o código, mas a plataforma de execução permanece Node.js.
- Trocas estruturais exigem novo ADR e alinhamento com o responsável pelo projeto.
