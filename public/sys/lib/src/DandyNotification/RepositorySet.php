<?php
declare(strict_types=1);

namespace DandyNotification;

use DandyNotification\Repository\AuditRepository;
use DandyNotification\Repository\DeliveryRepository;
use DandyNotification\Repository\EventRepository;
use DandyNotification\Repository\GirlRepository;
use DandyNotification\Repository\SubscriberRepository;
use DandyNotification\Repository\SubscriptionRepository;
use DandyNotification\Repository\SuppressionRepository;
use DandyNotification\Repository\TokenRepository;

final class RepositorySet
{
    public function __construct(
        private readonly SubscriberRepository $subscribers,
        private readonly SubscriptionRepository $subscriptions,
        private readonly TokenRepository $tokens,
        private readonly EventRepository $events,
        private readonly DeliveryRepository $deliveries,
        private readonly SuppressionRepository $suppressions,
        private readonly AuditRepository $auditLogs,
        private readonly GirlRepository $girls
    ) {
    }

    public function subscribers(): SubscriberRepository
    {
        return $this->subscribers;
    }

    public function subscriptions(): SubscriptionRepository
    {
        return $this->subscriptions;
    }

    public function tokens(): TokenRepository
    {
        return $this->tokens;
    }

    public function events(): EventRepository
    {
        return $this->events;
    }

    public function deliveries(): DeliveryRepository
    {
        return $this->deliveries;
    }

    public function suppressions(): SuppressionRepository
    {
        return $this->suppressions;
    }

    public function auditLogs(): AuditRepository
    {
        return $this->auditLogs;
    }

    public function girls(): GirlRepository
    {
        return $this->girls;
    }
}
