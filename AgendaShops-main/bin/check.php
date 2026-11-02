<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/bootstrap.php';
$errors=0;
function check_deploy(bool $ok,string $message): void {global $errors;echo ($ok?'OK  ':'ERRO ').$message.PHP_EOL;if(!$ok)$errors++;}
check_deploy(PHP_VERSION_ID>=80200,'PHP 8.2+');
foreach(['pdo_mysql','mbstring','fileinfo','iconv'] as $ext)check_deploy(extension_loaded($ext),'Extensão '.$ext);
check_deploy(is_writable(ROOT.'/storage'),'storage gravável');
$public=is_dir(dirname(ROOT).'/public_html')?dirname(ROOT).'/public_html':ROOT.'/public';
check_deploy(is_dir($public.'/uploads') && is_writable($public.'/uploads'),'uploads gravável');
check_deploy(is_file(ROOT.'/.env'),'Arquivo .env privado');
if(env('APP_ENV')==='production') {
 check_deploy(str_starts_with(env('APP_URL'),'https://') && !str_contains(env('APP_URL'),'seu-dominio'),'URL HTTPS configurada');
 check_deploy(env('SESSION_SECURE')==='1','Cookies seguros');
 check_deploy(env('DB_USERNAME')!=='root','Usuário MySQL de hospedagem');
}
try {db()->query('SELECT id FROM establishments LIMIT 1');check_deploy(true,'Conexão e esquema do banco');}catch(Throwable){check_deploy(false,'Conexão/esquema: confira credenciais e importação do SQL');}
exit($errors?1:0);
