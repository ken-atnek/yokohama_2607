-- 横浜ダンディーグループ
-- 出勤お知らせメールシステム
-- フェーズ1：新DBテーブル作成DDL
-- 対象: MySQL 8.0.45 / InnoDB / utf8mb4
--
-- 注意:
-- 1. このDDLには DROP TABLE / DELETE / UPDATE を含めない。
-- 2. MySQLのDDLは暗黙コミットされるため、実行前に対象DBを必ず確認する。
-- 3. 日時はUTC保存を前提とする。PHP/CLI/移行処理のDB接続直後に
--    SET time_zone = '+00:00' を実行すること。
-- 4. メールアドレスはアプリケーション側で trim + 小文字化してから保存する。
-- 5. 配信テーブルには、生成済み本文、生トークン、生トークン入りURLを保存しない。
-- 6. メール本文は、送信ワーカーが template_key / template_version /
--    template_data_snapshot からメモリ上で生成する。
-- 7. verify_email / settings_link 等のトークン付きメールは、送信試行の直前に
--    random_bytes() で生トークンを生成し、DBにはSHA-256ハッシュだけ保存する。
--    生トークンおよび認証URLは、DB・ログ・エラー文へ保存しない。
-- 8. トークン付きメールの送信試行が失敗した場合は、その試行で作成した
--    トークンを無効化し、次回試行時に新しいトークンを生成する。

SET NAMES utf8mb4;

-- ============================================================
-- 0. 実行前確認
-- ============================================================

SELECT
    DATABASE() AS target_database,
    VERSION() AS mysql_version,
    @@SESSION.sql_mode AS session_sql_mode,
    @@SESSION.time_zone AS session_time_zone;

SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      't_notification_subscriber',
      't_notification_subscription',
      't_notification_token',
      't_notification_event',
      't_notification_delivery',
      't_notification_suppression',
      't_notification_audit_log'
  )
ORDER BY TABLE_NAME;

-- 上記SELECTで既存テーブルが返る場合は、このDDLを実行せず既存定義を確認すること。

-- ============================================================
-- 1. 登録者
-- ============================================================

CREATE TABLE `t_notification_subscriber` (
    `subscriber_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '登録者ID',
    `email` VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT '正規化済みメールアドレス（trim・小文字化）',
    `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' COMMENT 'pending/active/unsubscribed/suppressed',
    `registration_source` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'empty_mail' COMMENT 'empty_mail/legacy_migration/admin/system',
    `quiet_hours_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '受信停止時間設定 0:無効 1:有効',
    `quiet_start_minute` SMALLINT UNSIGNED NULL COMMENT '受信停止開始（0～1439分）',
    `quiet_end_minute` SMALLINT UNSIGNED NULL COMMENT '受信停止終了（0～1439分）',
    `timezone` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'Asia/Tokyo' COMMENT '表示・受信停止判定用タイムゾーン',
    `consented_at` DATETIME(6) NULL COMMENT '本登録同意日時（UTC）',
    `consent_version` VARCHAR(64) NULL COMMENT '同意文面・規約バージョン',
    `verified_at` DATETIME(6) NULL COMMENT 'メールアドレス認証日時（UTC）',
    `unsubscribed_at` DATETIME(6) NULL COMMENT '全解除日時（UTC）',
    `suppressed_at` DATETIME(6) NULL COMMENT '配信禁止反映日時（UTC）',
    `last_registration_request_at` DATETIME(6) NULL COMMENT '最終空メール受付日時（UTC）',
    `last_settings_token_requested_at` DATETIME(6) NULL COMMENT '最終設定リンク要求日時（UTC）',
    `anonymized_at` DATETIME(6) NULL COMMENT '匿名化日時（UTC）',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '作成日時（UTC）',
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時（UTC）',
    PRIMARY KEY (`subscriber_id`),
    UNIQUE KEY `uq_tnsb_email` (`email`),
    KEY `idx_tnsb_status` (`status`, `updated_at`),
    KEY `idx_tnsb_retention` (`status`, `unsubscribed_at`, `anonymized_at`),
    CONSTRAINT `chk_tnsb_status`
        CHECK (`status` IN ('pending', 'active', 'unsubscribed', 'suppressed')),
    CONSTRAINT `chk_tnsb_source`
        CHECK (`registration_source` IN ('empty_mail', 'legacy_migration', 'admin', 'system')),
    CONSTRAINT `chk_tnsb_quiet_enabled`
        CHECK (`quiet_hours_enabled` IN (0, 1)),
    CONSTRAINT `chk_tnsb_quiet_range`
        CHECK (
            (`quiet_hours_enabled` = 0 AND `quiet_start_minute` IS NULL AND `quiet_end_minute` IS NULL)
            OR
            (
                `quiet_hours_enabled` = 1
                AND `quiet_start_minute` BETWEEN 0 AND 1439
                AND `quiet_end_minute` BETWEEN 0 AND 1439
                AND `quiet_start_minute` <> `quiet_end_minute`
            )
        )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='出勤お知らせメール登録者';

-- ============================================================
-- 2. 女の子購読
-- ============================================================

CREATE TABLE `t_notification_subscription` (
    `subscription_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '購読ID',
    `subscriber_id` BIGINT UNSIGNED NOT NULL COMMENT '登録者ID',
    `shop_id` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '店舗ID',
    `cast_id` BIGINT UNSIGNED NOT NULL COMMENT '女の子ID',
    `shop_name_snapshot` VARCHAR(100) NULL COMMENT '登録時・最終同期時の店舗名',
    `cast_name_snapshot` VARCHAR(100) NULL COMMENT '登録時・最終同期時の女の子名',
    `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' COMMENT 'pending/active/paused/unsubscribed',
    `pause_reason` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'cast_hidden/user_paused/admin_paused',
    `registration_source` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'empty_mail' COMMENT 'empty_mail/legacy_migration/admin/system',
    `requested_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '登録要求日時（UTC）',
    `activated_at` DATETIME(6) NULL COMMENT '有効化日時（UTC）',
    `paused_at` DATETIME(6) NULL COMMENT '一時停止日時（UTC）',
    `unsubscribed_at` DATETIME(6) NULL COMMENT '女の子単位解除日時（UTC）',
    `last_notified_at` DATETIME(6) NULL COMMENT '最終通知送信日時（UTC）',
    `anonymized_at` DATETIME(6) NULL COMMENT '匿名化日時（UTC）',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '作成日時（UTC）',
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時（UTC）',
    PRIMARY KEY (`subscription_id`),
    UNIQUE KEY `uq_tnsp_sub_shop_cast` (`subscriber_id`, `shop_id`, `cast_id`),
    UNIQUE KEY `uq_tnsp_id_subscriber` (`subscription_id`, `subscriber_id`),
    KEY `idx_tnsp_sub_status` (`subscriber_id`, `status`),
    KEY `idx_tnsp_cast_status` (`shop_id`, `cast_id`, `status`),
    KEY `idx_tnsp_pause` (`status`, `pause_reason`, `updated_at`),
    KEY `idx_tnsp_retention` (`status`, `unsubscribed_at`, `anonymized_at`),
    CONSTRAINT `fk_tnsp_subscriber`
        FOREIGN KEY (`subscriber_id`)
        REFERENCES `t_notification_subscriber` (`subscriber_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `chk_tnsp_status`
        CHECK (`status` IN ('pending', 'active', 'paused', 'unsubscribed')),
    CONSTRAINT `chk_tnsp_pause_reason`
        CHECK (`pause_reason` IS NULL OR `pause_reason` IN ('cast_hidden', 'user_paused', 'admin_paused')),
    CONSTRAINT `chk_tnsp_pause_state`
        CHECK (
            (`status` = 'paused' AND `pause_reason` IS NOT NULL)
            OR
            (`status` <> 'paused' AND `pause_reason` IS NULL)
        ),
    CONSTRAINT `chk_tnsp_source`
        CHECK (`registration_source` IN ('empty_mail', 'legacy_migration', 'admin', 'system'))
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='登録者ごとの女の子購読';

-- ============================================================
-- 3. ワンタイムトークン
-- ============================================================

CREATE TABLE `t_notification_token` (
    `token_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'トークンID',
    `subscriber_id` BIGINT UNSIGNED NOT NULL COMMENT '登録者ID',
    `subscription_id` BIGINT UNSIGNED NULL COMMENT '対象購読ID',
    `purpose` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'verify_email/manage_settings/unsubscribe_all/unsubscribe_subscription',
    `token_hash` BINARY(32) NOT NULL COMMENT '生トークンのSHA-256バイナリ',
    `request_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT '処理追跡用リクエストID',
    `expires_at` DATETIME(6) NOT NULL COMMENT '有効期限（UTC）',
    `used_at` DATETIME(6) NULL COMMENT '使用日時（UTC）',
    `revoked_at` DATETIME(6) NULL COMMENT '無効化日時（UTC）',
    `request_ip_hash` BINARY(32) NULL COMMENT '要求元IPのSHA-256バイナリ',
    `metadata` JSON NULL COMMENT '補助情報（個人情報・生トークンは禁止）',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '作成日時（UTC）',
    PRIMARY KEY (`token_id`),
    UNIQUE KEY `uq_tntk_hash` (`token_hash`),
    KEY `idx_tntk_sub_purpose` (`subscriber_id`, `purpose`, `expires_at`),
    KEY `idx_tntk_subscription` (`subscription_id`, `purpose`, `expires_at`),
    KEY `idx_tntk_cleanup` (`expires_at`, `used_at`, `revoked_at`),
    CONSTRAINT `fk_tntk_subscriber`
        FOREIGN KEY (`subscriber_id`)
        REFERENCES `t_notification_subscriber` (`subscriber_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_tntk_subscription`
        FOREIGN KEY (`subscription_id`)
        REFERENCES `t_notification_subscription` (`subscription_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `chk_tntk_purpose`
        CHECK (`purpose` IN ('verify_email', 'manage_settings', 'unsubscribe_all', 'unsubscribe_subscription')),
    CONSTRAINT `chk_tntk_target`
        CHECK (
            (`purpose` IN ('verify_email', 'unsubscribe_subscription') AND `subscription_id` IS NOT NULL)
            OR
            (`purpose` IN ('manage_settings', 'unsubscribe_all') AND `subscription_id` IS NULL)
        ),
    CONSTRAINT `chk_tntk_expiry`
        CHECK (`expires_at` > `created_at`),
    CONSTRAINT `chk_tntk_used_at`
        CHECK (`used_at` IS NULL OR `used_at` >= `created_at`),
    CONSTRAINT `chk_tntk_revoked_at`
        CHECK (`revoked_at` IS NULL OR `revoked_at` >= `created_at`)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='メール認証・設定・解除用ワンタイムトークン';

-- ============================================================
-- 4. 出勤変更イベント
-- ============================================================

CREATE TABLE `t_notification_event` (
    `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '通知イベントID',
    `event_key` BINARY(32) NOT NULL COMMENT 'イベント重複防止用SHA-256バイナリ',
    `event_type` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'schedule_created/schedule_start_changed/schedule_changed_to_off/schedule_changed_to_work',
    `source_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'schedule_save' COMMENT 'schedule_save/reconciliation',
    `shop_id` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '店舗ID',
    `cast_id` BIGINT UNSIGNED NOT NULL COMMENT '女の子ID',
    `shop_name_snapshot` VARCHAR(100) NULL COMMENT 'イベント発生時の店舗名',
    `cast_name_snapshot` VARCHAR(100) NULL COMMENT 'イベント発生時の女の子名',
    `schedule_date` DATE NOT NULL COMMENT '対象出勤日',
    `occurred_at` DATETIME(6) NOT NULL COMMENT '変更確定日時（UTC）',
    `before_payload` JSON NULL COMMENT '変更前スケジュール情報',
    `after_payload` JSON NOT NULL COMMENT '変更後スケジュール情報',
    `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/processed/failed/cancelled',
    `available_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '処理可能日時（UTC）',
    `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'イベント展開試行回数',
    `max_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '最大試行回数',
    `locked_at` DATETIME(6) NULL COMMENT 'ロック取得日時（UTC）',
    `locked_by` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'ワーカー識別子',
    `processed_at` DATETIME(6) NULL COMMENT '配信対象展開完了日時（UTC）',
    `failed_at` DATETIME(6) NULL COMMENT '最終失敗日時（UTC）',
    `last_error` TEXT NULL COMMENT '最終エラー概要（個人情報禁止）',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '作成日時（UTC）',
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時（UTC）',
    PRIMARY KEY (`event_id`),
    UNIQUE KEY `uq_tnev_event_key` (`event_key`),
    KEY `idx_tnev_queue` (`status`, `available_at`, `event_id`),
    KEY `idx_tnev_lock` (`status`, `locked_at`),
    KEY `idx_tnev_cast_date` (`shop_id`, `cast_id`, `schedule_date`, `created_at`),
    KEY `idx_tnev_cleanup` (`status`, `created_at`),
    CONSTRAINT `chk_tnev_type`
        CHECK (`event_type` IN ('schedule_created', 'schedule_start_changed', 'schedule_changed_to_off', 'schedule_changed_to_work')),
    CONSTRAINT `chk_tnev_source`
        CHECK (`source_type` IN ('schedule_save', 'reconciliation')),
    CONSTRAINT `chk_tnev_status`
        CHECK (`status` IN ('pending', 'processing', 'processed', 'failed', 'cancelled')),
    CONSTRAINT `chk_tnev_attempts`
        CHECK (`attempt_count` <= `max_attempts` AND `max_attempts` > 0)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='通知対象となる出勤変更イベント';

-- ============================================================
-- 5. 配信キュー・配信履歴
-- ============================================================
--
-- セキュリティ上の必須運用:
-- ・body_snapshotのような生成済み本文カラムは設けない。
-- ・template_data_snapshotには、本文再生成に必要な非機密データだけを保存する。
-- ・生トークン、生トークン入りURL、認証URL、設定URL、生成済み本文は保存しない。
-- ・verify_email / settings_linkは送信直前に生トークンを生成し、
--   t_notification_token.token_hashへSHA-256ハッシュだけ保存する。
-- ・本文は送信ワーカーのメモリ上で生成し、送信終了後に破棄する。
-- ・一時送信失敗時は、その試行用トークンをrevokedへ変更し、
--   再試行時に別の生トークンを発行する。

CREATE TABLE `t_notification_delivery` (
    `delivery_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配信ID',
    `event_id` BIGINT UNSIGNED NULL COMMENT '出勤変更イベントID（出勤通知のみ）',
    `subscriber_id` BIGINT UNSIGNED NOT NULL COMMENT '登録者ID',
    `subscription_id` BIGINT UNSIGNED NULL COMMENT '対象購読ID',
    `mail_type` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'メール種別',
    `recipient_email_snapshot` VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT '送信先メールアドレスのスナップショット',
    `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued' COMMENT 'queued/sending/deferred/sent/failed/suppressed/cancelled',
    `template_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'メールテンプレート識別子',
    `template_version` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'メールテンプレートバージョン',
    `subject_snapshot` VARCHAR(255) NULL COMMENT '生成済み件名（生トークン・認証URLは禁止）',
    `template_data_snapshot` JSON NOT NULL COMMENT '本文再生成用の非機密テンプレートデータ。生成済み本文、生トークン、認証URL、設定URL、メールアドレス全文は禁止',
    `message_group_key` BINARY(16) NULL COMMENT '2時間集約メールのグループ識別子',
    `available_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '送信可能日時（UTC）',
    `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '送信試行回数',
    `max_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '最大送信試行回数',
    `locked_at` DATETIME(6) NULL COMMENT 'ロック取得日時（UTC）',
    `locked_by` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT '送信ワーカー識別子',
    `provider_message_id` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'MTA・送信処理側のメッセージID',
    `sent_at` DATETIME(6) NULL COMMENT '送信完了日時（UTC）',
    `deferred_at` DATETIME(6) NULL COMMENT '再送延期日時（UTC）',
    `failed_at` DATETIME(6) NULL COMMENT '最終失敗日時（UTC）',
    `suppressed_at` DATETIME(6) NULL COMMENT '配信禁止判定日時（UTC）',
    `cancelled_at` DATETIME(6) NULL COMMENT '取消日時（UTC）',
    `last_error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT '最終エラーコード',
    `last_error_message` TEXT NULL COMMENT '最終エラー概要（個人情報禁止）',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '作成日時（UTC）',
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時（UTC）',
    PRIMARY KEY (`delivery_id`),
    UNIQUE KEY `uq_tndl_event_subscription` (`event_id`, `subscription_id`),
    KEY `idx_tndl_queue` (`status`, `available_at`, `delivery_id`),
    KEY `idx_tndl_subscriber_queue` (`subscriber_id`, `status`, `available_at`),
    KEY `idx_tndl_group` (`message_group_key`, `status`),
    KEY `idx_tndl_lock` (`status`, `locked_at`),
    KEY `idx_tndl_cleanup` (`status`, `created_at`, `sent_at`),
    KEY `idx_tndl_subscription_subscriber` (`subscription_id`, `subscriber_id`),
    CONSTRAINT `fk_tndl_event`
        FOREIGN KEY (`event_id`)
        REFERENCES `t_notification_event` (`event_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_tndl_subscriber`
        FOREIGN KEY (`subscriber_id`)
        REFERENCES `t_notification_subscriber` (`subscriber_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_tndl_subscription_subscriber`
        FOREIGN KEY (`subscription_id`, `subscriber_id`)
        REFERENCES `t_notification_subscription` (`subscription_id`, `subscriber_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `chk_tndl_mail_type`
        CHECK (`mail_type` IN ('verify_email', 'registration_complete', 'subscription_added', 'subscription_removed', 'settings_link', 'unsubscribe_all', 'schedule_update')),
    CONSTRAINT `chk_tndl_status`
        CHECK (`status` IN ('queued', 'sending', 'deferred', 'sent', 'failed', 'suppressed', 'cancelled')),
    CONSTRAINT `chk_tndl_event_relation`
        CHECK (
            (`mail_type` = 'schedule_update' AND `event_id` IS NOT NULL AND `subscription_id` IS NOT NULL)
            OR
            (`mail_type` <> 'schedule_update' AND `event_id` IS NULL)
        ),
    CONSTRAINT `chk_tndl_attempts`
        CHECK (`attempt_count` <= `max_attempts` AND `max_attempts` > 0)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='メール配信キューおよび配信履歴。本文・生トークン・トークン付きURLは保存しない';

-- ============================================================
-- 6. 配信禁止
-- ============================================================

CREATE TABLE `t_notification_suppression` (
    `suppression_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配信禁止ID',
    `subscriber_id` BIGINT UNSIGNED NULL COMMENT '登録者ID（未登録アドレスの場合はNULL）',
    `email` VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT '配信禁止対象メールアドレス',
    `status` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active' COMMENT 'active/released/expired',
    `reason` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'hard_bounce/complaint/invalid_address/manual',
    `source` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'system' COMMENT 'mailer/admin/import/system',
    `starts_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '配信禁止開始日時（UTC）',
    `expires_at` DATETIME(6) NULL COMMENT '配信禁止有効期限（UTC・無期限はNULL）',
    `released_at` DATETIME(6) NULL COMMENT '解除日時（UTC）',
    `last_seen_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '最終検知日時（UTC）',
    `details` JSON NULL COMMENT '補助情報（個人情報・生メールは禁止）',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '作成日時（UTC）',
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時（UTC）',
    PRIMARY KEY (`suppression_id`),
    UNIQUE KEY `uq_tnss_email` (`email`),
    KEY `idx_tnss_subscriber` (`subscriber_id`),
    KEY `idx_tnss_active` (`status`, `expires_at`),
    CONSTRAINT `fk_tnss_subscriber`
        FOREIGN KEY (`subscriber_id`)
        REFERENCES `t_notification_subscriber` (`subscriber_id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `chk_tnss_status`
        CHECK (`status` IN ('active', 'released', 'expired')),
    CONSTRAINT `chk_tnss_reason`
        CHECK (`reason` IN ('hard_bounce', 'complaint', 'invalid_address', 'manual')),
    CONSTRAINT `chk_tnss_source`
        CHECK (`source` IN ('mailer', 'admin', 'import', 'system')),
    CONSTRAINT `chk_tnss_expiry`
        CHECK (`expires_at` IS NULL OR `expires_at` > `starts_at`),
    CONSTRAINT `chk_tnss_release`
        CHECK (
            (`status` = 'released' AND `released_at` IS NOT NULL)
            OR
            (`status` IN ('active', 'expired'))
        )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='ハードバウンス・苦情・無効アドレス等の配信禁止管理';

-- ============================================================
-- 7. 監査ログ
-- ============================================================

CREATE TABLE `t_notification_audit_log` (
    `audit_log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '監査ログID',
    `subscriber_id` BIGINT UNSIGNED NULL COMMENT '登録者ID',
    `subscription_id` BIGINT UNSIGNED NULL COMMENT '購読ID',
    `actor_type` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'user/system/admin/cli/migration',
    `actor_id` VARCHAR(128) NULL COMMENT '管理者ID・ワーカーID等（不要時NULL）',
    `action` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '操作種別',
    `result` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'success' COMMENT 'success/failure',
    `request_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT '処理追跡用リクエストID',
    `email_hash` BINARY(32) NULL COMMENT '正規化メールアドレスのSHA-256バイナリ',
    `shop_id` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT '対象店舗ID',
    `cast_id` BIGINT UNSIGNED NULL COMMENT '対象女の子ID',
    `ip_hash` BINARY(32) NULL COMMENT 'IPアドレスのSHA-256バイナリ',
    `user_agent_hash` BINARY(32) NULL COMMENT 'User-AgentのSHA-256バイナリ',
    `metadata` JSON NULL COMMENT '操作詳細（生トークン・メールアドレス全文は禁止）',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '作成日時（UTC）',
    PRIMARY KEY (`audit_log_id`),
    KEY `idx_tnal_subscriber` (`subscriber_id`, `created_at`),
    KEY `idx_tnal_subscription` (`subscription_id`, `created_at`),
    KEY `idx_tnal_action` (`action`, `result`, `created_at`),
    KEY `idx_tnal_request` (`request_id`, `created_at`),
    KEY `idx_tnal_created` (`created_at`),
    CONSTRAINT `fk_tnal_subscriber`
        FOREIGN KEY (`subscriber_id`)
        REFERENCES `t_notification_subscriber` (`subscriber_id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_tnal_subscription`
        FOREIGN KEY (`subscription_id`)
        REFERENCES `t_notification_subscription` (`subscription_id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `chk_tnal_actor_type`
        CHECK (`actor_type` IN ('user', 'system', 'admin', 'cli', 'migration')),
    CONSTRAINT `chk_tnal_result`
        CHECK (`result` IN ('success', 'failure'))
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='登録・認証・設定変更・解除・移行・管理者操作の監査ログ';

-- ============================================================
-- 8. 実行後確認
-- ============================================================

SELECT
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION,
    TABLE_COMMENT
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      't_notification_subscriber',
      't_notification_subscription',
      't_notification_token',
      't_notification_event',
      't_notification_delivery',
      't_notification_suppression',
      't_notification_audit_log'
  )
ORDER BY TABLE_NAME;

SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    ENFORCED
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      't_notification_subscriber',
      't_notification_subscription',
      't_notification_token',
      't_notification_event',
      't_notification_delivery',
      't_notification_suppression',
      't_notification_audit_log'
  )
ORDER BY TABLE_NAME, CONSTRAINT_TYPE, CONSTRAINT_NAME;

SELECT
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX,
    COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      't_notification_subscriber',
      't_notification_subscription',
      't_notification_token',
      't_notification_event',
      't_notification_delivery',
      't_notification_suppression',
      't_notification_audit_log'
  )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SHOW CREATE TABLE `t_notification_subscriber`;
SHOW CREATE TABLE `t_notification_subscription`;
SHOW CREATE TABLE `t_notification_token`;
SHOW CREATE TABLE `t_notification_event`;
SHOW CREATE TABLE `t_notification_delivery`;
SHOW CREATE TABLE `t_notification_suppression`;
SHOW CREATE TABLE `t_notification_audit_log`;
