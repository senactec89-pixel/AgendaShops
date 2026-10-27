# Roadmap inicial

## Fase 0 — Fundação

- Aplicação PHP, templates, API e contratos (migração da fundação concluída).
- Ambientes locais e convenções.
- Modelo de domínio e decisões pendentes.
- Design system responsivo.

## Fase 1 — Conta e catálogo

- Cadastro, login, recuperação e Google.
- Perfil e consentimentos.
- Administração de categorias.
- Parceiros, profissionais e serviços.
- Páginas públicas e upload de imagens.

## Fase 2 — Descoberta

- Busca e filtros.
- Geolocalização opcional e localização manual.
- Favoritos.
- Comparação de até três serviços.

## Fase 3 — Agenda

- Regras de jornada, intervalos e bloqueios.
- Cálculo de disponibilidade.
- Solicitação pendente.
- Aceite ou recusa do parceiro.
- Cancelamento e reagendamento.
- Notificações transacionais.

## Fase 4 — Operação e confiança

- Painel do parceiro.
- Aprovação administrativa.
- Avaliações após atendimento.
- Promoções.
- Denúncias, moderação e auditoria.

## Fase 5 — Chat e preparação pública

- Conversas vinculadas a agendamentos.
- Contadores de não lidas e eventos automáticos.
- Bloqueio, denúncia e moderação.
- Testes de carga e concorrência.
- Revisão de acessibilidade, privacidade e segurança.
- Piloto fechado antes da abertura pública.


## Estado da entrega funcional de 23/10/2026

As telas provisórias foram substituídas por fluxos persistidos. As capacidades
das fases 1 a 4 e o chat da fase 5 estão implementados para operação local,
incluindo controle por estabelecimento e testes de concorrência de aceite.

Antes de abrir o piloto público, ainda executar no ambiente de implantação:

- Configurar e validar OAuth Google e SMTP com credenciais reais.
- Executar o site e testes contra PostgreSQL e configurar backups.
- Configurar hospedagem, HTTPS e permissões de arquivos.
- Validar textos de termos/privacidade e dados do operador.
- Realizar teste de carga, revisão visual/acessibilidade em navegador e piloto fechado.

Essas etapas não são substituídas pelos testes locais automatizados.
