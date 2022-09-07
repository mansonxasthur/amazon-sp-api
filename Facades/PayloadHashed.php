<?php

namespace App\Services\Amazon\Facades;

class PayloadHashed
{
    public static function withHash(array $data): array
    {
        return [
            'hash'    => self::hash($data),
            'payload' => $data,
        ];
    }

    public static function hash(array $data): string
    {
        ksort($data);
        return md5(serialize($data));
    }
}
