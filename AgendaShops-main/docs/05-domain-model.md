# Modelo de domínio inicial

Este documento apresenta entidades e limites conceituais. Ele não substitui o
schema do banco e deve evoluir junto com as regras de negócio.

## Identidade

### User

- Identidade comum de acesso.
- Nome, e-mail, telefone e estado da conta.
- Pode possuir credencial local e identidade Google vinculada.
- Possui um ou mais papéis e consentimentos versionados.

### CustomerProfile

- Preferências e dados específicos do cliente.
- Favoritos e histórico são relacionados, não embutidos no perfil.

### BusinessMembership

- Liga um usuário a um estabelecimento.
- Papéis iniciais: proprietário e funcionário.
- Permissões futuras podem ser refinadas sem criar outra conta.

## Oferta

### Business

- Estabelecimento parceiro sujeito a aprovação administrativa.
- Possui endereço, coordenadas, fuso, contatos, horários e estado de publicação.

### Professional

- Profissional pertencente a um estabelecimento.
- Possui especialidades, agenda e vínculo com serviços.

### Category

- Categoria ou subcategoria configurável de beleza e bem-estar.
- Desativação preserva referências históricas.

### Service

- Serviço oferecido por um estabelecimento.
- Possui duração, preço, descrição, imagens, categoria e profissionais elegíveis.
- Não representa produto físico, estoque ou pedido.

### Promotion

- Regra temporária aplicada a serviços elegíveis.
- Possui vigência, desconto, escopo e limites.

## Agenda

### BusinessHours

- Horário regular do estabelecimento.

### AvailabilityRule

- Jornada recorrente de um profissional.

### ScheduleBlock

- Intervalo, folga, feriado ou bloqueio excepcional.

### Booking

- Solicitação e posterior compromisso entre cliente e parceiro.
- Guarda serviço, profissional, intervalo, estado e snapshots comerciais.
- Começa como `PENDING_PARTNER_APPROVAL`.

### BookingStatusHistory

- Registro imutável das transições, ator, momento e motivo.

## Relacionamento

### Favorite

- Relação entre cliente e estabelecimento salvo.

### Review

- Avaliação vinculada a um atendimento concluído.
- Uma avaliação principal por cliente e atendimento.

### Conversation

- Canal entre cliente e parceiro vinculado a uma solicitação ou agendamento no
  primeiro ciclo.

### Message

- Mensagem de usuário ou evento automático da plataforma.
- Possui remetente, data, conteúdo e estado de leitura.

### Notification

- Entrega transacional derivada de evento de domínio.
- Falha de entrega não altera o estado do agendamento.

## Governança

### Report

- Denúncia de avaliação, mensagem, usuário ou estabelecimento.

### AuditEvent

- Registro de ação administrativa ou operacional crítica.
- Não deve armazenar segredos ou conteúdo pessoal desnecessário.

## Relacionamentos centrais

```text
User ── CustomerProfile
User ── BusinessMembership ── Business
Business ── Professional
Business ── Service ── Category
Professional ── Service
Professional ── AvailabilityRule / ScheduleBlock
Customer + Business + Service + Professional ── Booking
Booking ── BookingStatusHistory
Booking ── Conversation ── Message
Completed Booking ── Review
```

## Decisões ainda abertas

- Solicitação pendente não bloqueia horário; expiração é configurada por estabelecimento (ADR-005).
- Recursos compartilhados além de profissional, como sala ou equipamento.
- Política de atendimento é informada pelo parceiro e preservada no aceite.
- Funcionários operam agenda, bloqueios e chat; proprietário administra dados, catálogo e equipe.
- Comparação usa serviços da mesma categoria como critério inicial de equivalência.

## Persistência implementada

O schema executável é [database/schema.sql](../database/schema.sql), compartilhado
pelos adaptadores PostgreSQL e SQLite. `users` guarda credenciais e consentimento;
`memberships` representa BusinessMembership; `professionals` e
`service_professionals` definem habilitação; `businesses` e `professionals` guardam
dias e horários recorrentes. `schedule_blocks` representa intervalos e exceções.

`bookings` guarda snapshots e chave de idempotência por cliente; `booking_history`
é append-only pela aplicação. `conversations`, `messages` e `conversation_reads`
preservam contexto e leituras. `notifications`, `reports` e `audit_events` suportam
operação. `password_resets` armazena somente hash de token e expiração;
`rate_limits` mantém limites persistentes. Datas são UTC em ISO 8601 de largura
fixa; o fuso de exibição fica no estabelecimento e no snapshot do agendamento.
