<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

use DandyNotification\Exception\MailTransportException;

final class FakeMailTransport implements MailTransportInterface
{
    /** @var list<OutboundMail> */
    private array $sent = [];
    private bool $failNext = false;
    private bool $throwNext = false;

    public function failNext(): void
    {
        $this->failNext = true;
    }

    public function throwNext(): void
    {
        $this->throwNext = true;
    }

    public function send(OutboundMail $mail): MailTransportResult
    {
        if ($this->throwNext) {
            $this->throwNext = false;
            throw new MailTransportException('fake transport failure');
        }

        if ($this->failNext) {
            $this->failNext = false;

            return MailTransportResult::failure();
        }

        $this->sent[] = $mail;

        return MailTransportResult::success('fake-' . count($this->sent));
    }

    public function sentCount(): int
    {
        return count($this->sent);
    }

    public function lastMail(): ?OutboundMail
    {
        return $this->sent === [] ? null : $this->sent[array_key_last($this->sent)];
    }
}
