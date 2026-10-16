<?php
declare(strict_types=1);

use DandyNotification\Cli\CreateDeliveriesCommand;
use DandyNotification\Exception\ConfigException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (array_slice($argv ?? [], 1) !== []) {
    if (defined('STDERR')) {
        fwrite(STDERR, 'usage: php create-deliveries.php' . PHP_EOL);
    }
    exit(1);
}

try {
    $context = require __DIR__ . '/bootstrap.php';
    $exitCode = (new CreateDeliveriesCommand($context))->run();
} catch (ConfigException) {
    if (defined('STDERR')) {
        fwrite(STDERR, 'notification delivery create config error' . PHP_EOL);
    }
    $exitCode = 1;
} catch (Throwable) {
    if (defined('STDERR')) {
        fwrite(STDERR, 'notification delivery create internal error' . PHP_EOL);
    }
    $exitCode = 1;
}

exit($exitCode);
