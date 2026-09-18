<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

use DandyNotification\Exception\RenderFailureException;

final class OutboundMail
{
    public function __construct(
        private readonly string $recipient,
        private readonly string $subject,
        private readonly string $body,
        private readonly string $mailType
    ) {
        foreach ([
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'mail_type' => $this->mailType,
        ] as $label => $value) {
            if ($value === '' || preg_match('/[\r\n]/', $value) === 1) {
                throw new RenderFailureException($label . ' is invalid.');
            }
        }

        if (!filter_var($this->recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RenderFailureException('recipient is invalid.');
        }
    }

    public function recipient(): string
    {
        return $this->recipient;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function mailType(): string
    {
        return $this->mailType;
    }
}
