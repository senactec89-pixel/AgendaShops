<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/config/bootstrap.php';

use AgendaShops\Infrastructure\Configuration;

$directory = sys_get_temp_dir() . '/agendashops-config-' . bin2hex(random_bytes(8));
mkdir($directory . '/config', 0700, true);
$key = 'AGENDASHOPS_CONFIG_TEST';
$original = getenv($key);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
};
$write = static function (string $file, array $values): void {
    file_put_contents($file, '<?php return ' . var_export($values, true) . ';');
};
try {
    putenv($key);
    $write($directory . '/config/app.php', [$key => 'default']);
    Configuration::load($directory);
    $assert(getenv($key) === 'default', 'Shared defaults work without a local file.');

    putenv($key);
    $write($directory . '/config/local.php', [$key => 'local']);
    Configuration::load($directory);
    $assert(getenv($key) === 'local', 'Local values override defaults.');

    putenv($key);
    file_put_contents($directory . '/.env', $key . '="dotenv"' . "\n");
    Configuration::load($directory);
    $assert(getenv($key) === 'dotenv', '.env overrides local settings.');

    putenv($key . '=process');
    Configuration::load($directory);
    $assert(getenv($key) === 'process', 'Process environment keeps precedence over local credentials.');

    putenv($key . '=');
    Configuration::load($directory);
    $assert(getenv($key) === '', 'An explicitly empty process value is preserved.');

    unlink($directory . '/.env');
    putenv($key);
    $write($directory . '/config/local.php', [$key => ['invalid']]);
    try {
        Configuration::load($directory);
        throw new LogicException('Invalid configuration was accepted.');
    } catch (RuntimeException $error) {
        $assert(str_contains($error->getMessage(), 'Configuração inválida'), 'Invalid configuration fails clearly.');
    }
    echo "OK: {$checks} configuration checks\n";
} finally {
    $original === false ? putenv($key) : putenv($key . '=' . $original);
    foreach (['.env', 'config/app.php', 'config/local.php'] as $file) {
        if (is_file($directory . '/' . $file)) { unlink($directory . '/' . $file); }
    }
    rmdir($directory . '/config');
    rmdir($directory);
}
