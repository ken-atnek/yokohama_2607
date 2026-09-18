<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

use DandyNotification\Exception\EmptyMailRejectedException;

final class MailHeaderParser
{
    private const MAX_HEADER_BYTES = 65536;
    private const MAX_HEADER_LINE_BYTES = 4096;
    private const SINGLE_VALUE_HEADERS = ['from', 'subject'];

    public function parse(string $rawMail): ParsedMailHeaders
    {
        $headerBlock = $this->extractHeaderBlock($rawMail);
        if ($headerBlock === '') {
            throw new EmptyMailRejectedException('empty_headers');
        }

        if (strlen($headerBlock) > self::MAX_HEADER_BYTES) {
            throw new EmptyMailRejectedException('headers_too_large');
        }

        if (str_contains($headerBlock, "\0")) {
            throw new EmptyMailRejectedException('headers_contain_nul');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $headerBlock);
        $lines = explode("\n", $normalized);
        $headers = [];
        $currentName = null;
        $currentValue = '';

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (strlen($line) > self::MAX_HEADER_LINE_BYTES) {
                throw new EmptyMailRejectedException('header_line_too_long');
            }

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line) === 1) {
                throw new EmptyMailRejectedException('header_control_char');
            }

            if ($line[0] === ' ' || $line[0] === "\t") {
                if ($currentName === null) {
                    throw new EmptyMailRejectedException('malformed_header_continuation');
                }

                $currentValue .= ' ' . trim($line);
                continue;
            }

            if ($currentName !== null) {
                $this->appendHeader($headers, $currentName, $currentValue);
            }

            if (!str_contains($line, ':')) {
                throw new EmptyMailRejectedException('malformed_header_line');
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            if (preg_match('/^[a-z0-9!#$%&\'*+\-.^_`|~]+$/', $name) !== 1) {
                throw new EmptyMailRejectedException('malformed_header_name');
            }

            $currentName = $name;
            $currentValue = ltrim($value);
        }

        if ($currentName !== null) {
            $this->appendHeader($headers, $currentName, $currentValue);
        }

        foreach (self::SINGLE_VALUE_HEADERS as $name) {
            if (count($headers[$name] ?? []) > 1) {
                throw new EmptyMailRejectedException('duplicate_' . $name);
            }
        }

        return new ParsedMailHeaders($headers);
    }

    private function extractHeaderBlock(string $rawMail): string
    {
        $positions = [];
        foreach (["\r\n\r\n", "\n\n", "\r\r"] as $separator) {
            $position = strpos($rawMail, $separator);
            if ($position !== false) {
                $positions[] = $position;
            }
        }

        if ($positions === []) {
            throw new EmptyMailRejectedException('missing_header_body_separator');
        }

        return substr($rawMail, 0, min($positions));
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function appendHeader(array &$headers, string $name, string $value): void
    {
        $headers[$name] ??= [];
        $headers[$name][] = trim($value);
    }
}
