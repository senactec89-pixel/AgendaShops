<?php
declare(strict_types=1);
namespace AgendaShops\Infrastructure;

use PDO;

final class Database
{
    private int $depth = 0;
    public readonly PDO $pdo;
    public readonly bool $sqlite;

    public function __construct(?string $dsn = null)
    {
        $directory = self::storage();
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $dsn ??= getenv('DB_DSN') ?: 'sqlite:' . $directory . '/agendashops.sqlite';
        $this->sqlite = str_starts_with($dsn, 'sqlite:');
        if ($this->sqlite && getenv('APP_ENV') === 'production') { throw new \RuntimeException('Configure PostgreSQL em produção.'); }
        if (!$this->sqlite && !str_starts_with($dsn, 'pgsql:')) {
            throw new \RuntimeException('Use SQLite localmente ou PostgreSQL em produção.');
        }
        $this->pdo = new PDO($dsn, getenv('DB_USER') ?: null, getenv('DB_PASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        if ($this->sqlite) {
            $this->pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 10000; PRAGMA journal_mode = WAL;');
        }
    }

    public function migrate(): void
    {
        $this->pdo->exec(file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
    }

    public function all(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        return $this->all($sql, $params)[0] ?? null;
    }

    public function execute(string $sql, array $params = []): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    public function insert(string $table, array $data): string
    {
        $data = ['id' => self::id()] + $data;
        $this->execute('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
        return $data['id'];
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->depth > 0) {
            $savepoint = 'nested_' . $this->depth;
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
            $this->depth++;
            try {
                $result = $callback();
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                return $result;
            } catch (\Throwable $exception) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                throw $exception;
            } finally { $this->depth--; }
        }
        $this->sqlite ? $this->pdo->exec('BEGIN IMMEDIATE') : $this->pdo->beginTransaction();
        $this->depth++;
        try {
            $result = $callback();
            $this->sqlite ? $this->pdo->exec('COMMIT') : $this->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->sqlite ? $this->pdo->exec('ROLLBACK') : $this->pdo->rollBack();
            throw $exception;
        } finally { $this->depth--; }
    }

    public function lockProfessional(string $id): void
    {
        $this->one('SELECT id FROM professionals WHERE id = ?' . ($this->sqlite ? '' : ' FOR UPDATE'), [$id]);
    }

    public static function id(): string { return bin2hex(random_bytes(16)); }
    public static function now(): string { return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'); }
    public static function storage(): string { return getenv('STORAGE_PATH') ?: dirname(__DIR__, 2) . '/storage'; }
}
