# Instalação e configuração local

## Início rápido

Na raiz que contém `composer.json` e `start.ps1`, execute:

```powershell
.\start.ps1
```

O script encontra o PHP no PATH ou em `C:\xampp\php\php.exe`, instala o schema e
inicia o site em `http://localhost:8000`. O instalador informa o endereço privado
para criar o primeiro administrador. Não existe senha padrão.

Para executar os comandos separadamente:

```powershell
& C:\xampp\php\php.exe bin/console.php install
& C:\xampp\php\php.exe -S localhost:8000 index.php
```

Abra `http://localhost:8000/` ou `http://localhost:8000/index.php`.
O `index.php` da raiz entrega apenas os assets públicos conhecidos e delega as
demais requisições para a aplicação, inclusive a proteção dos caminhos privados.
Não inicie o servidor na raiz sem informar `index.php` como roteador.

Para manter `public/` como raiz no desenvolvimento, também é possível executar:

```powershell
& C:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
```

No Apache, a raiz do projeto contém `.htaccess` para encaminhar todas as
requisições ao index, sem liberar arquivos privados por existirem no disco.
Essa configuração exige `mod_rewrite` e `AllowOverride` habilitados. Em produção,
continue preferindo DocumentRoot apontado diretamente para `public/`.

## O arquivo local.php

`config/local.php` contém ajustes deste computador. O arquivo já foi preparado
para o ambiente local e é ignorado no Git. Em uma cópia nova do projeto, ele é
opcional; para criá-lo a partir do modelo:

```powershell
Copy-Item config/local.example.php config/local.php
```

Não sobrescreva um `local.php` já configurado. O arquivo deve retornar um array
com nomes de variáveis de ambiente e valores de texto, por exemplo:

```php
<?php
declare(strict_types=1);

return [
    'APP_ENV' => 'local',
    'APP_URL' => 'http://localhost:8000',
    'DB_DSN' => '',
    'MAIL_TRANSPORT' => 'file',
];
```

Com `DB_DSN` vazio, o banco SQLite continua em `storage/agendashops.sqlite`.
Não é necessário mover, recriar ou limpar o banco ao organizar as pastas.

## Ordem de configuração

Da maior para a menor prioridade:

1. Variáveis já definidas no processo/servidor.
2. `.env` na raiz, quando existir.
3. `config/local.php`, quando existir.
4. `config/app.php`, com os valores padrão.

Uma variável explicitamente vazia também prevalece sobre os arquivos de menor
prioridade. Isso permite, por exemplo, desabilitar Google em testes mesmo quando
as credenciais locais estão configuradas. O `.env` continua suportado; não é
preciso duplicar os mesmos ajustes em dois arquivos.

## Dados e integrações

- Sessões: `storage/sessions/`.
- Fotos: `storage/media/`, servidas pela rota `/media/...`.
- E-mails locais: `storage/mail/`; abra o arquivo para usar o link de recuperação.
- Token da primeira instalação: `storage/setup.token`, invalidado após o cadastro.
- PostgreSQL em produção: configure `DB_DSN`, `DB_USER` e `DB_PASSWORD`.
- Google: configure `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` e o callback
  `APP_URL/auth/google/callback`.
- E-mail externo: configure `MAIL_TRANSPORT=smtp`, `MAIL_FROM`, `SMTP_HOST`,
  `SMTP_PORT`, `SMTP_USER` e `SMTP_PASSWORD`.

Para produção, mantenha HTTPS e DocumentRoot apontando para **public/**. A
configuração local não substitui a configuração da hospedagem. Os detalhes dos
provedores e da primeira operação estão no [README](../README.md).

## Verificações

```powershell
& C:\xampp\php\php.exe tests/configuration/run.php
& C:\xampp\php\php.exe tests/domain/run.php
python tests/http/checks.py
```

Com Composer: `composer test` executa configuração e domínio;
`composer test:http` executa HTTP (requer Python). Os testes usam dados isolados.
