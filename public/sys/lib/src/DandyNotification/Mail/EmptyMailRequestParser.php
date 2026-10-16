<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

use DandyNotification\Exception\EmptyMailRejectedException;
use DandyNotification\Exception\ValidationException;
use DandyNotification\Validation\EmailAddress;

final class EmptyMailRequestParser
{
    public function __construct(
        private readonly MailHeaderParser $headerParser = new MailHeaderParser(),
        private readonly AutoReplyDetector $autoReplyDetector = new AutoReplyDetector()
    ) {
    }

    public function parse(string $rawMail): EmptyMailRequest
    {
        $headers = $this->headerParser->parse($rawMail);
        $autoReplyReason = $this->autoReplyDetector->detect($headers);
        if ($autoReplyReason !== null) {
            throw new EmptyMailRejectedException($autoReplyReason);
        }

        $email = $this->parseFrom($headers->value('from'));
        $castId = $this->parseSubject($headers->value('subject'));

        return new EmptyMailRequest($email, $castId);
    }

    private function parseFrom(?string $from): string
    {
        if ($from === null || trim($from) === '') {
            throw new EmptyMailRejectedException('missing_from');
        }

        if (preg_match('/[\r\n\x00-\x1F\x7F]/', $from) === 1) {
            throw new EmptyMailRejectedException('invalid_from');
        }

        $from = trim($from);
        if (substr_count($from, '<') > 0 || substr_count($from, '>') > 0) {
            if (substr_count($from, '<') !== 1 || substr_count($from, '>') !== 1) {
                throw new EmptyMailRejectedException('multiple_from_addresses');
            }

            $start = strpos($from, '<');
            $end = strpos($from, '>');
            if ($start === false || $end === false || $start > $end || trim(substr($from, $end + 1)) !== '') {
                throw new EmptyMailRejectedException('invalid_from');
            }

            $this->assertSafeDisplayNamePrefix(substr($from, 0, $start));
            $candidate = trim(substr($from, $start + 1, $end - $start - 1));
        } else {
            if (str_contains($from, ',') || str_contains($from, ';') || str_contains($from, ':')) {
                throw new EmptyMailRejectedException('multiple_from_addresses');
            }

            $candidate = $from;
        }

        if ($candidate === ''
            || strlen($candidate) > 254
            || preg_match('/[^\x21-\x7E]/', $candidate) === 1
            || str_contains($candidate, ',')
            || str_contains($candidate, ';')
            || str_contains($candidate, ':')
        ) {
            throw new EmptyMailRejectedException('invalid_from');
        }

        try {
            return EmailAddress::normalize($candidate);
        } catch (ValidationException) {
            throw new EmptyMailRejectedException('invalid_from');
        }
    }

    private function assertSafeDisplayNamePrefix(string $prefix): void
    {
        if (str_contains($prefix, '<') || str_contains($prefix, '>') || str_contains($prefix, '@')) {
            throw new EmptyMailRejectedException('multiple_from_addresses');
        }

        $inQuote = false;
        $escaped = false;
        $length = strlen($prefix);
        for ($index = 0; $index < $length; $index++) {
            $character = $prefix[$index];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($character === '\\') {
                $escaped = true;
                continue;
            }

            if ($character === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && ($character === ',' || $character === ';' || $character === ':')) {
                throw new EmptyMailRejectedException('multiple_from_addresses');
            }
        }

        if ($inQuote) {
            throw new EmptyMailRejectedException('invalid_from');
        }
    }

    private function parseSubject(?string $subject): int
    {
        if ($subject === null) {
            throw new EmptyMailRejectedException('missing_subject');
        }

        $decoded = trim($this->decodeSubject($subject));
        if ($decoded === '') {
            throw new EmptyMailRejectedException('empty_subject');
        }

        if (preg_match('/^add:([1-9][0-9]*)$/', $decoded, $matches) !== 1) {
            throw new EmptyMailRejectedException('invalid_subject');
        }

        if (!$this->fitsPhpInteger($matches[1])) {
            throw new EmptyMailRejectedException('invalid_cast_id');
        }

        return (int) $matches[1];
    }

    private function decodeSubject(string $subject): string
    {
        $decoded = null;

        if (function_exists('mb_decode_mimeheader')) {
            $previousEncoding = function_exists('mb_internal_encoding') ? mb_internal_encoding() : null;
            if ($previousEncoding !== null) {
                mb_internal_encoding('UTF-8');
            }

            $decodedValue = @mb_decode_mimeheader($subject);
            if ($previousEncoding !== null) {
                mb_internal_encoding($previousEncoding);
            }

            if (is_string($decodedValue) && $decodedValue !== '') {
                $decoded = $decodedValue;
            }
        }

        if ($decoded === null && function_exists('iconv_mime_decode')) {
            $decodedValue = @iconv_mime_decode(
                $subject,
                ICONV_MIME_DECODE_CONTINUE_ON_ERROR,
                'UTF-8'
            );
            if (is_string($decodedValue) && $decodedValue !== '') {
                $decoded = $decodedValue;
            }
        }

        return $this->ensureUtf8($decoded ?? $subject);
    }

    private function ensureUtf8(string $value): string
    {
        if (!function_exists('mb_check_encoding') || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (!function_exists('mb_convert_encoding')) {
            return $value;
        }

        $converted = @mb_convert_encoding(
            $value,
            'UTF-8',
            'UTF-8,ISO-2022-JP,SJIS-win,SJIS,EUC-JP'
        );

        return is_string($converted) ? $converted : $value;
    }

    private function fitsPhpInteger(string $digits): bool
    {
        $max = (string) PHP_INT_MAX;
        $length = strlen($digits);

        return $length < strlen($max)
            || ($length === strlen($max) && strcmp($digits, $max) <= 0);
    }
}
