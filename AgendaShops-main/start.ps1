$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpExecutable = if ($phpCommand) { $phpCommand.Source } elseif (Test-Path -LiteralPath 'C:\xampp\php\php.exe') { 'C:\xampp\php\php.exe' } else { throw 'Instale PHP 8.1 ou superior ou configure o PHP do XAMPP no PATH.' }
& $phpExecutable bin/console.php install
if ($LASTEXITCODE -ne 0) { throw 'Não foi possível instalar o banco.' }
Write-Host 'Site: http://localhost:8000 — mantenha este terminal aberto. Ctrl+C encerra o servidor.'
& $phpExecutable -S localhost:8000 index.php
