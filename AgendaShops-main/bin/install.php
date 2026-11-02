<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/bootstrap.php';
$name=env('DB_DATABASE','agendashops');if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name))exit("Nome de banco inválido.\n");
try {
 $pdo=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';charset=utf8mb4',env('DB_USERNAME','root'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
 if(db()->query('SHOW TABLES')->fetch()){fwrite(STDERR,"O banco já contém tabelas. Nada foi alterado.\n");exit(1);}
 db()->exec(file_get_contents(ROOT.'/database/database.sql'));
 echo "Instalação concluída. Nenhuma conta padrão foi criada.\nAbra ".url()." e cadastre seu estabelecimento.\n";
}catch(Throwable $e){fwrite(STDERR,"Falha na instalação: ".$e->getMessage()."\n");exit(1);}
