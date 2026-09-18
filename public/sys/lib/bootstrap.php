<?php
declare(strict_types=1);

use DandyNotification\Autoloader;
use DandyNotification\ApplicationContext;
use DandyNotification\Bootstrap;

require_once __DIR__ . '/src/DandyNotification/Autoloader.php';

Autoloader::register(__DIR__ . '/src/DandyNotification');

if (!defined('DANDY_NOTIFICATION_BOOTSTRAP_ENV_CONFIG_PATH')) {
    define('DANDY_NOTIFICATION_BOOTSTRAP_ENV_CONFIG_PATH', 'DANDY_NOTIFICATION_CONFIG');
}

if (!defined('DANDY_NOTIFICATION_BOOTSTRAP_PRODUCTION_CONFIG_PATH')) {
    define(
        'DANDY_NOTIFICATION_BOOTSTRAP_PRODUCTION_CONFIG_PATH',
        '/home/hpg-kobe/private/dandy_notification/dandy_notification_config.production.php'
    );
}

if (!function_exists('dandy_notification_bootstrap_resolve_config_path')) {
    function dandy_notification_bootstrap_resolve_config_path(
        ?string $explicitConfigPath = null
    ): string {
        if ($explicitConfigPath !== null) {
            // Keep '' and '0' explicit paths invalid instead of allowing ConfigLoader to fall back to the environment.
            return ($explicitConfigPath === '' || $explicitConfigPath === '0') ? ' ' : $explicitConfigPath;
        }

        $environmentConfigPath = getenv(DANDY_NOTIFICATION_BOOTSTRAP_ENV_CONFIG_PATH);
        if ($environmentConfigPath !== false && $environmentConfigPath !== '') {
            return $environmentConfigPath;
        }

        if (is_file(DANDY_NOTIFICATION_BOOTSTRAP_PRODUCTION_CONFIG_PATH)) {
            return DANDY_NOTIFICATION_BOOTSTRAP_PRODUCTION_CONFIG_PATH;
        }

        return ' ';
    }
}

if (!function_exists('dandy_notification_bootstrap_create')) {
    function dandy_notification_bootstrap_create(
        ?string $explicitConfigPath = null
    ): ApplicationContext {
        return Bootstrap::create(dandy_notification_bootstrap_resolve_config_path($explicitConfigPath));
    }
}

return dandy_notification_bootstrap_create();
