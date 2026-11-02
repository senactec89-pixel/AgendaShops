<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
if (is_file(ROOT.'/.env')) foreach (file(ROOT.'/.env', FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
 if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
 [$k,$v]=explode('=', $line, 2); $_ENV[trim($k)]=trim($v, " \t\n\r\0\x0B\"'");
}
function env(string $key, string $default=''): string { return getenv($key)!==false ? (string)getenv($key) : ($_ENV[$key] ?? $default); }
date_default_timezone_set(env('APP_TIMEZONE','America/Sao_Paulo'));
if(env('APP_ENV','local')==='production') {
 ini_set('display_errors','0'); ini_set('display_startup_errors','0');
 ini_set('log_errors','1'); ini_set('error_log',ROOT.'/storage/php-error.log');
 error_reporting(E_ALL);
}

spl_autoload_register(function($class){ $file=ROOT.'/'.str_replace('\\','/',$class).'.php'; if(is_file($file)) require $file; });
function db(): PDO { static $pdo; if($pdo) return $pdo; $pdo = new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';dbname='.env('DB_DATABASE','agendashops').';charset=utf8mb4', env('DB_USERNAME','root'), env('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); $pdo->exec("SET time_zone = '".date('P')."'"); return $pdo; }
function query(string $sql,array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function url(string $path=''): string { return rtrim(env('APP_URL',''),'/').'/'.ltrim($path,'/'); }
function go(string $path): never { header('Location: '.url($path)); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void { if(!hash_equals(csrf(), (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) throw new RuntimeException('Sessão expirada. Atualize a página.',419); }
function money(mixed $v): string { return 'R$ '.number_format((float)$v,2,',','.'); }
function user(): ?array { return isset($_SESSION['uid']) ? (query('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['uid']])->fetch() ?: null) : null; }
function auth(bool $owner=false): array { $u=user(); if(!$u) go('entrar'); if($u['role']!=='superadmin') { $t=query('SELECT active FROM establishments WHERE id=?',[$u['establishment_id']])->fetch(); if(!$t || !$t['active']) throw new RuntimeException('Estabelecimento bloqueado.',403); } if($owner && $u['role']!=='admin') throw new RuntimeException('Acesso restrito ao administrador do estabelecimento.',403); return $u; }
function tenant(): int { $u=auth(); if(!$u['establishment_id']) throw new RuntimeException('Acesse o painel da plataforma.',403); return (int)$u['establishment_id']; }
function rate_limit(string $key,int $limit=15,int $seconds=900): void { $hash=hash('sha256',$key.'|'.($_SERVER['REMOTE_ADDR']??'cli')); query('INSERT IGNORE INTO rate_limits (bucket,hits,expires_at) VALUES (?,0,?)',[$hash,date('Y-m-d H:i:s',time()+$seconds)]); query('UPDATE rate_limits SET hits=IF(expires_at<NOW(),1,hits+1),expires_at=IF(expires_at<NOW(),?,expires_at) WHERE bucket=?',[date('Y-m-d H:i:s',time()+$seconds),$hash]); if((int)query('SELECT hits FROM rate_limits WHERE bucket=?',[$hash])->fetchColumn()>$limit) throw new RuntimeException('Muitas tentativas. Aguarde alguns minutos.',429); }
function view(string $name,array $data=[]): void { extract($data); require ROOT.'/app/Views/'.$name.'.php'; }
function required(string $key,int $max=190): string { $v=trim((string)($_POST[$key]??'')); if($v==='' || mb_strlen($v)>$max) throw new RuntimeException('Preencha corretamente: '.$key,422); return $v; }
function email_value(string $key,bool $optional=false): string { $v=trim((string)($_POST[$key]??'')); if(($v==='' && $optional) || filter_var($v,FILTER_VALIDATE_EMAIL)) return $v; throw new RuntimeException('Informe um e-mail válido.',422); }
function phone_value(string $key='phone'): string { $v=preg_replace('/\D/','',required($key,30)); if(strlen($v)<10 || strlen($v)>15) throw new RuntimeException('Informe um WhatsApp válido com DDD.',422); return $v; }
function password_value(): string { $p=required('password',200); if(strlen($p)<10) throw new RuntimeException('A senha deve ter pelo menos 10 caracteres.',422); return password_hash($p,PASSWORD_DEFAULT); }
