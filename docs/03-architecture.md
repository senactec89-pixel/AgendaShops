# Arquitetura inicial

## Visão

```text
Web responsiva
├── Área pública e descoberta
├── Área autenticada do cliente
├── Portal do parceiro
└── Administração
          │
          ▼
API modular
├── Identidade e acesso
├── Estabelecimentos
├── Catálogo e descoberta
├── Agenda e agendamentos
├── Favoritos e avaliações
├── Mensagens e notificações
└── Administração e auditoria
          │
          ├── PostgreSQL/PostGIS
          ├── Redis e processamento assíncrono
          ├── Armazenamento de imagens
          ├── Provedor de mapas
          └── E-mail e futuras notificações
```

## Organização técnica

- `apps/web`: aplicação Next.js responsiva.
- `apps/api`: API NestJS independente e versionada.
- `packages/contracts`: contratos de domínio compartilhados.
- `packages/ui`: tokens e componentes visuais reutilizáveis.
- `infra`: infraestrutura local e futuros artefatos de implantação.

## Estratégia de backend

O MVP será um monólito modular. Cada módulo possuirá limites claros, mas será
implantado como uma única API. Isso reduz custo operacional e preserva a opção de
separar serviços somente quando volume e equipe justificarem.

## Consistência da agenda

A disponibilidade será calculada no servidor a partir de horário do
estabelecimento, jornada do profissional, duração do serviço, intervalos, bloqueios
e agendamentos existentes.

O aceite do parceiro deverá ocorrer em transação e impedir sobreposição. A
persistência deverá guardar o preço, duração, profissional e política aplicados no
momento da confirmação.

## Web e segurança

- Sessões protegidas por cookies `HttpOnly`, `Secure` e política `SameSite`.
- Autorização validada na API por papel e pelo estabelecimento ao qual o usuário
  pertence.
- Proteção contra CSRF, XSS, força bruta e abuso de endpoints.
- Logs sem senhas, tokens ou dados pessoais desnecessários.
- Páginas públicas renderizadas de forma compatível com SEO.
- Interface planejada para WCAG 2.2 AA e navegação por teclado.

