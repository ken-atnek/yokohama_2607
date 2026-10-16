<?php
declare(strict_types=1);

namespace DandyNotification;

use DandyNotification\Service\DeliveryService;
use DandyNotification\Service\MailService;
use DandyNotification\Service\NotificationEventService;
use DandyNotification\Service\NotificationSettingsService;
use DandyNotification\Service\PublicStateSyncService;
use DandyNotification\Service\RegistrationConfirmationService;
use DandyNotification\Service\RegistrationService;
use DandyNotification\Service\ScheduleDeliveryCreationService;
use DandyNotification\Service\ScheduleEventService;
use DandyNotification\Service\SettingsAuthenticationService;
use DandyNotification\Service\SettingsLinkRequestService;
use DandyNotification\Service\SettingsService;
use DandyNotification\Service\UnsubscribeService;

final class ServiceSet
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly RegistrationConfirmationService $registrationConfirmation,
        private readonly SettingsService $settings,
        private readonly SettingsAuthenticationService $settingsAuthentication,
        private readonly SettingsLinkRequestService $settingsLinkRequests,
        private readonly NotificationSettingsService $notificationSettings,
        private readonly UnsubscribeService $unsubscribes,
        private readonly NotificationEventService $notificationEvents,
        private readonly DeliveryService $deliveries,
        private readonly MailService $mail,
        private readonly PublicStateSyncService $publicStateSync,
        private readonly ScheduleEventService $scheduleEvents,
        private readonly ScheduleDeliveryCreationService $scheduleDeliveryCreation
    ) {
    }

    public function registration(): RegistrationService
    {
        return $this->registration;
    }

    public function registrationConfirmation(): RegistrationConfirmationService
    {
        return $this->registrationConfirmation;
    }

    public function settings(): SettingsService
    {
        return $this->settings;
    }

    public function settingsAuthentication(): SettingsAuthenticationService
    {
        return $this->settingsAuthentication;
    }

    public function settingsLinkRequests(): SettingsLinkRequestService
    {
        return $this->settingsLinkRequests;
    }

    public function notificationSettings(): NotificationSettingsService
    {
        return $this->notificationSettings;
    }

    public function unsubscribes(): UnsubscribeService
    {
        return $this->unsubscribes;
    }

    public function notificationEvents(): NotificationEventService
    {
        return $this->notificationEvents;
    }

    public function deliveries(): DeliveryService
    {
        return $this->deliveries;
    }

    public function mail(): MailService
    {
        return $this->mail;
    }

    public function publicStateSync(): PublicStateSyncService
    {
        return $this->publicStateSync;
    }

    public function scheduleEvents(): ScheduleEventService
    {
        return $this->scheduleEvents;
    }

    public function scheduleDeliveryCreation(): ScheduleDeliveryCreationService
    {
        return $this->scheduleDeliveryCreation;
    }
}
