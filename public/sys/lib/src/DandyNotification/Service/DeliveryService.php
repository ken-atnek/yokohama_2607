<?php
declare(strict_types=1);

namespace DandyNotification\Service;

use DandyNotification\Validation\TemplateDataValidator;

final class DeliveryService
{
    public function __construct(private readonly TemplateDataValidator $templateDataValidator)
    {
    }

    public function buildTemplateDataSnapshot(string $mailType, array $data): string
    {
        return $this->templateDataValidator->encode($mailType, $data);
    }
}
