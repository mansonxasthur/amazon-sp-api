<?php

namespace App\Services\Amazon\Facades;

use App\Services\Amazon\Enums\ReportType;

class DelimiterIdentifier
{
    public function identify(ReportType $reportType): string
    {
        return match($reportType) {
          ReportType::GET_MERCHANT_LISTINGS_ALL_DATA => "\t",
          default => ','
        };
    }
}
