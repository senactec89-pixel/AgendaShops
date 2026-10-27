# ADR-006 — Organização dos arquivos e configuração local

- Estado: aceito pela solicitação de organização completa do projeto.
- Data: 26/10/2026.
- Complementa: ADR-004 e ADR-005.

## Contexto

Após a implementação dos fluxos PHP, as telas e os testes estavam concentrados
em pastas únicas. O responsável solicitou organização dos arquivos, incluindo
`index.php`, `local.php` e documentação.

## Decisão

- Manter `public/index.php` como única entrada HTTP e `public/router.php` como
  roteador de desenvolvimento; somente `public/` é exposta pelo servidor.
- Mover a inicialização para `config/bootstrap.php`, compartilhada por console,
  aplicação e testes. Separar helpers em `src/Support/helpers.php`.
- Concentrar padrões versionados em `config/app.php`, fornecer
  `config/local.example.php` e permitir ajustes privados em `config/local.php`.
  Precedência: ambiente do processo > `.env` > configuração local > padrões.
- Separar telas em `templates/pages` por área, layout em `templates/layouts` e
  trechos compartilhados em `templates/partials`.
- Separar CSS e JavaScript em `public/assets/css` e `public/assets/js`.
- Separar testes de configuração, domínio e HTTP em suas próprias pastas,
  atualizando os comandos Composer e a documentação.
- Remover apenas diretórios legados vazios de `apps` e `packages`. Preservar
  integralmente banco, sessões, fotos e demais dados em `storage`.

## Consequências

As regras e URLs funcionais do site continuam as mesmas; os assets usam caminhos
atualizados. A raiz deixa de ter bootstrap de aplicação solto. Configurações locais
e credenciais permanecem fora da pasta pública e ignoradas no Git. O README e os
guias 07 e 08 descrevem a localização dos arquivos e os comandos atuais.
