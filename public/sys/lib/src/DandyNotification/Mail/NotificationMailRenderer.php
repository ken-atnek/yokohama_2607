<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

use DandyNotification\Config\AppConfig;
use DandyNotification\Exception\RenderFailureException;
use DandyNotification\Validation\TemplateDataValidator;
use JsonException;
use Throwable;

final class NotificationMailRenderer
{
    private const OFFICIAL_SITE_URL = 'https://dandy-g.jp/';

    private const SHOP_ORDER = [
        'yh-dandy' => 1,
        'yh-clubd' => 2,
        'yh-mrd' => 3,
    ];

    public function __construct(
        private readonly AppConfig $config,
        private readonly TemplateDataValidator $validator
    ) {
    }

    public function render(array $deliveryRows, ?string $verificationUrl, ?string $settingsUrl): OutboundMail
    {
        if ($deliveryRows === []) {
            throw new RenderFailureException('delivery rows are empty.');
        }

        try {
            $mailType = (string) ($deliveryRows[0]['mail_type'] ?? '');
            $recipient = (string) ($deliveryRows[0]['recipient_email_snapshot'] ?? '');

            return match ($mailType) {
                'verify_email' => $this->renderVerifyEmail($recipient, $mailType, $this->decodeSnapshot($deliveryRows[0]), $verificationUrl),
                'registration_complete' => $this->renderSimpleCastMail(
                    $recipient,
                    $mailType,
                    $this->decodeSnapshot($deliveryRows[0]),
                    '本登録が完了し、女の子の登録を行いました',
                    '出勤お知らせメールの本登録が完了しました。',
                    '下記の女の子を登録しました。'
                ),
                'subscription_added' => $this->renderSimpleCastMail(
                    $recipient,
                    $mailType,
                    $this->decodeSnapshot($deliveryRows[0]),
                    '女の子を追加しました',
                    '出勤お知らせメールへ下記の女の子を追加しました。',
                    null
                ),
                'subscription_removed' => $this->renderSimpleCastMail(
                    $recipient,
                    $mailType,
                    $this->decodeSnapshot($deliveryRows[0]),
                    '女の子の登録を解除しました',
                    '出勤お知らせメールより、下記の女の子の登録を解除しました。',
                    null
                ),
                'settings_link' => $this->renderSettingsLink($recipient, $mailType, $this->decodeSnapshot($deliveryRows[0]), $settingsUrl),
                'unsubscribe_all' => $this->renderUnsubscribeAll($recipient, $mailType, $this->decodeSnapshot($deliveryRows[0])),
                'schedule_update' => $this->renderScheduleUpdate($recipient, $mailType, $deliveryRows),
                default => throw new RenderFailureException('unsupported mail_type.'),
            };
        } catch (RenderFailureException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RenderFailureException('mail render failed.', 0, $exception);
        }
    }

    public function buildVerificationUrl(string $rawToken): string
    {
        return $this->buildTokenUrl((string) $this->config->notificationUrlConfig()['confirmation_base_url'], $rawToken);
    }

    public function buildSettingsUrl(string $rawToken): string
    {
        return $this->buildTokenUrl((string) $this->config->notificationUrlConfig()['settings_base_url'], $rawToken);
    }

    private function renderVerifyEmail(
        string $recipient,
        string $mailType,
        array $data,
        ?string $verificationUrl
    ): OutboundMail {
        $this->validator->validate($mailType, $data);
        if (!is_string($verificationUrl) || $verificationUrl === '') {
            throw new RenderFailureException('verification URL is required.');
        }

        $body = implode("\n", $this->withCommonFooter([
            '出勤お知らせメールの仮登録を受け付けました。',
            '',
            '下記の内容で登録を受け付けています。',
            '',
            '------------------------',
            '店舗：' . $data['shop_name'],
            '女の子：' . $data['cast_name'],
            '------------------------',
            '',
            '本登録を完了するには、下記URLを開いて確認してください。',
            $verificationUrl,
            '',
            'このURLは、発行から1時間有効です。',
            'このURLは1回だけ使用できます。',
            '',
            '本登録を完了するまで、出勤お知らせメールは配信されません。',
            '',
            'このメールに心当たりがない場合は、URLを開かず、そのまま破棄してください。',
            '',
            '------------------------',
            '',
            '【出勤お知らせメールについて】',
            '',
            '・登録した女の子の出勤予定が決定または変更された際にお知らせします。',
            '・複数の女の子を登録できます。',
            '・本登録後、メールを受信しない時間帯を設定できます。',
            '・登録中の女の子の確認や解除ができます。',
            '',
            '横浜ダンディーグループ',
            $this->siteRootUrl(),
        ]));

        return new OutboundMail($recipient, '仮登録を受け付けました', $body, $mailType);
    }

    private function renderSimpleCastMail(
        string $recipient,
        string $mailType,
        array $data,
        string $subject,
        string $intro,
        ?string $secondLine
    ): OutboundMail {
        $this->validator->validate($mailType, $data);
        $lines = [
            $intro,
        ];

        if ($secondLine !== null) {
            $lines[] = $secondLine;
        }

        $lines = array_merge($lines, [
            '',
            '------------------------',
            '店舗：' . $data['shop_name'],
            '女の子：' . $data['cast_name'],
            '------------------------',
            '',
        ]);

        if ($mailType === 'registration_complete') {
            $lines = array_merge($lines, [
                '今後、対象となる出勤予定が決定または変更された際に、出勤お知らせメールをお送りします。',
                '',
            ]);
        }

        if (in_array($mailType, ['registration_complete', 'subscription_added'], true)) {
            $lines = array_merge($lines, [
                '受信時間の設定、登録中の女の子の確認、登録解除は、下記ページからお手続きください。',
                '',
                $this->settingsRequestUrl(),
                '',
            ]);
        } else {
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            '横浜ダンディーグループ',
            $this->siteRootUrl(),
        ]);

        return new OutboundMail($recipient, $subject, implode("\n", $this->withCommonFooter($lines)), $mailType);
    }

    private function renderSettingsLink(
        string $recipient,
        string $mailType,
        array $data,
        ?string $settingsUrl
    ): OutboundMail {
        $this->validator->validate($mailType, $data);
        if (!is_string($settingsUrl) || $settingsUrl === '') {
            throw new RenderFailureException('settings URL is required.');
        }

        $body = implode("\n", $this->withCommonFooter([
            '出勤お知らせメールの設定ページへのリンク発行要求を受け付けました。',
            '',
            '下記URLから設定ページを開いてください。',
            $settingsUrl,
            '',
            'このURLは、発行から1時間有効です。',
            'このURLは1回だけ使用できます。',
            '',
            '心当たりがない場合は、このメールを破棄してください。',
            '',
            '横浜ダンディーグループ',
            $this->siteRootUrl(),
        ]));

        return new OutboundMail($recipient, '出勤お知らせメールの設定リンクをお送りします', $body, $mailType);
    }

    private function renderUnsubscribeAll(string $recipient, string $mailType, array $data): OutboundMail
    {
        $this->validator->validate($mailType, $data);
        $body = implode("\n", $this->withCommonFooter([
            '出勤お知らせメールの全登録解除が完了しました。',
            '',
            '現在登録中だった女の子への出勤お知らせメールはすべて解除されています。',
            '再度利用する場合は、女の子詳細ページから登録してください。',
            '',
            '心当たりがない場合は、お手数ですが店舗までお問い合わせください。',
            '',
            '横浜ダンディーグループ',
            $this->siteRootUrl(),
        ]));

        return new OutboundMail($recipient, '出勤お知らせメールの登録をすべて解除しました', $body, $mailType);
    }

    private function renderScheduleUpdate(string $recipient, string $mailType, array $deliveryRows): OutboundMail
    {
        $snapshots = [];
        foreach ($deliveryRows as $row) {
            $data = $this->decodeSnapshot($row);
            $this->validator->validate($mailType, $data);
            $snapshots[] = $data;
        }

        $buckets = $this->scheduleBuckets($snapshots);
        $uniqueCasts = [];
        foreach ($buckets as $bucket) {
            $uniqueCasts[$bucket['shop_id'] . ':' . $bucket['cast_id']] = true;
        }

        $lines = [
            '登録中の女の子の出勤情報が更新されました。',
            '',
            '更新キャスト数：' . count($uniqueCasts) . '名',
            '',
            '------------------------',
        ];

        foreach ($buckets as $bucket) {
            $lines[] = $bucket['shop_name'];
            $lines[] = '・' . $bucket['cast_name'];
            $lines[] = '　出勤日：' . $bucket['schedule_date'];
            $lines[] = '　更新理由：' . $bucket['display_reason'];
            if ($bucket['next_schedules'] !== []) {
                $lines[] = '　次回出勤予定：';
                foreach ($bucket['next_schedules'] as $schedule) {
                    $lines[] = '　- ' . $schedule['schedule_date'] . ' ' . $schedule['start_time'] . '～' . $schedule['end_time'];
                }
            }
            $lines[] = '------------------------';
        }

        $lines = array_merge($lines, [
            '',
            '最新のスケジュールや女の子の詳細は、オフィシャルサイトからご確認ください。',
            '',
            $this->siteRootUrl(),
            '',
            '【ご予約・お問い合わせ】',
            '',
            '横浜ダンディー',
            '045-242-8030',
            '',
            'クラブダンディー',
            '045-243-2468',
            '',
            'ミスターダンディー',
            '045-243-9555',
            '',
            '------------------------',
            '',
            '受信時間の変更、登録中の女の子の確認、登録解除はこちらからお手続きください。',
            '',
            $this->settingsRequestUrl(),
            '',
            '横浜ダンディーグループ',
        ]);

        return new OutboundMail($recipient, 'DANDY-出勤お知らせメール【更新情報】', implode("\n", $this->withCommonFooter($lines)), $mailType);
    }

    private function decodeSnapshot(array $deliveryRow): array
    {
        $json = $deliveryRow['template_data_snapshot'] ?? null;
        if (!is_string($json) || $json === '') {
            throw new RenderFailureException('template_data_snapshot is missing.');
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RenderFailureException('template_data_snapshot JSON decode failed.', 0, $exception);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new RenderFailureException('template_data_snapshot is invalid.');
        }

        return $data;
    }

    private function buildTokenUrl(string $baseUrl, string $rawToken): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $rawToken) !== 1) {
            throw new RenderFailureException('raw token is invalid.');
        }

        $this->assertUsableAbsoluteUrl($baseUrl, 'token base URL');

        return $baseUrl . '?token=' . rawurlencode($rawToken);
    }

    private function siteRootUrl(): string
    {
        return self::OFFICIAL_SITE_URL;
    }

    private function settingsRequestUrl(): string
    {
        return $this->urlOrigin($this->settingsBaseUrl()) . 'wgmailsys/settings-request/';
    }

    private function settingsBaseUrl(): string
    {
        $baseUrl = $this->config->notificationUrlConfig()['settings_base_url'] ?? null;
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RenderFailureException('settings base URL is required.');
        }

        $this->assertUsableAbsoluteUrl($baseUrl, 'settings base URL');

        return $baseUrl;
    }

    private function withCommonFooter(array $lines): array
    {
        return array_merge($lines, [''], $this->commonFooterLines());
    }

    private function commonFooterLines(): array
    {
        return [
            '個人情報の取扱いについて',
            '',
            'ご登録いただいたメールアドレスは、出勤お知らせメールの配信、本サービスの登録・解除・設定、および運営上必要な連絡にのみ利用します。',
            '広告メール、他サービスのご案内、マーケティング目的には利用しません。',
            '法令に基づく場合等を除き、第三者へ提供しません。',
        ];
    }

    private function urlOrigin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !is_string($parts['scheme'] ?? null)
            || !is_string($parts['host'] ?? null)
        ) {
            throw new RenderFailureException('notification URL origin is invalid.');
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port . '/';
    }

    private function assertUsableAbsoluteUrl(string $url, string $label): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !is_string($parts['scheme'] ?? null)
            || !is_string($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new RenderFailureException($label . ' is invalid.');
        }
    }

    private function scheduleBuckets(array $snapshots): array
    {
        $buckets = [];
        foreach ($snapshots as $data) {
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            if (!is_string($summary['display_reason'] ?? null) || $summary['display_reason'] === '') {
                throw new RenderFailureException('schedule_update display reason is missing.');
            }
            $displayReason = (string) $summary['display_reason'];

            foreach ($data['events'] as $event) {
                $key = $event['shop_id'] . ':' . $event['cast_id'] . ':' . $event['schedule_date'];
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'shop_id' => (string) $event['shop_id'],
                        'cast_id' => (int) $event['cast_id'],
                        'shop_name' => (string) $event['shop_name'],
                        'cast_name' => (string) $event['cast_name'],
                        'schedule_date' => (string) $event['schedule_date'],
                        'display_reason' => $displayReason,
                        'next_schedules' => $this->normalizeNextSchedules($data['next_schedules'] ?? []),
                    ];
                }
            }
        }

        usort($buckets, static function (array $a, array $b): int {
            $shopCompare = (self::SHOP_ORDER[$a['shop_id']] ?? 99) <=> (self::SHOP_ORDER[$b['shop_id']] ?? 99);
            if ($shopCompare !== 0) {
                return $shopCompare;
            }

            $castCompare = $a['cast_id'] <=> $b['cast_id'];
            if ($castCompare !== 0) {
                return $castCompare;
            }

            return strcmp($a['schedule_date'], $b['schedule_date']);
        });

        return $buckets;
    }

    private function normalizeNextSchedules(mixed $nextSchedules): array
    {
        if (!is_array($nextSchedules)) {
            return [];
        }

        $normalized = [];
        foreach ($nextSchedules as $schedule) {
            if (!is_array($schedule)) {
                continue;
            }

            $normalized[] = [
                'schedule_date' => (string) ($schedule['schedule_date'] ?? ''),
                'start_time' => (string) ($schedule['start_time'] ?? ''),
                'end_time' => (string) ($schedule['end_time'] ?? ''),
            ];
        }

        return $normalized;
    }
}
