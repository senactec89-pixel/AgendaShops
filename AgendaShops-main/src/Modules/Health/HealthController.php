<?php
declare(strict_types=1);

namespace AgendaShops\Modules\Health;

final class HealthController
{
    public function check(): array
    {
        return [
            'service' => 'agendashops-api',
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }
}
