<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/config/bootstrap.php';
$db = new \AgendaShops\Infrastructure\Database();
$db->migrate();
$password = bin2hex(random_bytes(12));
$token = bin2hex(random_bytes(32));
file_put_contents(\AgendaShops\Infrastructure\Database::storage() . '/setup.token',$token,LOCK_EX);
echo json_encode(['email'=>'admin@example.test','password'=>$password,'setup_token'=>$token],JSON_THROW_ON_ERROR);
