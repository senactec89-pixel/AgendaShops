<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';

use AgendaShops\Infrastructure\Database;
use AgendaShops\Modules\Identity\IdentityService;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$db = new Database();
$command = $argv[1] ?? 'help';
try {
    if ($command === 'install') {
        $db->migrate();
        if (!$db->one('SELECT id FROM categories LIMIT 1')) {
            $db->transaction(function () use ($db): void {
                foreach (require dirname(__DIR__) . '/config/initial-categories.php' as $index=>$name) {
                    $db->insert('categories',['name'=>$name,'sort_order'=>$index]);
                }
            });
        }
        if (!$db->one("SELECT id FROM users WHERE role='ADMIN'")) {
            $file = Database::storage() . '/setup.token';
            if (!is_file($file)) { file_put_contents($file,bin2hex(random_bytes(32)),LOCK_EX); }
            echo "Banco instalado. Configure a primeira conta de administrador neste endereço privado:\n" . rtrim(getenv('APP_URL') ?: 'http://localhost:8000','/') . '/instalar?token=' . trim(file_get_contents($file)) . "\n";
        } else { echo "Banco atualizado.\n"; }
    } elseif ($command === 'admin') {
        $db->migrate();
        $prompt = static function (string $message): string { fwrite(STDOUT,$message); return trim((string)fgets(STDIN)); };
        $email = getenv('ADMIN_EMAIL') ?: $prompt('E-mail do administrador: ');
        $name = getenv('ADMIN_NAME') ?: $prompt('Nome: ');
        $phone = getenv('ADMIN_PHONE') ?: $prompt('Telefone com DDD: ');
        $password = getenv('ADMIN_PASSWORD');
        if (!$password) { throw new RuntimeException('Defina ADMIN_PASSWORD no ambiente do processo (mínimo 10 caracteres).'); }
        if ($db->one('SELECT id FROM users WHERE email=?',[mb_strtolower(trim($email))])) { throw new RuntimeException('E-mail já cadastrado. Nenhuma permissão foi alterada.'); }
        $user = (new IdentityService($db))->register(['name'=>$name,'email'=>$email,'phone'=>$phone,'password'=>$password,'password_confirmation'=>$password,'consent'=>'1'],'cli-admin');
        $db->execute("UPDATE users SET role='ADMIN' WHERE id=?",[$user['id']]);
        echo "Administrador criado. Use suas credenciais na tela Entrar.\n";
    } elseif ($command === 'expire') {
        $catalog = new \AgendaShops\Modules\Catalog\CatalogService($db);
        (new \AgendaShops\Modules\Bookings\BookingService($db,$catalog))->expire();
        echo "Expirações processadas.\n";
    } else {
        echo "Comandos: install | admin | expire\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n"); exit(1);
}
