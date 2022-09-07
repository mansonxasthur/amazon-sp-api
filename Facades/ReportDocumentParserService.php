<?php

namespace App\Services\Amazon\Facades;

use App\Helpers\Logger;
use App\Services\Amazon\Enums\ReportType;
use App\Services\Amazon\Exceptions\RequestException;
use App\Integrations\Amazon\Requests\Reports\GetReportDocumentRequest;

class ReportDocumentParserService
{
    public function __construct(
        protected GetReportDocumentRequest $getReportDocumentRequest,
        protected DelimiterIdentifier      $delimiterIdentifier,
    )
    {
    }

    public function parse(string $reportDocumentId, ReportType $reportType): array
    {
        try {
            $reportDocument = $this->getReportDocument($reportDocumentId);
            if (!isset($reportDocument['url'])) {
                Logger::warning('MISSING_URL', [
                    'file'        => get_called_class(), 'report_document_id' => $reportDocumentId,
                    'report_type' => $reportType,
                ]);
                return [];
            }
            $url  = $reportDocument['url'];
            $gzip = isset($reportDocument['compressionAlgorithm']);
            return $gzip ? $this->getCompressedContent($url, $this->getDelimiter($reportType)) :
                $this->getContent($url, $this->getDelimiter($reportType));
        } catch (RequestException $e) {
            Logger::error('AMAZON_REQUEST_ERROR', $e, [
                'report_document_id' => $reportDocumentId, 'report_type' => $reportType, 'errors' => $e->errors(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('REPORT_DOCUMENT_PARSER_ERROR', $e, [
                'report_document_id' => $reportDocumentId, 'report_type' => $reportType,
            ]);
        }

        return [];
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \App\Services\Amazon\Exceptions\RequestException
     */
    protected function getReportDocument(string $reportDocumentId): array
    {
        return $this->getReportDocumentRequest->get(['report_document_id' => $reportDocumentId]);
    }

    protected function getDelimiter(ReportType $reportType): string
    {
        return $this->delimiterIdentifier->identify($reportType);
    }

    protected function getCompressedContent(string $url, string $delimiter): array
    {
        $file    = gzopen($url, 'rb', false);
        $headers = [];
        $data    = [];
        if ($file) {
            while (!gzeof($file)) {
                $row = explode($delimiter, gzread($file, 1024));
                if (empty($row)) {
                    break;
                }
                if (empty($headers)) {
                    $headers = array_map(fn($column) => str_replace('-', '_', $column), $row);
                    continue;
                }
                $data[] = array_combine($headers, $row);
            }
            gzclose($file);
        }

        return $data;
    }

    protected function getContent(string $url, string $delimiter): array
    {
        $content = file_get_contents($url);
        $headers = [];
        return array_reduce(explode("\n", $content), function (array $data, string $row) use ($delimiter, &$headers) {
            if (empty($row)) {
                return $data;
            }

            $row = explode($delimiter, $row);

            if (empty($headers)) {
                $headers = array_map(fn($column) => str_replace('-', '_', $column), $row);
                return $data;
            }

            $data[] = array_combine($headers, $row);
            return $data;
        }, []);
    }
}
