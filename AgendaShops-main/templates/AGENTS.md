# Instruções da aplicação web

Estas instruções complementam o `AGENTS.md` da raiz e valem para `templates` e `public/assets`.

## Plataforma

- Usar templates PHP e CSS; escapar dados com `e()`.
- Telas ficam em `pages/` por área; layout global em `layouts/main.php`; cartões e outros trechos compartilhados em `partials/`.
- Estilos ficam em `public/assets/css/`; scripts em `public/assets/js/`.
- A aplicação deve funcionar em celular e desktop; não ampliar simplesmente um
  layout mobile.
- Páginas públicas de descoberta e estabelecimento devem ser compatíveis com SEO.
- Componentes interativos devem funcionar por teclado e tecnologia assistiva.

## Limites de responsabilidade

- Consumir regras e estados vindos da API; não recriar disponibilidade ou
  autorização no navegador.
- Concentrar acesso a dados em serviços PHP, fora dos templates.
- Separar componentes visuais, estado da interface e acesso a dados.
- Renderizar HTML no servidor; adicionar JavaScript somente para interações necessárias.

## Linguagem de produto

- Usar sempre **AgendaShops**.
- Não mostrar produtos, carrinho, pedidos ou checkout.
- Chamar a primeira ação de agenda de “Solicitar horário” ou equivalente, não
  “Agendamento confirmado”.
- Exibir claramente os estados aguardando parceiro, confirmado, recusado,
  expirado, cancelado, em atendimento e concluído.
- Quando a geolocalização for recusada, oferecer cidade, bairro, CEP ou endereço.
- Comparação permite no máximo três opções equivalentes.

## Áreas

- Pública: início, explorar, estabelecimento, serviço e comparação.
- Cliente: conta, favoritos, solicitações, agendamentos, mensagens e avaliações.
- Parceiro: perfil, serviços, equipe, agenda, solicitações e conversas.
- Admin: aprovações, categorias, moderação, auditoria e configurações.

## Design e acessibilidade

- Garantir contraste WCAG 2.2 AA, foco visível e áreas de interação adequadas.
- Não depender somente de cor para comunicar estado.
- Fornecer loading, erro, vazio, sucesso e retry quando aplicável.
- Respeitar `prefers-reduced-motion`.
- Não usar imagens meramente decorativas quando elas prejudicarem desempenho.
