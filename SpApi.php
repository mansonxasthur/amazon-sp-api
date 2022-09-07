<?php

namespace App\Services\Amazon;

use App\Services\Amazon\Contracts\SignatureInterface;

class SpApi extends Http
{
    public function __construct(
        protected SignatureInterface $signature,
        string                       $accessKey,
        string                       $accessSecret,
        string                       $endpoint,
        array                        $appInfo,
        string|null                  $marketplaceId,
        string|null                  $restrictedDataToken = null,
        bool                         $beta = false
    )
    {
        parent::__construct(
            $accessKey,
            $accessSecret,
            $endpoint,
            $appInfo,
            $marketplaceId,
            $restrictedDataToken,
            $beta,
        );
    }

    protected function signRequest(Request $request): void
    {
        $this->signature->sign($request, $this->getAccessKey(), $this->getAccessSecret());
    }
}
