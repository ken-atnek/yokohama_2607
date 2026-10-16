<?php
declare(strict_types=1);

namespace DandyNotification\Database;

use PDO;

final class DatabaseCapabilities
{
    /** @var \WeakMap<PDO, bool>|null */
    private static ?\WeakMap $skipLockedSupportByConnection = null;

    public static function supportsForUpdateSkipLocked(PDO $pdo): bool
    {
        $cache = self::skipLockedSupportCache();
        if (!isset($cache[$pdo])) {
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            $cache[$pdo] = is_string($version)
                && self::supportsForUpdateSkipLockedVersion($version);
        }

        return $cache[$pdo];
    }

    public static function supportsForUpdateSkipLockedVersion(string $version): bool
    {
        if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $matches) !== 1) {
            return false;
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $isMariaDb = stripos($version, 'mariadb') !== false;

        if ($isMariaDb) {
            return $major > 10 || ($major === 10 && $minor >= 6);
        }

        return $major >= 8;
    }

    /**
     * @return \WeakMap<PDO, bool>
     */
    private static function skipLockedSupportCache(): \WeakMap
    {
        if (self::$skipLockedSupportByConnection === null) {
            self::$skipLockedSupportByConnection = new \WeakMap();
        }

        return self::$skipLockedSupportByConnection;
    }
}
