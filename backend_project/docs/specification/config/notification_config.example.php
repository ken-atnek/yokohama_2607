<?php

declare(strict_types=1);

return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'database_name_here',
        'user' => 'database_user_here',
        'password' => 'database_password_here',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'environment' => 'local',
        'timezone' => 'Asia/Tokyo',
        'session_name' => 'DANDY_NOTIFICATION_SESSION',
        'session_cookie' => [
            // Local HTTP: domain='', secure=false, samesite='Lax'.
            // Production HTTPS: production domain, secure=true, samesite='Lax'.
            // ConfigLoader rejects secure=false when environment='production'.
            'domain' => '',
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
            'lifetime' => 86400,
        ],
    ],
    'log' => [
        'path' => 'E:/web_data/hot_point_kobe/private_logs/yokohama_2607/notification.log',
        'level' => 'info',
    ],
    'feature' => [
        'notification_empty_mail_receive_enabled' => false,
        'notification_confirmation_enabled' => false,
        'notification_settings_enabled' => false,
        'notification_event_enqueue_enabled' => false,
        'notification_delivery_create_enabled' => false,
        'notification_send_enabled' => false,
        'notification_dry_run' => true,
        'notification_legacy_migration_enabled' => false,
        'notification_admin_enabled' => false,
        'notification_public_state_sync_enabled' => false,
        'notification_public_state_reconciliation_enabled' => false,
    ],
    'empty_mail' => [
        // Production initial operating value for empty mail receive.
        // Keep feature flags off until production activation.
        'max_bytes' => 262144,
        'allowed_shop_ids' => ['yh-dandy', 'yh-clubd', 'yh-mrd'],
        'verification_token_ttl_seconds' => 3600,
        'rate_limit' => [
            'email' => [
                'window_seconds' => 3600,
                'max_attempts' => 12,
            ],
            'email_cast' => [
                'window_seconds' => 3600,
                'max_attempts' => 5,
            ],
            'global' => [
                'window_seconds' => 300,
                'max_attempts' => 200,
            ],
        ],
    ],
    'notification_url' => [
        // production:
        // https://www.dandy-g.jp/wgmailsys/confirm/
        //
        // staging:
        // https://demo-yokohama.dandy-g.jp/wgmailsys/confirm/
        //
        // local:
        // http://127.0.0.1:3000/wgmailsys/confirm/
        'confirmation_base_url' => null,

        // production:
        // https://www.dandy-g.jp/wgmailsys/settings/
        //
        // staging:
        // https://demo-yokohama.dandy-g.jp/wgmailsys/settings/
        //
        // local:
        // http://127.0.0.1:3000/wgmailsys/settings/
        // notification_settings_enabled=true時は必須。
        'settings_base_url' => null,

    ],
    'mail' => [
        // Phase11 production mail auth:
        // Header From remains no-reply@dandy-g.jp in NativeMailTransport.
        // Envelope From / Return-Path uses this value when real sending is enabled.
        'envelope_from' => 'bounce@dandy-g.jp',
    ],
    'confirmation' => [
        // Production initial consent/version identifier.
        // notification_confirmation_enabled=true時は必須。
        'consent_version' => '2026-08-31',
    ],
    'settings' => [
        // notification_settings_enabled=false時はnull許容。
        // notification_settings_enabled=true時は以下の固定値のみ許可。
        'manage_token_ttl_seconds' => 3600,
        'session_lifetime_seconds' => 86400,
    ],
    'public_state' => [
        'notification_public_state_reconciliation_batch_size' => 100,
    ],
];
