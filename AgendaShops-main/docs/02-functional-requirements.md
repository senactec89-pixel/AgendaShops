# Requisitos funcionais iniciais

## RF-WEB-001 — Cadastro do cliente

O cliente poderá criar uma conta com nome, e-mail, telefone, senha e confirmação
do aceite dos termos e da política de privacidade vigentes.

Critérios principais:

- E-mail e telefone serão validados quanto a formato e duplicidade.
- O aceite guardará versão, data e origem.
- A senha nunca será persistida em texto puro.

## RF-WEB-002 — Autenticação

O cliente poderá entrar com e-mail/senha ou Google, encerrar a sessão e recuperar
o acesso. Contas sociais e locais deverão ser vinculadas sem duplicidade quando
representarem a mesma identidade verificada.

## RF-WEB-003 — Perfil e exclusão

O cliente poderá atualizar seus dados básicos e solicitar exclusão. A remoção
respeitará histórico, auditoria, obrigações legais e anonimização aplicável.

## RF-WEB-004 — Categorias e subcategorias

O administrador poderá criar, ordenar, ativar e desativar categorias e
subcategorias de beleza e bem-estar sem alteração de código.

## RF-WEB-005 — Catálogo do parceiro

O parceiro poderá cadastrar serviços com nome, descrição, categoria, duração,
preço, imagens, profissionais habilitados e estado de publicação.

## RF-WEB-006 — Descoberta

O cliente poderá buscar por serviço ou estabelecimento e filtrar por localização,
faixa de preço, avaliação e disponibilidade. A recusa da geolocalização não
impedirá a busca manual.

## RF-WEB-007 — Página do estabelecimento

A página pública apresentará informações do estabelecimento, localização,
horários, serviços, profissionais, fotos, preços iniciais, avaliações e próxima
disponibilidade pertinente.

## RF-WEB-008 — Favoritos

O cliente autenticado poderá adicionar e remover estabelecimentos de sua lista de
favoritos.

## RF-WEB-009 — Comparação de serviços

O cliente poderá selecionar até três opções equivalentes e compará-las antes de
iniciar o agendamento.

Critérios comparáveis:

- Preço e duração.
- Avaliação e quantidade de avaliações.
- Distância aproximada.
- Próxima disponibilidade.
- Promoção vigente.
- Profissionais disponíveis.

As informações de disponibilidade são indicativas na comparação e deverão ser
revalidadas ao criar a solicitação.

## RF-WEB-010 — Solicitação de agendamento

O cliente selecionará estabelecimento, serviço, profissional, data e horário e
enviará uma solicitação ao parceiro. O agendamento começará com o estado
`PENDING_PARTNER_APPROVAL` e não poderá ser apresentado como confirmado.

## RF-WEB-011 — Aceite ou recusa do parceiro

O parceiro poderá aceitar ou recusar uma solicitação pendente. Ao aceitar, o
backend revalidará a disponibilidade de forma transacional. Em caso de conflito,
a aceitação falhará de forma controlada e o parceiro deverá sugerir outro horário.

Estados iniciais previstos:

```text
PENDING_PARTNER_APPROVAL
CONFIRMED
REJECTED_BY_PARTNER
EXPIRED
CANCELED_BY_CUSTOMER
CANCELED_BY_PARTNER
IN_PROGRESS
COMPLETED
NO_SHOW
RESCHEDULED
```

## RF-WEB-012 — Chat

Antes do lançamento público, o sistema deverá permitir conversas entre cliente e
parceiro. No primeiro ciclo, uma conversa será vinculada a uma solicitação ou a um
agendamento, reduzindo spam e mantendo contexto operacional.

O módulo deverá suportar:

- Lista de conversas e contador de não lidas.
- Mensagens de texto.
- Eventos automáticos de aceite, recusa, cancelamento e reagendamento.
- Bloqueio administrativo e denúncia.
- Registro de data, remetente e estado de leitura.

Anexos e comunicação promocional em massa ficam fora do primeiro ciclo.

## Regras operacionais implementadas em 23/10/2026

- Contas locais usam senha com 10 a 72 caracteres, hash, CSRF e limite de tentativas.
  Recuperação usa link de 30 minutos, de uso único, e revoga sessões anteriores.
- Consentimento registra versão, data UTC e origem. Login Google é exibido quando
  configurado. Para vincular uma conta local existente, entre primeiro com senha.
- Categorias/subcategorias, ordem e estado são administráveis pelo site. A
  equivalência de comparação usa a mesma categoria, com no máximo três serviços.
- Parceiros cadastram estabelecimento sujeito à aprovação, serviços, fotos,
  profissionais, jornadas, dias de funcionamento, bloqueios e promoções percentuais
  com vigência. Promoções não se acumulam; há uma promoção por serviço.
- Funcionários precisam de conta e vínculo criado pelo proprietário. Podem operar
  agenda, mensagens e bloqueios, mas não alterar catálogo, equipe ou estabelecimento.
- Busca permite texto, endereço manual, categoria, preço mínimo/máximo, avaliação
  e data disponível. Coordenadas opcionais ordenam por distância aproximada.
- Horários de consulta usam intervalos de 15 minutos e horizonte de 180 dias.
  O backend valida jornada, funcionamento, duração, bloqueios e confirmações.
- Solicitações pendentes não reservam o horário. O parceiro configura prazo de
  aceite em minutos; zero significa sem expiração automática. Aceite revalida tudo
  dentro de transação com bloqueio do profissional; sobreposição falha com 409.
- Preço, duração e política apresentados na solicitação são revalidados no aceite,
  quando são preservados juntamente com o profissional e nome do serviço.
- Reagendamento pelo cliente cria nova solicitação e libera a anterior de forma
  atômica. A nova solicitação exige outro aceite. Falhas mantêm a original.
- Atendimento só pode ser iniciado a partir do horário marcado. Apenas o parceiro
  pode iniciar, concluir ou registrar não comparecimento. Clientes só avaliam seus
  próprios atendimentos concluídos, uma avaliação por atendimento.
- Chat mantém mensagens, eventos automáticos, leituras e contadores. Atualização
  ocorre pelo link de atualização da conversa, sem promessa de tempo real.
- Notificações de mudanças de estado ficam na conta. Denúncias, bloqueio de chat,
  moderação de avaliações e aprovação/suspensão são administráveis e auditados.
- Exclusão é bloqueada enquanto houver atendimentos ativos. Após a exclusão, dados
  cadastrais são anonimizados e o histórico operacional é preservado.

Google e entrega externa de e-mail dependem das credenciais do operador. No modo
local, e-mails são salvos em `storage/mail`, nunca disponibilizados publicamente.
Veja [ADR-005](decisions/ADR-005-functional-local-site.md).

