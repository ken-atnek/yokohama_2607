<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

use DandyNotification\Exception\MailTransportException;
use Throwable;

final class NativeMailTransport implements MailTransportInterface
{
    private const FROM_DISPLAY_NAME = 'ダンディーグループ';
    private const FROM_ADDRESS = 'no-reply@dandy-g.jp';

    private readonly ?string $envelopeFrom;

    public function __construct(
        private readonly ?\Closure $mailInvoker = null,
        ?string $envelopeFrom = null
    ) {
        $this->envelopeFrom = $this->normalizeEnvelopeFrom($envelopeFrom);
    }

    public function send(OutboundMail $mail): MailTransportResult
    {
        $headers = $this->buildHeaders();
        $subject = $this->encodeSubject($mail->subject());
        $body = $this->encodeBody($mail->body());

        try {
            $result = $this->sendMail($mail->recipient(), $subject, $body, $headers);
        } catch (Throwable $exception) {
            throw new MailTransportException('mail transport threw exception.', 0, $exception);
        }

        if ($result !== true) {
            return MailTransportResult::failure();
        }

        return MailTransportResult::success();
    }

    private function sendMail(string $recipient, string $subject, string $body, string $headers): bool
    {
        $additionalParameters = $this->additionalParameters();
        if ($this->mailInvoker instanceof \Closure) {
            return $additionalParameters === null
                ? (bool) ($this->mailInvoker)($recipient, $subject, $body, $headers)
                : (bool) ($this->mailInvoker)($recipient, $subject, $body, $headers, $additionalParameters);
        }

        return $additionalParameters === null
            ? mail($recipient, $subject, $body, $headers)
            : mail($recipient, $subject, $body, $headers, $additionalParameters);
    }

    private function additionalParameters(): ?string
    {
        if ($this->envelopeFrom === null) {
            return null;
        }

        return '-f' . $this->envelopeFrom;
    }

    private function normalizeEnvelopeFrom(?string $envelopeFrom): ?string
    {
        if ($envelopeFrom === null) {
            return null;
        }

        if ($envelopeFrom === '' || !$this->isSafeEnvelopeFrom($envelopeFrom)) {
            throw new MailTransportException('mail envelope from is invalid.');
        }

        return $envelopeFrom;
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

    public function buildHeaders(): string
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'From: ' . $this->encodeHeaderText(self::FROM_DISPLAY_NAME) . ' <' . self::FROM_ADDRESS . '>',
        ];

        foreach ($headers as $header) {
            if (preg_match('/[\r\n](?!$)/', $header) === 1) {
                throw new MailTransportException('mail header is invalid.');
            }
        }

        return implode("\r\n", $headers);
    }

    public function encodeSubject(string $subject): string
    {
        if ($subject === '' || preg_match('/[\r\n]/', $subject) === 1) {
            throw new MailTransportException('mail subject is invalid.');
        }

        return $this->encodeHeaderText($subject, strlen('Subject: '));
    }

    private function encodeHeaderText(string $value, int $indent = 0): string
    {
        if ($value === '' || preg_match('/[\r\n]/', $value) === 1) {
            throw new MailTransportException('mail header text is invalid.');
        }

        if (!function_exists('mb_encode_mimeheader')) {
            throw new MailTransportException('mail header encoder is unavailable.');
        }

        $encoded = mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n", $indent);
        if ($encoded === ''
            || preg_match('/\r(?!\n)|(?<!\r)\n/', $encoded) === 1
            || preg_match('/\r\n(?![ \t])/', $encoded) === 1
        ) {
            throw new MailTransportException('mail header encoding failed.');
        }

        return $encoded;
    }

    public function encodeBody(string $body): string
    {
        return rtrim(chunk_split(base64_encode($body), 76, "\r\n"), "\r\n");
    }
}
