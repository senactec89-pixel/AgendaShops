<?php
require dirname(__DIR__).'/config/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$name=env('DB_DATABASE');if(!preg_match('/^agendashops_http_[a-f0-9]{10}$/D',$name))exit(1);
$pdo=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';charset=utf8mb4',env('DB_USERNAME','root'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(($argv[1]??'')==='cleanup'){$pdo->exec("DROP DATABASE `$name`");exit;}
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");db()->exec(file_get_contents(ROOT.'/database/database.sql'));
