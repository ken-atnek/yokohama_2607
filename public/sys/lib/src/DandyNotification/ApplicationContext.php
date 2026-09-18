<?php

declare(strict_types=1);

namespace DandyNotification;

use DandyNotification\Config\AppConfig;
use DandyNotification\Config\FeatureFlags;
use DandyNotification\Database\PdoFactory;
use DandyNotification\Log\JsonLogger;
use DandyNotification\Repository\AuditRepository;
use DandyNotification\Repository\DeliveryRepository;
use DandyNotification\Repository\EventRepository;
use DandyNotification\Repository\GirlRepository;
use DandyNotification\Repository\SubscriberRepository;
use DandyNotification\Repository\SubscriptionRepository;
use DandyNotification\Repository\SuppressionRepository;
use DandyNotification\Repository\TokenRepository;
use DandyNotification\Security\CsrfTokenManager;
use DandyNotification\Security\SessionManager;
use DandyNotification\Service\DeliveryService;
use DandyNotification\Service\MailService;
use DandyNotification\Service\NotificationEventService;
use DandyNotification\Service\NotificationSettingsService;
use DandyNotification\Service\PublicStateSyncService;
use DandyNotification\Service\RegistrationConfirmationService;
use DandyNotification\Service\RegistrationService;
use DandyNotification\Service\ScheduleDeliveryCreationService;
use DandyNotification\Service\ScheduleDeliveryPlanningService;
use DandyNotification\Service\ScheduleEventDecisionService;
use DandyNotification\Service\ScheduleEventService;
use DandyNotification\Service\SettingsAuthenticationService;
use DandyNotification\Service\SettingsLinkRequestService;
use DandyNotification\Service\SettingsService;
use DandyNotification\Service\UnsubscribeService;
use DandyNotification\Validation\TemplateDataValidator;
use PDO;

final class ApplicationContext
{
    private ?PDO $pdo = null;
    private ?RepositorySet $repositories = null;
    private ?ServiceSet $services = null;

    public function __construct(
        private readonly AppConfig $config,
        private readonly FeatureFlags $featureFlags,
        private readonly JsonLogger $logger,
        private readonly PdoFactory $pdoFactory
    ) {}

    public function config(): AppConfig
    {
        return $this->config;
    }

    public function featureFlags(): FeatureFlags
    {
        return $this->featureFlags;
    }

    public function logger(): JsonLogger
    {
        return $this->logger;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->pdoFactory->create();
        }

        return $this->pdo;
    }

    public function existingPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function repositories(): RepositorySet
    {
        if ($this->repositories === null) {
            $pdo = $this->pdo();
            $this->repositories = new RepositorySet(
                new SubscriberRepository($pdo),
                new SubscriptionRepository($pdo),
                new TokenRepository($pdo),
                new EventRepository($pdo),
                new DeliveryRepository($pdo),
                new SuppressionRepository($pdo),
                new AuditRepository($pdo),
                new GirlRepository($pdo)
            );
        }

        return $this->repositories;
    }

    public function services(): ServiceSet
    {
        if ($this->services === null) {
            $session = new SessionManager($this->config->sessionConfig());
            $csrf = new CsrfTokenManager($session);
            $validator = new TemplateDataValidator();
            $repositories = $this->repositories();
            $settingsService = new SettingsService($session, $csrf);
            $deliveryService = new DeliveryService($validator);
            $scheduleEventDecisionService = new ScheduleEventDecisionService();
            $scheduleDeliveryPlanningService = new ScheduleDeliveryPlanningService();
            $notificationEventService = new NotificationEventService();
            $settingsAuthenticationService = new SettingsAuthenticationService(
                $this->pdo(),
                $repositories,
                $settingsService,
                $this->featureFlags,
                $this->config,
                $this->logger
            );

            $this->services = new ServiceSet(
                new RegistrationService(
                    $repositories->subscribers(),
                    $repositories->subscriptions(),
                    $repositories->suppressions()
                ),
                new RegistrationConfirmationService(
                    $this->pdo(),
                    $repositories,
                    $deliveryService,
                    $settingsService,
                    $this->featureFlags,
                    $this->config,
                    $this->logger
                ),
                $settingsService,
                $settingsAuthenticationService,
                new SettingsLinkRequestService(
                    $this->pdo(),
                    $repositories,
                    $deliveryService,
                    $settingsService,
                    $settingsAuthenticationService,
                    $this->config,
                    $this->featureFlags
                ),
                new NotificationSettingsService(
                    $this->pdo(),
                    $repositories,
                    $settingsAuthenticationService
                ),
                new UnsubscribeService(
                    $this->pdo(),
                    $repositories,
                    $deliveryService,
                    $settingsService,
                    $settingsAuthenticationService
                ),
                $notificationEventService,
                $deliveryService,
                new MailService($this->featureFlags, $this->logger),
                new PublicStateSyncService(
                    $this->pdo(),
                    $repositories,
                    $this->logger
                ),
                new ScheduleEventService(
                    $this->pdo(),
                    $repositories,
                    $this->featureFlags,
                    $this->logger,
                    $scheduleEventDecisionService,
                    $notificationEventService
                ),
                new ScheduleDeliveryCreationService(
                    $this->pdo(),
                    $repositories,
                    $deliveryService,
                    $scheduleDeliveryPlanningService,
                    $scheduleEventDecisionService
                )
            );
        }

        return $this->services;
    }
}
