<?php
declare(strict_types=1);

namespace DandyNotification\Config;

use DandyNotification\Exception\ConfigException;

final class ConfigLoader
{
    private const ENV_CONFIG_PATH = 'DANDY_NOTIFICATION_CONFIG';
    private const ALLOWED_EMPTY_MAIL_SHOP_IDS = ['yh-dandy', 'yh-clubd', 'yh-mrd'];
    private const VERIFICATION_TOKEN_TTL_SECONDS = 3600;
    private const SETTINGS_SESSION_LIFETIME_SECONDS = 86400;
    private const PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_DEFAULT = 100;
    private const PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_MIN = 1;
    private const PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_MAX = 1000;
    private const DELIVERY_CREATE_BATCH_SIZE_DEFAULT = 100;
    private const DELIVERY_CREATE_BATCH_SIZE_MIN = 1;
    private const DELIVERY_CREATE_BATCH_SIZE_MAX = 500;
    private const DELIVERY_SEND_BATCH_SIZE_DEFAULT = 100;
    private const DELIVERY_SEND_BATCH_SIZE_MIN = 1;
    private const DELIVERY_SEND_BATCH_SIZE_MAX = 100;
    private const DELIVERY_SEND_STALE_THRESHOLD_SECONDS_DEFAULT = 1800;
    private const DELIVERY_SEND_STALE_THRESHOLD_SECONDS_MIN = 1;

    public function __construct(private readonly string $publicRoot)
    {
    }

    public function load(?string $path = null): AppConfig
    {
        $path = $path ?: getenv(self::ENV_CONFIG_PATH);
        if (!is_string($path) || trim($path) === '') {
            throw new ConfigException(
                self::ENV_CONFIG_PATH . ' is not set. Notification features stopped safely.'
            );
        }

        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            throw new ConfigException('Notification config file was not found.');
        }

        if ($this->isUnderPublicRoot($realPath)) {
            throw new ConfigException('Notification config file must be outside the public directory.');
        }

        $loaded = require $realPath;
        if (!is_array($loaded)) {
            throw new ConfigException('Notification config file must return an array.');
        }

        $values = array_replace_recursive($this->defaults(), $loaded);
        if (isset($loaded['empty_mail'])
            && is_array($loaded['empty_mail'])
            && array_key_exists('allowed_shop_ids', $loaded['empty_mail'])
        ) {
            $values['empty_mail']['allowed_shop_ids'] = $loaded['empty_mail']['allowed_shop_ids'];
        }
        if (isset($values['delivery_create']) && is_array($values['delivery_create'])) {
            $values['delivery_create'] = $this->normalizeDeliveryCreateConfig($values['delivery_create']);
        }
        if (isset($values['delivery_send']) && is_array($values['delivery_send'])) {
            $values['delivery_send'] = $this->normalizeDeliverySendConfig($values['delivery_send']);
        }
        if (isset($values['notification_url']) && is_array($values['notification_url'])) {
            $values['notification_url'] = [
                'confirmation_base_url' => $values['notification_url']['confirmation_base_url'] ?? null,
                'settings_base_url' => $values['notification_url']['settings_base_url'] ?? null,
            ];
        }
        $this->validate($values);

        return new AppConfig($values);
    }

    private function defaults(): array
    {
        return [
            'database' => [
                'host' => '',
                'port' => 3306,
                'name' => '',
                'user' => '',
                'password' => '',
                'charset' => 'utf8mb4',
            ],
            'app' => [
                'environment' => 'production',
                'timezone' => 'Asia/Tokyo',
                'session_name' => 'DANDY_NOTIFICATION_SESSION',
                'session_cookie' => [
                    'domain' => 'dandy-g.jp',
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'lifetime' => 86400,
                ],
            ],
            'log' => [
                'path' => '',
                'level' => 'info',
            ],
            'feature' => FeatureFlags::defaults()->all(),
            'empty_mail' => [
                'max_bytes' => null,
                'allowed_shop_ids' => self::ALLOWED_EMPTY_MAIL_SHOP_IDS,
                'verification_token_ttl_seconds' => self::VERIFICATION_TOKEN_TTL_SECONDS,
                'rate_limit' => [
                    'email' => [
                        'window_seconds' => null,
                        'max_attempts' => null,
                    ],
                    'email_cast' => [
                        'window_seconds' => null,
                        'max_attempts' => null,
                    ],
                    'global' => [
                        'window_seconds' => null,
                        'max_attempts' => null,
                    ],
                ],
            ],
            'notification_url' => [
                'confirmation_base_url' => null,
                'settings_base_url' => null,
            ],
            'mail' => [
                'envelope_from' => null,
            ],
            'confirmation' => [
                'consent_version' => null,
            ],
            'settings' => [
                'manage_token_ttl_seconds' => self::VERIFICATION_TOKEN_TTL_SECONDS,
                'session_lifetime_seconds' => self::SETTINGS_SESSION_LIFETIME_SECONDS,
            ],
            'public_state' => [
                'notification_public_state_reconciliation_batch_size' => self::PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_DEFAULT,
            ],
            'delivery_create' => [
                'notification_delivery_create_batch_size' => self::DELIVERY_CREATE_BATCH_SIZE_DEFAULT,
            ],
            'delivery_send' => [
                'notification_send_batch_size' => self::DELIVERY_SEND_BATCH_SIZE_DEFAULT,
                'notification_send_stale_threshold_seconds' => self::DELIVERY_SEND_STALE_THRESHOLD_SECONDS_DEFAULT,
            ],
        ];
    }

    private function validate(array $values): void
    {
        foreach (['database', 'app', 'log', 'feature', 'empty_mail', 'notification_url', 'mail', 'confirmation', 'settings', 'public_state', 'delivery_create', 'delivery_send'] as $section) {
            if (!isset($values[$section]) || !is_array($values[$section])) {
                throw new ConfigException($section . ' section must be an array.');
            }
        }

        if (!isset($values['app']['session_cookie']) || !is_array($values['app']['session_cookie'])) {
            throw new ConfigException('app.session_cookie section must be an array.');
        }

        $featureFlags = FeatureFlags::fromArray($values['feature']);
        $this->validateEmptyMailConfig(
            $values['empty_mail'],
            $featureFlags->notificationEmptyMailReceiveEnabled()
        );

        foreach (['host', 'name', 'user', 'password', 'charset'] as $key) {
            if (!array_key_exists($key, $values['database'])) {
                throw new ConfigException('Missing database.' . $key . ' in notification config.');
            }
        }

        if ((string) $values['database']['host'] === '') {
            throw new ConfigException('database.host is required.');
        }

        if ((string) $values['database']['name'] === '') {
            throw new ConfigException('database.name is required.');
        }

        if ((string) $values['database']['user'] === '') {
            throw new ConfigException('database.user is required.');
        }

        if ((string) $values['database']['charset'] !== 'utf8mb4') {
            throw new ConfigException('database.charset must be utf8mb4.');
        }

        if (!is_int($values['database']['port']) && !ctype_digit((string) $values['database']['port'])) {
            throw new ConfigException('database.port must be an integer.');
        }

        if ((int) $values['database']['port'] < 1 || (int) $values['database']['port'] > 65535) {
            throw new ConfigException('database.port is invalid.');
        }

        if ((string) $values['log']['path'] === '') {
            throw new ConfigException('log.path is required.');
        }

        $logPath = (string) $values['log']['path'];
        if (!$this->isAbsolutePath($logPath)) {
            throw new ConfigException('log.path must be an absolute path.');
        }

        if ($this->isUnderPublicRoot(dirname($logPath))) {
            throw new ConfigException('log.path must be outside the public directory.');
        }

        if (!in_array((string) $values['app']['timezone'], timezone_identifiers_list(), true)) {
            throw new ConfigException('app.timezone is invalid.');
        }

        $environment = (string) $values['app']['environment'];
        if (!in_array($environment, ['local', 'staging', 'production'], true)) {
            throw new ConfigException('app.environment is invalid.');
        }

        $this->validateSessionConfig($values['app']['session_name'], $values['app']['session_cookie'], $environment);
        $this->validateConfirmationConfig(
            $values['notification_url'],
            $values['confirmation'],
            $featureFlags->notificationConfirmationEnabled(),
            $environment
        );
        $this->validateSettingsConfig(
            $values['notification_url'],
            $values['settings'],
            $featureFlags->notificationSettingsEnabled(),
            $environment
        );
        $this->validateMailConfig($values['mail'], $environment);
        $this->validatePublicStateConfig($values['public_state']);
        $this->validateDeliveryCreateConfig($values['delivery_create']);
        $this->validateDeliverySendConfig($values['delivery_send']);

        foreach (['debug', 'info', 'warning', 'error'] as $level) {
            if ((string) $values['log']['level'] === $level) {
                return;
            }
        }

        throw new ConfigException('log.level is invalid.');
    }

    private function validateConfirmationConfig(
        array $notificationUrl,
        array $confirmation,
        bool $confirmationEnabled,
        string $environment
    ): void {
        foreach (['confirmation_base_url', 'settings_base_url'] as $key) {
            if (!array_key_exists($key, $notificationUrl)) {
                throw new ConfigException('notification_url.' . $key . ' is required.');
            }
        }

        if (!array_key_exists('consent_version', $confirmation)) {
            throw new ConfigException('confirmation.consent_version is required.');
        }

        $this->validateConfirmationBaseUrl(
            $notificationUrl['confirmation_base_url'],
            $environment,
            $confirmationEnabled
        );
        $this->validateConsentVersion($confirmation['consent_version'], $confirmationEnabled);
    }

    private function validateSettingsConfig(
        array $notificationUrl,
        array $settings,
        bool $settingsEnabled,
        string $environment
    ): void {
        foreach (['manage_token_ttl_seconds', 'session_lifetime_seconds'] as $key) {
            if (!array_key_exists($key, $settings)) {
                throw new ConfigException('settings.' . $key . ' is required.');
            }
        }

        if ($settingsEnabled || $settings['manage_token_ttl_seconds'] !== null) {
            if ($settings['manage_token_ttl_seconds'] !== self::VERIFICATION_TOKEN_TTL_SECONDS) {
                throw new ConfigException('settings.manage_token_ttl_seconds must be 3600.');
            }
        }

        if ($settingsEnabled || $settings['session_lifetime_seconds'] !== null) {
            if ($settings['session_lifetime_seconds'] !== self::SETTINGS_SESSION_LIFETIME_SECONDS) {
                throw new ConfigException('settings.session_lifetime_seconds must be 86400.');
            }
        }

        $this->validateSettingsBaseUrl(
            $notificationUrl['settings_base_url'],
            $environment,
            $settingsEnabled
        );
    }

    private function validateSessionConfig(mixed $sessionName, array $cookie, string $environment): void
    {
        if (!is_string($sessionName)
            || preg_match('/[\r\n]/', $sessionName) === 1
            || preg_match('/^[A-Za-z][A-Za-z0-9_]{1,127}$/', $sessionName) !== 1
        ) {
            throw new ConfigException('app.session_name is invalid.');
        }

        foreach (['domain', 'path'] as $key) {
            if (!array_key_exists($key, $cookie) || !is_string($cookie[$key])) {
                throw new ConfigException('app.session_cookie.' . $key . ' must be string.');
            }

            if (preg_match('/[\r\n]/', $cookie[$key]) === 1) {
                throw new ConfigException('app.session_cookie.' . $key . ' must not contain line breaks.');
            }
        }

        if ($cookie['path'] === '') {
            throw new ConfigException('app.session_cookie.path must not be empty.');
        }

        if (!array_key_exists('secure', $cookie) || !is_bool($cookie['secure'])) {
            throw new ConfigException('app.session_cookie.secure must be boolean.');
        }

        if (!array_key_exists('httponly', $cookie) || !is_bool($cookie['httponly'])) {
            throw new ConfigException('app.session_cookie.httponly must be boolean.');
        }

        $sameSite = (string) ($cookie['samesite'] ?? '');
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new ConfigException('app.session_cookie.samesite is invalid.');
        }

        if ($sameSite === 'None' && $cookie['secure'] !== true) {
            throw new ConfigException('app.session_cookie.secure must be true when SameSite=None.');
        }

        if ($environment === 'production' && $cookie['secure'] !== true) {
            throw new ConfigException('app.session_cookie.secure must be true in production.');
        }

        if ($environment === 'production' && $cookie['domain'] !== 'dandy-g.jp') {
            throw new ConfigException('app.session_cookie.domain must be dandy-g.jp in production.');
        }

        if ($environment === 'staging' && ($cookie['domain'] !== '' || $cookie['secure'] !== true)) {
            throw new ConfigException('app.session_cookie must be secure host-only in staging.');
        }

        if ($environment === 'local' && ($cookie['domain'] !== '' || $cookie['secure'] !== false)) {
            throw new ConfigException('app.session_cookie must be insecure host-only in local.');
        }

        if (!is_int($cookie['lifetime'] ?? null) || $cookie['lifetime'] < 0) {
            throw new ConfigException('app.session_cookie.lifetime must be a non-negative integer.');
        }
    }

    private function validatePublicStateConfig(array $publicState): void
    {
        $key = 'notification_public_state_reconciliation_batch_size';
        if (!array_key_exists($key, $publicState)) {
            throw new ConfigException('public_state.' . $key . ' is required.');
        }

        $value = $publicState[$key];
        if (!is_int($value)
            || $value < self::PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_MIN
            || $value > self::PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_MAX
        ) {
            throw new ConfigException(
                'public_state.' . $key . ' must be an integer from '
                . self::PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_MIN
                . ' to '
                . self::PUBLIC_STATE_RECONCILIATION_BATCH_SIZE_MAX
                . '.'
            );
        }
    }

    private function validateDeliveryCreateConfig(array $deliveryCreate): void
    {
        $key = 'notification_delivery_create_batch_size';
        if (!array_key_exists($key, $deliveryCreate)) {
            throw new ConfigException('delivery_create.' . $key . ' is required.');
        }

        $value = $deliveryCreate[$key];
        if (!is_int($value)
            || $value < self::DELIVERY_CREATE_BATCH_SIZE_MIN
            || $value > self::DELIVERY_CREATE_BATCH_SIZE_MAX
        ) {
            throw new ConfigException(
                'delivery_create.' . $key . ' must be an integer from '
                . self::DELIVERY_CREATE_BATCH_SIZE_MIN
                . ' to '
                . self::DELIVERY_CREATE_BATCH_SIZE_MAX
                . '.'
            );
        }
    }

    private function normalizeDeliveryCreateConfig(array $deliveryCreate): array
    {
        $key = 'notification_delivery_create_batch_size';
        $value = $deliveryCreate[$key] ?? self::DELIVERY_CREATE_BATCH_SIZE_DEFAULT;
        if (!is_int($value)) {
            $deliveryCreate[$key] = self::DELIVERY_CREATE_BATCH_SIZE_DEFAULT;

            return $deliveryCreate;
        }

        if ($value < self::DELIVERY_CREATE_BATCH_SIZE_MIN) {
            $deliveryCreate[$key] = self::DELIVERY_CREATE_BATCH_SIZE_DEFAULT;

            return $deliveryCreate;
        }

        if ($value > self::DELIVERY_CREATE_BATCH_SIZE_MAX) {
            $deliveryCreate[$key] = self::DELIVERY_CREATE_BATCH_SIZE_MAX;
        }

        return $deliveryCreate;
    }

    private function validateDeliverySendConfig(array $deliverySend): void
    {
        $batchKey = 'notification_send_batch_size';
        if (!array_key_exists($batchKey, $deliverySend)) {
            throw new ConfigException('delivery_send.' . $batchKey . ' is required.');
        }

        $batchSize = $deliverySend[$batchKey];
        if (!is_int($batchSize)
            || $batchSize < self::DELIVERY_SEND_BATCH_SIZE_MIN
            || $batchSize > self::DELIVERY_SEND_BATCH_SIZE_MAX
        ) {
            throw new ConfigException(
                'delivery_send.' . $batchKey . ' must be an integer from '
                . self::DELIVERY_SEND_BATCH_SIZE_MIN
                . ' to '
                . self::DELIVERY_SEND_BATCH_SIZE_MAX
                . '.'
            );
        }

        $staleKey = 'notification_send_stale_threshold_seconds';
        if (!array_key_exists($staleKey, $deliverySend)) {
            throw new ConfigException('delivery_send.' . $staleKey . ' is required.');
        }

        $staleThreshold = $deliverySend[$staleKey];
        if (!is_int($staleThreshold) || $staleThreshold < self::DELIVERY_SEND_STALE_THRESHOLD_SECONDS_MIN) {
            throw new ConfigException('delivery_send.' . $staleKey . ' must be a positive integer.');
        }
    }

    private function validateMailConfig(array $mail, string $environment): void
    {
        if (!array_key_exists('envelope_from', $mail)) {
            throw new ConfigException('mail.envelope_from is required.');
        }

        $value = $mail['envelope_from'];
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) || !$this->isSafeEnvelopeFrom($value)) {
            throw new ConfigException('mail.envelope_from is invalid.');
        }

        if ($environment === 'production' && $value !== 'bounce@dandy-g.jp') {
            throw new ConfigException('mail.envelope_from is invalid for production.');
        }
    }

    private function isSafeEnvelopeFrom(string $value): bool
    {
        if (str_starts_with($value, '-')
            || preg_match('/[\r\n\0 \t]/', $value) === 1
            || filter_var($value, FILTER_VALIDATE_EMAIL) !== $value
        ) {
            return false;
        }

        return preg_match(
            '/\A[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+\z/',
            $value
        ) === 1;
    }

    private function normalizeDeliverySendConfig(array $deliverySend): array
    {
        $batchKey = 'notification_send_batch_size';
        $batchSize = $deliverySend[$batchKey] ?? self::DELIVERY_SEND_BATCH_SIZE_DEFAULT;
        if (!is_int($batchSize) || $batchSize < self::DELIVERY_SEND_BATCH_SIZE_MIN) {
            $deliverySend[$batchKey] = self::DELIVERY_SEND_BATCH_SIZE_DEFAULT;
        } elseif ($batchSize > self::DELIVERY_SEND_BATCH_SIZE_MAX) {
            $deliverySend[$batchKey] = self::DELIVERY_SEND_BATCH_SIZE_MAX;
        }

        $staleKey = 'notification_send_stale_threshold_seconds';
        $staleThreshold = $deliverySend[$staleKey] ?? self::DELIVERY_SEND_STALE_THRESHOLD_SECONDS_DEFAULT;
        if (!is_int($staleThreshold) || $staleThreshold < self::DELIVERY_SEND_STALE_THRESHOLD_SECONDS_MIN) {
            $deliverySend[$staleKey] = self::DELIVERY_SEND_STALE_THRESHOLD_SECONDS_DEFAULT;
        }

        return $deliverySend;
    }

    private function validateEmptyMailConfig(array $emptyMail, bool $receiveEnabled): void
    {
        if (!array_key_exists('max_bytes', $emptyMail)) {
            throw new ConfigException('empty_mail.max_bytes is required.');
        }

        $this->validateNullablePositiveInt(
            $emptyMail['max_bytes'],
            'empty_mail.max_bytes',
            $receiveEnabled
        );

        if (!isset($emptyMail['allowed_shop_ids']) || !is_array($emptyMail['allowed_shop_ids'])) {
            throw new ConfigException('empty_mail.allowed_shop_ids must be an array.');
        }

        if ($emptyMail['allowed_shop_ids'] === []) {
            throw new ConfigException('empty_mail.allowed_shop_ids must not be empty.');
        }

        $seenShopIds = [];
        foreach ($emptyMail['allowed_shop_ids'] as $shopId) {
            if (!is_string($shopId)
                || !in_array($shopId, self::ALLOWED_EMPTY_MAIL_SHOP_IDS, true)
                || isset($seenShopIds[$shopId])
            ) {
                throw new ConfigException('empty_mail.allowed_shop_ids contains an invalid shop_id.');
            }

            $seenShopIds[$shopId] = true;
        }

        if (($emptyMail['verification_token_ttl_seconds'] ?? null) !== self::VERIFICATION_TOKEN_TTL_SECONDS) {
            throw new ConfigException('empty_mail.verification_token_ttl_seconds must be 3600.');
        }

        if (!isset($emptyMail['rate_limit']) || !is_array($emptyMail['rate_limit'])) {
            throw new ConfigException('empty_mail.rate_limit section must be an array.');
        }

        foreach (['email', 'email_cast', 'global'] as $scope) {
            if (!isset($emptyMail['rate_limit'][$scope]) || !is_array($emptyMail['rate_limit'][$scope])) {
                throw new ConfigException('empty_mail.rate_limit.' . $scope . ' section must be an array.');
            }

            $this->validateNullablePositiveInt(
                $emptyMail['rate_limit'][$scope]['window_seconds'] ?? null,
                'empty_mail.rate_limit.' . $scope . '.window_seconds',
                $receiveEnabled
            );
            $this->validateNullablePositiveInt(
                $emptyMail['rate_limit'][$scope]['max_attempts'] ?? null,
                'empty_mail.rate_limit.' . $scope . '.max_attempts',
                $receiveEnabled
            );
        }
    }

    private function validateNullablePositiveInt(mixed $value, string $path, bool $required): void
    {
        if ($value === null && !$required) {
            return;
        }

        if (!is_int($value) || $value < 1) {
            throw new ConfigException($path . ' must be a positive integer.');
        }
    }

    private function validateConfirmationBaseUrl(mixed $value, string $environment, bool $required): void
    {
        $this->validateWgMailSysBaseUrl(
            $value,
            $environment,
            $required,
            'notification_url.confirmation_base_url',
            '/wgmailsys/confirm/'
        );
    }

    private function validateSettingsBaseUrl(mixed $value, string $environment, bool $required): void
    {
        $this->validateWgMailSysBaseUrl(
            $value,
            $environment,
            $required,
            'notification_url.settings_base_url',
            '/wgmailsys/settings/'
        );
    }

    private function validateWgMailSysBaseUrl(
        mixed $value,
        string $environment,
        bool $required,
        string $configPath,
        string $requiredPath
    ): void {
        if ($value === null && !$required) {
            return;
        }

        $parts = $this->parseAbsoluteUrl($value, $configPath);
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');

        if (!str_ends_with((string) $value, '/')) {
            throw new ConfigException($configPath . ' must end with slash.');
        }

        if (isset($parts['query'])) {
            throw new ConfigException($configPath . ' must not contain query.');
        }

        if ($path !== $requiredPath) {
            throw new ConfigException($configPath . ' path is invalid.');
        }

        if ($environment === 'production') {
            if ($scheme !== 'https' || $host !== 'www.dandy-g.jp' || array_key_exists('port', $parts)) {
                throw new ConfigException($configPath . ' is invalid for production.');
            }

            return;
        }

        if ($environment === 'staging') {
            if ($scheme !== 'https' || $host !== 'demo-yokohama.dandy-g.jp' || array_key_exists('port', $parts)) {
                throw new ConfigException($configPath . ' is invalid for staging.');
            }

            return;
        }

        if ($scheme !== 'http' || !in_array($host, ['localhost', '127.0.0.1'], true)) {
            throw new ConfigException($configPath . ' is invalid for local.');
        }

        if (!array_key_exists('port', $parts)) {
            throw new ConfigException($configPath . ' local port is required.');
        }
    }

    private function validateConsentVersion(mixed $value, bool $required): void
    {
        if ($value === null && !$required) {
            return;
        }

        if (!is_string($value) || $value === '') {
            throw new ConfigException('confirmation.consent_version must be a non-empty string.');
        }

        if ($this->containsControlChars($value) || strlen($value) > 64) {
            throw new ConfigException('confirmation.consent_version is invalid.');
        }
    }

    private function parseAbsoluteUrl(mixed $value, string $path): array
    {
        if (!is_string($value) || $value === '') {
            throw new ConfigException($path . ' must be a non-empty string.');
        }

        if ($this->containsControlChars($value)) {
            throw new ConfigException($path . ' must not contain control characters.');
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new ConfigException($path . ' must be an absolute URL without credentials or fragment.');
        }

        if (array_key_exists('port', $parts) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) {
            throw new ConfigException($path . ' port is invalid.');
        }

        return $parts;
    }

    private function containsControlChars(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private function isUnderPublicRoot(string $path): bool
    {
        $realPublic = realpath($this->publicRoot);
        if ($realPublic === false) {
            return false;
        }

        $realPath = realpath($path);
        $normalPath = $this->normalizePath($realPath === false ? $path : $realPath);
        $normalPublic = $this->normalizePath($realPublic);

        return strncmp($normalPath, $normalPublic, strlen($normalPublic)) === 0;
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
