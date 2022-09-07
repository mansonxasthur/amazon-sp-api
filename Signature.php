<?php

namespace App\Services\Amazon;

use App\Services\Amazon\Contracts\SignatureInterface;

/**
 * @ref https://docs.aws.amazon.com/general/latest/gr/signature-version-4.html
 */
class Signature implements SignatureInterface
{
    public function sign(Request $request, string $accessKey, string $accessSecret): void
    {
        $canonicalRequest       = $this->buildCanonicalRequest($request);
        $hashedCanonicalRequest = hash('SHA256', $canonicalRequest);

        $stringToSign = $this->buildStringToSign($request, $hashedCanonicalRequest);
        $signature    = $this->calculateSignature($request, $stringToSign, $accessSecret);
        $request->setHeader('Authorization',
            $this->buildAuthorization($request, $accessKey, $signature)
        );
    }

    protected function buildCanonicalRequest(Request $request): string
    {
        return sprintf("%s\n%s\n%s\n%s\n%s\n%s",
            strtoupper($request->getMethod()),
            $this->canonicalUri($request->getPath()),
            $this->canonicalQueryString($request->getQuery()),
            $this->canonicalHeaders($request->getHeaders()),
            $this->signedHeaders($request->getHeaders()),
            hash('SHA256', json_encode($request->getBody()))
        );
    }

    protected function canonicalUri(string $path): string
    {
        return trim(implode('/', array_map(fn($component) => urlencode(urlencode($component)), explode('/', $path))));
    }

    protected function canonicalQueryString(array $query): string
    {
        $builder = '';
        ksort($query);
        foreach ($query as $key => $value) {
            if (!empty($builder))
                $builder .= '&';

            $builder .= urlencode($key) . '=' . urlencode($value);
        }

        return $builder;
    }

    protected function canonicalHeaders(array $headers): string
    {
        ksort($headers);
        $builder = '';

        foreach ($headers as $name => $value) {
            $builder .= strtolower($name) . ':' . trim($value) . "\n";
        }

        return $builder;
    }

    protected function signedHeaders(array $headers): string
    {
        $signedHeaders = array_keys($headers);
        sort($signedHeaders);
        return implode(';', $signedHeaders);
    }

    protected function buildStringToSign(Request $request, string $hashedCanonicalRequest): string
    {
        return sprintf("%s\n%s\n%s\n%s",
            Request::ALGORITHM,
            date("Ymd\THis\Z", $request->getTime()), // ISO8601 Zulu string
            $this->scope($request),
            $hashedCanonicalRequest,
        );
    }

    protected function buildAuthorization(Request $request, string $accessKey, string $signature): string
    {
        $signedHeaders = array_keys($request->getHeaders());
        sort($signedHeaders);
        return sprintf('%s Credential=%s, SignedHeaders=%s, Signature=%s',
            Request::ALGORITHM,
            $this->getCredential($request, $accessKey),
            implode(';', $signedHeaders),
            $signature
        );
    }

    protected function getCredential(Request $request, string $accessKey): string
    {
        return sprintf('%s/%s',
            $accessKey,
            $this->scope($request),
        );
    }

    protected function scope(Request $request): string
    {
        return sprintf('%s/%s/%s/%s',
            date('Ymd', $request->getTime()),
            $request->getAwsRegion(),
            Request::SERVICE,
            Request::TERMINATION_STRING,
        );
    }

    protected function calculateSignature(Request $request, string $stringToSign, string $accessSecret): string
    {
        return $this->hmac($stringToSign,
            $this->calculateSigningKey($request, $accessSecret)
        );
    }

    protected function calculateSigningKey(Request $request, string $accessSecret): string
    {
        $kDate    = $this->hmac(date('Ymd', $request->getTime()), "AWS4$accessSecret", true);
        $kRegion  = $this->hmac($request->getAwsRegion(), $kDate, true);
        $kService = $this->hmac(Request::SERVICE, $kRegion, true);
        return $this->hmac(Request::TERMINATION_STRING, $kService, true);
    }

    protected function hmac(string $data, string $key, bool $binary = false): string
    {
        return hash_hmac('SHA256', $data, $key, $binary);
    }
}
