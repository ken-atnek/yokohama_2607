<?php
declare(strict_types=1);

namespace DandyNotification\Database;

use DandyNotification\Config\AppConfig;
use InvalidArgumentException;
use PDO;

final class PdoFactory
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    public function create(): PDO
    {
        $database = $this->config->database();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $database['host'],
            (int) $database['port'],
            $database['name'],
            $database['charset']
        );

        $pdo = new PDO(
            $dsn,
            (string) $database['user'],
            (string) $database['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );

        $this->initializeConnectionSession($pdo, (string) $database['charset']);

        return $pdo;
    }

    private function initializeConnectionSession(PDO $pdo, string $charset): void
    {
        $pdo->exec($this->charsetInitializationSql($charset));
        $pdo->exec("SET time_zone = '+00:00'");
    }

    private function charsetInitializationSql(string $charset): string
    {
        return match ($charset) {
            'utf8mb4' => 'SET NAMES utf8mb4',
            default => throw new InvalidArgumentException('Unsupported database charset.'),
        };
    }
}
