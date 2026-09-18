<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

interface MailTransportInterface
{
    public function send(OutboundMail $mail): MailTransportResult;
}
