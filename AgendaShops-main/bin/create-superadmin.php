<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/bootstrap.php';
$email=trim(readline('E-mail do superadministrador: '));$name=trim(readline('Nome: '));
if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !$name)exit("Dados inválidos.\n");
// Use a hidden prompt on Windows and Unix; never pass the password in command arguments.
if(PHP_OS_FAMILY==='Windows') {
 $cmd='powershell -NoProfile -Command "$p = Read-Host -AsSecureString Senha; $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b) }"';
 $password=rtrim((string)shell_exec($cmd),"\r\n");
} else {
 echo 'Senha: ';system('stty -echo');try{$password=rtrim((string)fgets(STDIN),"\r\n");}finally{system('stty echo');echo "\n";}
}
if(strlen($password)<12)exit("Use no mínimo 12 caracteres.\n");
try{query("INSERT INTO users(name,email,password,role) VALUES (?,?,?,'superadmin')",[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);echo "Superadministrador criado.\n";}catch(Throwable){fwrite(STDERR,"Não foi possível criar a conta. Verifique o e-mail e o banco.\n");exit(1);}
