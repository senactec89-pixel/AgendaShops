<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function localDate(string $utc, string $timezone = 'America/Sao_Paulo'): string
{
    return (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone($timezone))->format('d/m/Y H:i');
}
