<?php
declare(strict_types=1);

namespace DandyNotification\Cli;

use DandyNotification\ApplicationContext;
use DandyNotification\Exception\ConfigException;
use DandyNotification\Exception\EmptyMailRejectedException;
use DandyNotification\Exception\PdoDiagnosticException;
use DandyNotification\Mail\EmptyMailRequestParser;
use DandyNotification\Mail\RawMailReader;
use DandyNotification\Service\EmptyMailRegistrationService;
use DandyNotification\Support\RequestId;
use JsonException;
use PDOException;
use Throwable;

final class EmptyMailReceiveCommand
{
    private const EXIT_OK = 0;
    private const EXIT_SOFTWARE = 70;
    private const EXIT_TEMPFAIL = 75;
    private const EXIT_CONFIG = 78;

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ?RawMailReader $reader = null,
        private readonly EmptyMailRequestParser $parser = new EmptyMailRequestParser()
    ) {
    }

    public function run(): int
    {
        $requestId = RequestId::generate();

        try {
            $flags = $this->context->featureFlags();
            if (!$flags->notificationEmptyMailReceiveEnabled()) {
                $this->context->logger()->info('empty mail receive skipped', [
                    'request_id' => $requestId,
                    'reason' => 'feature_disabled',
                ]);

                return self::EXIT_OK;
            }

            if (!$flags->notificationDeliveryCreateEnabled()) {
                $this->context->logger()->warning('empty mail receive skipped', [
                    'request_id' => $requestId,
                    'reason' => 'delivery_create_disabled',
                ]);

                return self::EXIT_OK;
            }

            $emptyMailConfig = $this->context->config()->emptyMailConfig();
            $reader = $this->reader ?? new RawMailReader((int) $emptyMailConfig['max_bytes']);

            try {
                $rawMail = $reader->readFromStdin();
                $request = $this->parser->parse($rawMail);
            } catch (EmptyMailRejectedException $exception) {
                return $this->finishParserRejection($requestId, $exception->reason());
            }

            $service = new EmptyMailRegistrationService(
                $this->context->pdo(),
                $this->context->repositories(),
                $this->context->services()->deliveries(),
                $emptyMailConfig
            );

            $result = $service->handle($request, $requestId);
            $this->context->logger()->info('empty mail receive completed', [
                'request_id' => $requestId,
                'result' => $result,
                'cast_id' => $request->castId(),
                'email_hash_prefix' => substr(bin2hex(hash('sha256', $request->email(), true)), 0, 16),
            ]);

            return self::EXIT_OK;
        } catch (PDOException $exception) {
            $this->rollbackIfNeeded();
            $this->writeGenericStderr('notification empty mail temporary failure');
            $this->tryLogError($requestId, 'db_or_delivery_failure', $this->pdoDiagnosticContext($exception));

            return self::EXIT_TEMPFAIL;
        } catch (ConfigException $exception) {
            $this->rollbackIfNeeded();
            $this->writeGenericStderr('notification empty mail config error');

            return self::EXIT_CONFIG;
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            $this->writeGenericStderr('notification empty mail internal error');
            $this->tryLogError($requestId, 'internal_error');

            return self::EXIT_SOFTWARE;
        }
    }

    private function finishParserRejection(string $requestId, string $reason): int
    {
        $auditSaved = false;
        try {
            $pdo = $this->context->pdo();
            $pdo->beginTransaction();
            $this->context->repositories()->auditLogs()->insert([
                'action' => $this->isIgnoredReason($reason) ? 'empty_mail_ignored' : 'empty_mail_rejected',
                'result' => $this->isIgnoredReason($reason) ? 'success' : 'failure',
                'request_id' => $requestId,
                'metadata' => $this->metadata([
                    'reason_code' => $reason,
                    'reason' => $reason,
                ]),
            ]);
            $pdo->commit();
            $auditSaved = true;
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
        }

        try {
            $this->context->logger()->info('empty mail receive ignored', [
                'request_id' => $requestId,
                'reason_code' => $reason,
                'audit_saved' => $auditSaved,
            ]);
        } catch (Throwable) {
        }

        return self::EXIT_OK;
    }

    private function isIgnoredReason(string $reason): bool
    {
        return str_starts_with($reason, 'precedence_')
            || in_array($reason, [
                'mailer_daemon',
                'postmaster',
                'empty_return_path',
                'auto_submitted',
                'auto_response_header',
                'loop_header',
                'delivery_status_report',
                'bounce_subject',
            ], true);
    }

    private function rollbackIfNeeded(): void
    {
        try {
            $pdo = $this->context->existingPdo();
            if ($pdo !== null && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $diagnosticContext
     */
    private function tryLogError(string $requestId, string $reason, array $diagnosticContext = []): void
    {
        try {
            $this->context->logger()->error('empty mail receive failed', [
                'request_id' => $requestId,
                'reason' => $reason,
            ] + $diagnosticContext);
        } catch (Throwable) {
        }
    }

    /**
     * @return array{stage: string, exception_class: string, sqlstate: ?string, driver_code: int|string|null}
     */
    private function pdoDiagnosticContext(PDOException $exception): array
    {
        if ($exception instanceof PdoDiagnosticException) {
            return [
                'stage' => $exception->stage(),
                'exception_class' => $exception->exceptionClass(),
                'sqlstate' => $exception->sqlState(),
                'driver_code' => $exception->driverCode(),
            ];
        }

        return [
            'stage' => $this->context->existingPdo() === null ? 'db_connection' : 'unknown',
            'exception_class' => $exception::class,
            'sqlstate' => PdoDiagnosticException::extractSqlState($exception),
            'driver_code' => PdoDiagnosticException::extractDriverCode($exception),
        ];
    }

    private function writeGenericStderr(string $message): void
    {
        if (defined('STDERR')) {
            fwrite(STDERR, $message . PHP_EOL);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadata(array $metadata): string
    {
        try {
            return json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return '{"reason":"metadata_encoding_failed"}';
        }
    }
}
