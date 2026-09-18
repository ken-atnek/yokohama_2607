<?php
declare(strict_types=1);

namespace DandyNotification\Mail;

final class AutoReplyDetector
{
    public function detect(ParsedMailHeaders $headers): ?string
    {
        $from = strtolower($headers->value('from') ?? '');
        if (str_contains($from, 'mailer-daemon')) {
            return 'mailer_daemon';
        }

        if (preg_match('/(?:^|[<\s])postmaster(?:@|[>\s]|$)/i', $from) === 1) {
            return 'postmaster';
        }

        foreach ($headers->values('return-path') as $returnPath) {
            if (trim($returnPath) === '<>') {
                return 'empty_return_path';
            }
        }

        foreach ($headers->values('auto-submitted') as $autoSubmitted) {
            $autoSubmitted = strtolower(trim($autoSubmitted));
            if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
                return 'auto_submitted';
            }
        }

        foreach ($headers->values('precedence') as $precedence) {
            $precedence = strtolower(trim($precedence));
            if (in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)) {
                return 'precedence_' . $precedence;
            }
        }

        foreach (['x-auto-response-suppress', 'x-autoreply', 'x-autorespond', 'x-auto-reply'] as $headerName) {
            foreach ($headers->values($headerName) as $value) {
                return 'auto_response_header';
            }
        }

        foreach ($headers->values('x-loop') as $value) {
            return 'loop_header';
        }

        foreach ($headers->values('content-type') as $contentType) {
            $contentType = strtolower($contentType);
            if (str_contains($contentType, 'delivery-status')
                || str_contains($contentType, 'multipart/report')
                || str_contains($contentType, 'report-type=delivery-status')
            ) {
                return 'delivery_status_report';
            }
        }

        $subject = strtolower($headers->value('subject') ?? '');
        foreach ([
            'delivery status notification',
            'undelivered mail returned to sender',
            'mail delivery failed',
            'failure notice',
        ] as $bounceSubject) {
            if (str_contains($subject, $bounceSubject)) {
                return 'bounce_subject';
            }
        }

        return null;
    }
}
