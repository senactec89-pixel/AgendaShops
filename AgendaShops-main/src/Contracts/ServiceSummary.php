<?php
declare(strict_types=1);

namespace AgendaShops\Contracts;

final class ServiceSummary implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $establishmentId,
        public readonly string $name,
        public readonly int $durationMinutes,
        public readonly int $priceInCents,
    ) {
        if (trim($id) === '' || trim($establishmentId) === '' || trim($name) === '' || $durationMinutes <= 0 || $priceInCents < 0) {
            throw new \InvalidArgumentException('Invalid service summary.');
        }
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
