<?php
declare(strict_types=1);
namespace AgendaShops\Domain;

final class Input
{
    public static function text(array $data, string $key, int $min = 1, int $max = 255): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value)) < $min || mb_strlen(trim($value)) > $max) {
            throw new Problem('Preencha corretamente o campo ' . $key . '.');
        }
        return trim($value);
    }
    public static function integer(array $data, string $key, int $min, int $max): int
    {
        $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value === null || $value < $min || $value > $max) {
            throw new Problem('Valor inválido para ' . $key . '.');
        }
        return $value;
    }
    public static function email(array $data): string
    {
        $value = mb_strtolower(self::text($data, 'email'));
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { throw new Problem('Informe um e-mail válido.'); }
        return $value;
    }
    public static function phone(array $data): string
    {
        $value = preg_replace('/\D/', '', self::text($data, 'phone'));
        if (strlen($value) < 10 || strlen($value) > 15) { throw new Problem('Informe um telefone com DDD.'); }
        return $value;
    }
    public static function time(array $data, string $key): string
    {
        $value = self::text($data, $key);
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value)) { throw new Problem('Horário inválido.'); }
        return $value;
    }
    public static function date(string $value, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) { throw new Problem('Data e horário inválidos.'); }
        return $date;
    }
}
