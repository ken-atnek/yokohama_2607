<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const EX_SOFTWARE = 70;
const EX_TEMPFAIL = 75;
const EX_CONFIG = 78;
const FIRST_LINE_MAX_BYTES = 65536;
const COPY_OK = 'ok';
const COPY_INPUT_READ_FAILURE = 'input_read_failure';
const COPY_CHILD_WRITE_FAILURE = 'child_write_failure';
const COPY_FIRST_LINE_OVERFLOW = 'first_line_overflow';

try {
    exit(runReceiveEmptyMailWrapper($argv ?? []));
} catch (Throwable) {
    writeWrapperError('notification empty mail wrapper internal error');
    exit(EX_SOFTWARE);
}

/**
 * @param list<string> $argv
 */
function runReceiveEmptyMailWrapper(array $argv): int
{
    $options = parseWrapperOptions(array_slice($argv, 1));
    if ($options === null) {
        writeWrapperError('usage: php receive-empty-mail.php [--child-script=/path/to/pop3.php] [--php-binary=/path/to/php]');

        return EX_CONFIG;
    }

    $phpBinary = $options['php_binary'] ?? PHP_BINARY;
    $childScript = $options['child_script'] ?? dirname(__DIR__) . '/pop3.php';

    if (!is_string($phpBinary) || $phpBinary === '') {
        writeWrapperError('notification empty mail wrapper php binary is invalid');

        return EX_CONFIG;
    }

    if (!is_string($childScript) || $childScript === '' || !is_file($childScript) || !is_readable($childScript)) {
        writeWrapperError('notification empty mail wrapper child script is unavailable');

        return EX_CONFIG;
    }

    $input = fopen('php://stdin', 'rb');
    if (!is_resource($input)) {
        writeWrapperError('notification empty mail wrapper stdin error');

        return EX_TEMPFAIL;
    }

    $firstLineResult = readFirstLineBounded($input);
    if ($firstLineResult['status'] === COPY_INPUT_READ_FAILURE) {
        return EX_TEMPFAIL;
    }

    if ($firstLineResult['status'] === COPY_FIRST_LINE_OVERFLOW) {
        return EX_SOFTWARE;
    }

    $descriptors = [
        0 => ['pipe', 'rb'],
        1 => ['file', 'php://stdout', 'wb'],
        2 => ['file', 'php://stderr', 'wb'],
    ];
    $process = @proc_open(
        [$phpBinary, $childScript],
        $descriptors,
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );

    if (!is_resource($process) || !isset($pipes[0]) || !is_resource($pipes[0])) {
        writeWrapperError('notification empty mail wrapper child launch failed');

        return EX_TEMPFAIL;
    }

    $copyStatus = copyNormalizedMessage($input, $pipes[0], $firstLineResult['line']);
    fclose($pipes[0]);
    $childExitCode = proc_close($process);

    if ($copyStatus === COPY_CHILD_WRITE_FAILURE) {
        if (isValidChildExitCode($childExitCode)) {
            return $childExitCode;
        }

        writeWrapperError('notification empty mail wrapper child exit code is invalid');

        return EX_SOFTWARE;
    }

    if ($copyStatus === COPY_INPUT_READ_FAILURE) {
        return EX_TEMPFAIL;
    }

    if ($copyStatus === COPY_FIRST_LINE_OVERFLOW) {
        return EX_SOFTWARE;
    }

    if (!isValidChildExitCode($childExitCode)) {
        writeWrapperError('notification empty mail wrapper child exit code is invalid');

        return EX_SOFTWARE;
    }

    return $childExitCode;
}

/**
 * @param list<string> $arguments
 * @return array{child_script?: string, php_binary?: string}|null
 */
function parseWrapperOptions(array $arguments): ?array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--child-script=')) {
            $options['child_script'] = substr($argument, strlen('--child-script='));
            continue;
        }

        if (str_starts_with($argument, '--php-binary=')) {
            $options['php_binary'] = substr($argument, strlen('--php-binary='));
            continue;
        }

        return null;
    }

    return $options;
}

/**
 * @param resource $input
 * @param resource $childStdin
 */
function copyNormalizedMessage($input, $childStdin, ?string $firstLine): string
{
    if ($firstLine === null) {
        return COPY_OK;
    }

    if (strncmp($firstLine, 'From ', 5) !== 0 && !writeAll($childStdin, $firstLine)) {
        return COPY_CHILD_WRITE_FAILURE;
    }

    while (!feof($input)) {
        $chunk = fread($input, 8192);
        if ($chunk === false) {
            writeWrapperError('notification empty mail wrapper stdin read failed');

            return COPY_INPUT_READ_FAILURE;
        }

        if ($chunk !== '' && !writeAll($childStdin, $chunk)) {
            return COPY_CHILD_WRITE_FAILURE;
        }
    }

    return COPY_OK;
}

/**
 * @param resource $input
 * @return array{status: string, line: ?string}
 */
function readFirstLineBounded($input): array
{
    $line = '';
    while (true) {
        if (strlen($line) >= FIRST_LINE_MAX_BYTES) {
            $byte = fread($input, 1);
            if ($byte === false) {
                writeWrapperError('notification empty mail wrapper stdin read failed');

                return ['status' => COPY_INPUT_READ_FAILURE, 'line' => null];
            }

            if ($byte === '' && feof($input)) {
                return ['status' => COPY_OK, 'line' => $line === '' ? null : $line];
            }

            writeWrapperError('notification empty mail wrapper first line is too long');

            return ['status' => COPY_FIRST_LINE_OVERFLOW, 'line' => null];
        }

        $byte = fread($input, 1);
        if ($byte === false) {
            writeWrapperError('notification empty mail wrapper stdin read failed');

            return ['status' => COPY_INPUT_READ_FAILURE, 'line' => null];
        }

        if ($byte === '') {
            if (feof($input)) {
                return ['status' => COPY_OK, 'line' => $line === '' ? null : $line];
            }

            writeWrapperError('notification empty mail wrapper stdin read failed');

            return ['status' => COPY_INPUT_READ_FAILURE, 'line' => null];
        }

        $line .= $byte;
        if ($byte === "\n") {
            return ['status' => COPY_OK, 'line' => $line];
        }
    }
}

/**
 * @param resource $stream
 */
function writeAll($stream, string $bytes): bool
{
    $offset = 0;
    $length = strlen($bytes);
    while ($offset < $length) {
        $written = @fwrite($stream, substr($bytes, $offset));
        if ($written === false || $written === 0) {
            writeWrapperError('notification empty mail wrapper child stdin write failed');

            return false;
        }

        $offset += $written;
    }

    return true;
}

function isValidChildExitCode(mixed $exitCode): bool
{
    return is_int($exitCode) && $exitCode >= 0 && $exitCode <= 255;
}

function writeWrapperError(string $message): void
{
    if (defined('STDERR')) {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
