<?php
declare(strict_types=1);

namespace AgendaShops\Http;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}
}
