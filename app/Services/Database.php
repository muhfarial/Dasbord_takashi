<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Takashi Fried Chicken - Dual-PDO Database Manager
 * Primary: MySQL 8.0 (PDO via .env)
 * Automatic Fallback: SQLite Local PDO (database/takashi_runtime.sqlite)
 * Ensures 100% persistent CRUD writes across page refreshes even when MySQL daemon is offline.
 */
class Database
{
    private static ?PDO $mysqlPdo = null;
    private static bool $mysqlAttempted = false;
    private static ?PDO $sqlitePdo = null;
    private static array $env = [];

    public static function loadEnv(): array
    {
        if (!empty(self::$env)) {
            return self::$env;
        }

        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }
                if (str_contains($trimmed, '=')) {
                    [$key, $val] = explode('=', $trimmed, 2);
                    $key = trim($key);
                    $val = trim($val, " \t\n\r\0\x0B\"'");
                    self::$env[$key] = $val;
                }
            }
        }

        return self::$env;
    }

    public static function env(string $key, string $default = ''): string
    {
        $env = self::loadEnv();
        return $env[$key] ?? $default;
    }

    /**
     * Try connecting to MySQL 8.0 via PDO using .env configuration.
     */
    public static function mysql(): ?PDO
    {
        if (self::$mysqlAttempted) {
            return self::$mysqlPdo;
        }

        self::$mysqlAttempted = true;

        if (!extension_loaded('pdo_mysql')) {
            return null;
        }

        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '3306');
        $db   = self::env('DB_DATABASE', 'dasbord_takashi');
        $user = self::env('DB_USERNAME', 'root');
        $pass = self::env('DB_PASSWORD', '');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 1,
            ]);
            // Quick sanity check that core tables exist
            $pdo->query("SELECT 1 FROM items LIMIT 1");
            self::$mysqlPdo = $pdo;
        } catch (PDOException $e) {
            self::$mysqlPdo = null;
        }

        return self::$mysqlPdo;
    }

    /**
     * Local SQLite PDO fallback so CRUD persists across refreshes even if Laragon MySQL is stopped.
     */
    public static function sqlite(): PDO
    {
        if (self::$sqlitePdo !== null) {
            return self::$sqlitePdo;
        }

        $dbPath = __DIR__ . '/../../database/takashi_runtime.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = OFF;');

        self::$sqlitePdo = $pdo;
        return self::$sqlitePdo;
    }

    public static function isMysqlActive(): bool
    {
        return self::mysql() !== null;
    }

    public static function modeLabel(): string
    {
        return self::isMysqlActive() ? 'MySQL 8.0 (PDO Aktif)' : 'SQLite PDO Persisten (Lokal)';
    }
}
