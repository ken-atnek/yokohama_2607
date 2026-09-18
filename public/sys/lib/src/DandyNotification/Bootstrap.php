<?php
declare(strict_types=1);

namespace DandyNotification;

use DandyNotification\Config\ConfigLoader;
use DandyNotification\Database\PdoFactory;
use DandyNotification\Log\JsonLogger;

final class Bootstrap
{
    public static function create(?string $configPath = null): ApplicationContext
    {
        $publicRoot = dirname(__DIR__, 4);
        $loader = new ConfigLoader($publicRoot);
        $config = $loader->load($configPath);

        date_default_timezone_set($config->appTimezone());

        $flags = $config->featureFlags();
        $logger = new JsonLogger(
            $config->logPath(),
            $config->logLevel(),
            $publicRoot
        );

        return new ApplicationContext(
            $config,
            $flags,
            $logger,
            new PdoFactory($config)
        );
    }
}
