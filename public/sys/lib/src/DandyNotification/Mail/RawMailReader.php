<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

use DandyNotification\Exception\ConfigException;
use DandyNotification\Exception\EmptyMailRejectedException;
use RuntimeException;

final class RawMailReader
{
    public function __construct(private readonly int $maxBytes)
    {
        if ($this->maxBytes < 1) {
            throw new ConfigException('empty_mail.max_bytes must be a positive integer.');
        }
    }

    public function readFromStdin(): string
    {
        $stream = fopen('php://stdin', 'rb');
        if (!is_resource($stream)) {
            throw new RuntimeException('stdin stream could not be opened.');
        }

        return $this->readFromStream($stream);
    }

    /**
     * @param resource $stream
     */
    public function readFromStream($stream): string
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('mail input stream is invalid.');
        }

        $rawMail = stream_get_contents($stream, $this->maxBytes + 1);
        if (!is_string($rawMail)) {
            throw new RuntimeException('mail input stream could not be read.');
        }

        if (strlen($rawMail) > $this->maxBytes) {
            throw new EmptyMailRejectedException('mail_too_large');
        }

        if ($rawMail === '') {
            throw new EmptyMailRejectedException('empty_input');
        }

        return $rawMail;
    }
}
