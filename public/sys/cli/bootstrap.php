<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

return require dirname(__DIR__) . '/lib/bootstrap.php';
