<?php
declare(strict_types=1);

namespace DandyNotification\Validation;

use DandyNotification\Exception\ValidationException;

final class EmailAddress
{
    public static function normalize(string $email): string
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || strlen($normalized) > 254 || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Email address is invalid.');
        }

        return $normalized;
    }
}
