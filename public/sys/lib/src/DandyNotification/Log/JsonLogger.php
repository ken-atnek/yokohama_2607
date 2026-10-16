<?php
declare(strict_types=1);

namespace DandyNotification\Log;

use DateTimeImmutable;
use DateTimeZone;
use DandyNotification\Exception\ConfigException;

final class JsonLogger
{
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
    ];

    private readonly SensitiveDataSanitizer $sanitizer;

    public function __construct(
        private readonly string $path,
        private readonly string $level,
        private readonly string $publicRoot
    ) {
        $this->sanitizer = new SensitiveDataSanitizer();
        $this->assertOutsidePublicRoot();
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $record = [
            'time' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'level' => $level,
            'message' => $this->sanitizer->sanitizeMessage($message),
            'context' => $this->sanitizer->sanitizeContext($context),
        ];

        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"level":"error","message":"json log encoding failed"}';
        }

        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new ConfigException('Log directory could not be created.');
        }

        if (!is_writable($dir)) {
            throw new ConfigException('Log directory is not writable.');
        }

        if (is_file($this->path) && !is_writable($this->path)) {
            throw new ConfigException('Log file is not writable.');
        }

        if (error_log($json . PHP_EOL, 3, $this->path) !== true) {
            throw new ConfigException('Log write failed.');
        }
    }

    private function shouldLog(string $level): bool
    {
        $current = self::LEVELS[$this->level] ?? self::LEVELS['info'];
        $requested = self::LEVELS[$level] ?? self::LEVELS['info'];

        return $requested >= $current;
    }

    private function assertOutsidePublicRoot(): void
    {
        if (!$this->isAbsolutePath($this->path)) {
            throw new ConfigException('Log path must be an absolute path.');
        }

        $realPublic = realpath($this->publicRoot);
        if ($realPublic === false) {
            return;
        }

        $realDir = realpath(dirname($this->path));
        $dirPath = $realDir === false ? dirname($this->path) : $realDir;
        $public = $this->normalizePath($realPublic);
        $dir = $this->normalizePath($dirPath);

        if (strncmp($dir, $public, strlen($public)) === 0) {
            throw new ConfigException('Log path must be outside the public directory.');
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function normalizePath(string $path): string
    {
        return strtolower(rtrim(str_replace('\\', '/', $path), '/') . '/');
    }
}
