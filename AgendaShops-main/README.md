# AgendaShops — site PHP funcional

Site responsivo de serviços de beleza e bem-estar, com dados persistidos e áreas
de cliente, parceiro, funcionário e administrador.

O arquivo **`index.php` na raiz** abre o sistema por um servidor PHP. Na pasta do
projeto, execute `php -S localhost:8000 index.php` e acesse
`http://localhost:8000/index.php`. Com XAMPP, use o caminho do PHP mostrado abaixo.
Arquivos PHP precisam do servidor; não são abertos pelo protocolo `file://`.

## Iniciar no Windows

Abra um PowerShell nesta pasta e execute:

```powershell
.\start.ps1
```

O script usa PHP do PATH ou `C:\xampp\php\php.exe`, instala o banco local e informa
o endereço privado para criar o primeiro administrador. Mantenha o terminal
aberto e acesse **http://localhost:8000**. Não há senha administrativa padrão.

Alternativamente, execute na raiz:

```powershell
& C:\xampp\php\php.exe bin/console.php install
& C:\xampp\php\php.exe -S localhost:8000 index.php
```

Com PHP no PATH, substitua o caminho por `php`. Com Composer, também é possível
usar `composer install-app`, `composer serve` e `composer test`.

## Primeira configuração pelo site

1. Abra o endereço `/instalar?token=...` exibido pelo instalador e crie sua conta
   administrativa. O token é privado e deixa de funcionar após a criação.
2. Em **Admin**, confira as categorias iniciais; crie, ordene ou desative conforme
   necessário. Subcategorias também são suportadas.
3. Crie uma conta de parceiro pelo cadastro normal. Em **Parceiro**, cadastre o
   estabelecimento, endereço, política e fuso. Configure horários, profissionais,
   serviços, preços e fotos. Habilite os profissionais em cada serviço.
4. Na conta administrativa, aprove o estabelecimento em **Admin**. Só então ele
   aparecerá em **Explorar**.
5. Clientes podem buscar, comparar, favoritar e solicitar horários. O parceiro
   aceita/recusa em **Agenda**; ambas as partes conversam em **Mensagens**.

Não há estabelecimentos fictícios no banco inicial. A busca fica vazia até um
parceiro real cadastrar serviços e receber aprovação.

## Funcionalidades

- Cadastro com consentimento versionado, login, logout, recuperação de senha,
  atualização de perfil e exclusão com anonimização cadastral.
- Login e vinculação Google quando as credenciais estiverem configuradas.
- Estabelecimentos sujeitos a aprovação; serviços com imagens, duração, preços
  em centavos, categorias configuráveis e profissionais habilitados.
- Jornadas, dias de atendimento, intervalos/folgas por data e bloqueios.
- Busca por nome, cidade/endereço, categoria, faixa de preço, avaliação e data.
  Geolocalização opcional ordena por distância aproximada; busca manual permanece.
- Favoritos e comparação de até três serviços da mesma categoria.
- Solicitação pendente, aceite transacional sem sobreposição, recusa, cancelamento,
  reagendamento, expiração configurável, atendimento e avaliação após conclusão.
- Chat vinculado ao agendamento, eventos de sistema, leitura, contador de não lidas,
  denúncias e bloqueio administrativo. Use **Atualizar mensagens** para novas mensagens.
- Promoção percentual por serviço e período; notificações transacionais na conta.
- Equipe vinculada por e-mail pelo proprietário; funcionário opera agenda, bloqueios
  e mensagens. O proprietário mantém catálogo, jornadas, equipe e estabelecimento.
- Administração de categorias, aprovação/suspensão, denúncias, avaliações, inspeção
  e bloqueio de conversas, com auditoria.

Pendentes não reservam o horário. O prazo de resposta é configurado em minutos
pelo parceiro; zero desativa a expiração automática. Horários de consulta têm passo
de 15 minutos e horizonte de 180 dias. O aceite revalida disponibilidade, preço,
duração e política e preserva os dados da confirmação.

## Banco e configuração

Requisitos: PHP 8.1+ com PDO, `pdo_sqlite` (local) ou `pdo_pgsql` (produção),
mbstring, fileinfo e cURL. Use uma versão PHP atualmente suportada em produção.
Composer e Node.js não são necessários para o modo local.

Ajustes deste computador ficam em `config/local.php`, com modelo em
`config/local.example.php`. O arquivo local é ignorado no Git. O `.env` continua
suportado: ambiente do processo > `.env` > `config/local.php` > `config/app.php`.
Consulte o [guia local](docs/08-local-setup.md). Não publique credenciais.

Sem `DB_DSN`, o banco local é `storage/agendashops.sqlite`. Dados, sessões, imagens
e e-mails são privados em `storage/`, que deve ser gravável pelo processo PHP.
Para PostgreSQL em produção:

```dotenv
APP_ENV=production
APP_URL=https://seu-dominio.example
DB_DSN=pgsql:host=localhost;port=5432;dbname=agendashops
DB_USER=agendashops
DB_PASSWORD=configure-no-servidor
MAIL_TRANSPORT=smtp
MAIL_FROM=noreply@seu-dominio.example
```

Crie o banco e usuário, habilite `pdo_pgsql` e execute `php bin/console.php install`.
O schema usa SQL compartilhado entre os adaptadores. PostgreSQL/PostGIS continua
como banco de produção planejado. Redis é opcional e não é usado nos fluxos atuais.
O Compose de infraestrutura pode ser iniciado com:

```sh
docker compose --env-file .env -f infra/docker-compose.yml up -d
```

A saúde da aplicação está em `/api/v1/health`; a consulta de horários em
`/api/v1/slots/ID_DO_SERVICO?date=AAAA-MM-DD`. Saúde não verifica banco ou provedores.
Para processar expiração sem visitas ao site, agende `php bin/console.php expire`.

## E-mail e Google

Localmente, `MAIL_TRANSPORT=file` salva os e-mails de recuperação em
`storage/mail/*.txt`. Abra o arquivo para usar o link de recuperação. Esses arquivos
não são servidos pelo site. Em produção, configure SMTP com STARTTLS:

```dotenv
MAIL_TRANSPORT=smtp
MAIL_FROM=noreply@seu-dominio.example
SMTP_HOST=smtp.seu-provedor.example
SMTP_PORT=587
SMTP_USER=configure-no-servidor
SMTP_PASSWORD=configure-no-servidor
```

Para Google, crie um cliente OAuth de aplicação web e defina `GOOGLE_CLIENT_ID` e
`GOOGLE_CLIENT_SECRET`. Cadastre como callback exatamente
`APP_URL/auth/google/callback` (ex.: `http://localhost:8000/auth/google/callback`).
O botão só aparece quando configurado. Para vincular uma conta local existente,
entre primeiro com sua senha e use o botão de vinculação no perfil. Para conta
nova, aceite os termos pelo cadastro Google.

Google e entrega SMTP real precisam das credenciais do operador e não foram
validados com contas externas nesta entrega. Veja a
[documentação oficial do Google](https://developers.google.com/identity/openid-connect/openid-connect).

## Testes

```powershell
& C:\xampp\php\php.exe tests/configuration/run.php
& C:\xampp\php\php.exe tests/domain/run.php
python tests/http/checks.py
```

Os testes usam dados isolados e não criam contas no banco do site. A suíte de
configuração valida a precedência dos ambientes. A de domínio cobre permissões,
conflitos, concorrência real, transições, promoções, histórico, chat, expiração e
avaliações. A de HTTP inicia um servidor temporário e verifica os fluxos completos,
incluindo CSRF, recuperação de senha, upload e proteção dos arquivos privados.

Para usar outro PHP nos testes HTTP, defina `PHP_BINARY` no ambiente. Não aponte
`TEST_DB_DSN` para banco real; o teste de domínio cria dados de teste.

## Hospedagem e estrutura

Exponha **somente `public/`** como raiz do site, com HTTPS. Apache precisa de
`mod_rewrite` e permissão para o `.htaccess` incluído. O
[servidor embutido do PHP](https://www.php.net/manual/en/features.commandline.webserver.php)
é destinado ao desenvolvimento. Faça backup do banco e do armazenamento privado;
revise os textos de termos/privacidade com os dados do operador antes do piloto.

```text
index.php           Abertura local pela raiz e entrega dos assets permitidos
public/index.php    Entrada compartilhada do site e da API; raiz de produção
public/assets/     CSS e JavaScript em pastas próprias
config/            bootstrap.php, app.php e configuração local.php
src/               HTTP, módulos, contratos, infraestrutura e helpers
templates/         layouts/, partials/ e pages/ separadas por área
database/          Schema relacional
bin/console.php    Instalação, administrador e expiração
storage/           Dados privados preservados, ignorados no Git
tests/             configuration/, domain/ e http/
docs/              Requisitos, arquitetura, guias e decisões
```

Leia [AGENTS.md](AGENTS.md), o [índice](docs/README.md) e o
[ADR-005](docs/decisions/ADR-005-functional-local-site.md).

Mapa completo dos arquivos: [organização do projeto](docs/07-project-structure.md).
