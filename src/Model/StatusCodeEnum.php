<?php

namespace App\Model;

enum StatusCodeEnum: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';

    public static function load(int $status): self
    {
        return match ($status) {
            1 => self::ACTIVE,
            2 => self::INACTIVE,
            default => throw new \InvalidArgumentException('Neplatný status kód: ' . $status),
        };
    }
}
