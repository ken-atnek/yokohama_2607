<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Repository\SubscriberRepository;
use DandyNotification\Repository\SubscriptionRepository;
use DandyNotification\Repository\SuppressionRepository;
use DandyNotification\Validation\EmailAddress;

final class RegistrationService
{
    public function __construct(
        private readonly SubscriberRepository $subscribers,
        private readonly SubscriptionRepository $subscriptions,
        private readonly SuppressionRepository $suppressions
    ) {
    }

    public function normalizeEmail(string $email): string
    {
        return EmailAddress::normalize($email);
    }

    public function findSubscriberByEmail(string $email): ?array
    {
        return $this->subscribers->findByEmail($this->normalizeEmail($email));
    }

    public function findSubscription(int $subscriberId, string $shopId, int $castId): ?array
    {
        return $this->subscriptions->findBySubscriberShopCast($subscriberId, $shopId, $castId);
    }

    public function isSuppressedEmail(string $email): bool
    {
        return $this->suppressions->findActiveByEmail($this->normalizeEmail($email)) !== null;
    }
}
