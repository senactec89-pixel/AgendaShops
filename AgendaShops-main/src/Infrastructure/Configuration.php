<?php
declare(strict_types=1);
namespace AgendaShops\Infrastructure;

final class Configuration
{
    /** Priority: process environment > .env > config/local.php > config/app.php. */
    public static function load(string $projectRoot): void
    {
        $defaults = self::read($projectRoot . '/config/app.php');
        $localFile = $projectRoot . '/config/local.php';
        $local = is_file($localFile) ? self::read($localFile) : [];
        $environment = [];
        if (is_file($projectRoot . '/.env')) {
            foreach (file($projectRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $match)) {
                    $environment[$match[1]] = trim($match[2], "\"'");
                }
            }
        }
        foreach (array_replace($defaults, $local, $environment) as $key => $value) {
            if (!is_string($key) || !preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) || !is_string($value) || str_contains($value, "\0")) {
                throw new \RuntimeException('Configuração inválida; use chaves de ambiente e valores de texto.');
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }

    private static function read(string $file): array
    {
        $values = require $file;
        if (!is_array($values)) {
            throw new \RuntimeException('O arquivo de configuração deve retornar um array.');
        }
        return $values;
    }
}
