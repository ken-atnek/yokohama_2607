#!/usr/local/bin/php
<?php
declare(strict_types=1);

use DandyNotification\Cli\EmptyMailReceiveCommand;
use DandyNotification\Exception\ConfigException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $context = require __DIR__ . '/cli/bootstrap.php';
    $exitCode = (new EmptyMailReceiveCommand($context))->run();
} catch (ConfigException) {
    if (defined('STDERR')) {
        fwrite(STDERR, 'notification empty mail config error' . PHP_EOL);
    }
    $exitCode = 78;
} catch (Throwable) {
    if (defined('STDERR')) {
        fwrite(STDERR, 'notification empty mail internal error' . PHP_EOL);
    }
    $exitCode = 70;
}

exit($exitCode);
