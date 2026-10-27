<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($projectRoot): void {
    $prefix = 'AgendaShops\\';
    if (str_starts_with($class, $prefix)) {
        $file = $projectRoot . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

\AgendaShops\Infrastructure\Configuration::load($projectRoot);
require_once $projectRoot . '/src/Support/helpers.php';
date_default_timezone_set('UTC');
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
