<?php

namespace App\Services\Amazon\Contracts;

use App\Services\Amazon\Request;

interface SignatureInterface
{
    public function sign(Request $request, string $accessKey, string $accessSecret): void;
}
