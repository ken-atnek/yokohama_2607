<?php
declare(strict_types=1);

namespace DandyNotification;

final class Autoloader
{
    private const PREFIX = 'DandyNotification\\';

    public static function register(string $baseDir): void
    {
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        spl_autoload_register(static function (string $class) use ($baseDir): void {
            if (strncmp($class, self::PREFIX, strlen(self::PREFIX)) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen(self::PREFIX));
            $file = $baseDir . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
                . '.php';

            $realBase = realpath($baseDir);
            $realFile = realpath($file);

            if ($realBase === false || $realFile === false) {
                return;
            }

            $realBase = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strncmp($realFile, $realBase, strlen($realBase)) !== 0) {
                return;
            }

            require_once $realFile;
        });
    }
}
