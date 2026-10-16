<?php
declare(strict_types=1);

use DandyNotification\Cli\PublicStateReconciliationCommand;
use DandyNotification\Exception\ConfigException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$args = array_slice($argv ?? [], 1);
if ($args === []) {
    $apply = false;
} elseif ($args === ['--apply']) {
    $apply = true;
} else {
    if (defined('STDERR')) {
        fwrite(STDERR, 'usage: php reconcile_public_state.php [--apply]' . PHP_EOL);
    }
    exit(1);
}

try {
    $context = require __DIR__ . '/bootstrap.php';
    $exitCode = (new PublicStateReconciliationCommand($context))->run($apply);
} catch (ConfigException) {
    if (defined('STDERR')) {
        fwrite(STDERR, 'notification public state reconciliation config error' . PHP_EOL);
    }
    $exitCode = 1;
} catch (Throwable) {
    if (defined('STDERR')) {
        fwrite(STDERR, 'notification public state reconciliation internal error' . PHP_EOL);
    }
    $exitCode = 1;
}

exit($exitCode);
