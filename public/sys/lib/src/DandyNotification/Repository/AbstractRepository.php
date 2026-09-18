<?php
declare(strict_types=1);

namespace DandyNotification\Repository;

use PDO;
use PDOStatement;

abstract class AbstractRepository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    protected function prepareAndExecute(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $parameter = is_int($key)
                ? $key + 1
                : (str_starts_with((string) $key, ':') ? (string) $key : ':' . (string) $key);

            $statement->bindValue($parameter, $value, $this->detectPdoType($value));
        }

        $statement->execute();

        return $statement;
    }

    private function detectPdoType(mixed $value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }

        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }

        if ($value === null) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }
}
