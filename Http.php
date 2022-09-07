<?php

namespace App\Services\Amazon;

use GuzzleHttp\Client;
use App\Helpers\Logger;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use App\Services\Amazon\Exceptions\RequestException as AmazonRequestException;

abstract class Http
{
    public const METHOD_GET    = 'GET';
    public const METHOD_POST   = 'POST';
    public const METHOD_PUT    = 'PUT';
    public const METHOD_PATCH  = 'PATCH';
    public const METHOD_DELETE = 'DELETE';

    protected const REQUIRED_APP_INFO = [
        'name', 'version', 'language', 'language_version', 'platform',
    ];

    protected Client       $client;
    protected Request|null $request = null;
    protected string       $time;

    public function __construct(
        protected string      $accessKey,
        protected string      $accessSecret,
        protected string      $endpoint,
        protected array       $appInfo,
        protected string|null $marketplaceId = null,
        protected string|null $restrictedDataToken = null,
        protected bool        $beta = false,
    )
    {
        $this->bootstrap();
    }

    protected function bootstrap(): void
    {
        $this->validateAppInfo();
        $this->client = new Client([
            'base_uri' => $this->endpoint,
        ]);
    }


    public function setMarketplaceId(?string $marketplaceId): static
    {
        $this->marketplaceId = $marketplaceId;
        return $this;
    }


    public function setRestrictedDataToken(?string $restrictedDataToken): static
    {
        $this->restrictedDataToken = $restrictedDataToken;
        return $this;
    }



    protected function validateAppInfo(): void
    {
        $missingKeys = array_diff(self::REQUIRED_APP_INFO, array_keys($this->appInfo));

        if (!empty($missingKeys)) {
            throw new \Exception(
                sprintf('Missing app info (%s)', implode(',', $missingKeys))
            );
        }
    }

    protected function buildUserAgent(): string
    {
        return sprintf('%s/%s (Languages=%s/%s;Platform=%s)',
            $this->appInfo['name'],
            $this->appInfo['version'],
            $this->appInfo['language'],
            $this->appInfo['language_version'],
            $this->appInfo['platform']
        );
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getRequest(): Request|null
    {
        return $this->request;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function getAccessSecret(): string
    {
        return $this->accessSecret;
    }

    /**
     * @param    string    $method
     * @param    string    $path
     * @param    array     $body
     * @param    array     $headers
     * @param    array     $query
     *
     * @return \App\Services\Amazon\Response
     * @throws \App\Services\Amazon\Exceptions\RequestException|\GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $method, string $path, array $body = [], array $headers = [], array $query = []): Response
    {
        $this->time = time();
        $headers    = $this->applyHeaders($headers);
        $query      = $this->applyQuery($query);
        $request    = new Request($this->getEndpoint(), $this->time, $method, $path, $body, $headers, $query);
        $this->signRequest($request);
        $this->request = $request;
        try {
            return $this->handleResponse(
                $this->client->request(
                    $request->getMethod(),
                    $request->getPath(),
                    $this->buildContext($request)
                )
            );

        } catch (RequestException $e) {
            Logger::warning('FAILED_AMAZON_REQUEST', [
                'request_headers' => $request->getHeaders(), 'uri' => $request->getFullUri(),
            ]);
            $this->handleRequestException($e);
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \App\Services\Amazon\Exceptions\RequestException
     */
    public function get(string $path, array $body = [], array $headers = [], array $query = []): Response
    {
        return $this->request(self::METHOD_GET, $path, $body, $headers, $query);
    }

    /**
     * @throws \App\Services\Amazon\Exceptions\RequestException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post(string $path, array $body = [], array $headers = [], array $query = []): Response
    {
        return $this->request(self::METHOD_POST, $path, $body, $headers, $query);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \App\Services\Amazon\Exceptions\RequestException
     */
    public function put(string $path, array $body = [], array $headers = [], array $query = []): Response
    {
        return $this->request(self::METHOD_PUT, $path, $body, $headers, $query);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \App\Services\Amazon\Exceptions\RequestException
     */
    public function patch(string $path, array $body = [], array $headers = [], array $query = []): Response
    {
        return $this->request(self::METHOD_PATCH, $path, $body, $headers, $query);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \App\Services\Amazon\Exceptions\RequestException
     */
    public function delete(string $path, array $body = [], array $headers = [], array $query = []): Response
    {
        return $this->request(self::METHOD_DELETE, $path, $body, $headers, $query);
    }

    protected function applyHeaders(array $headers): array
    {
        $baseHeaders = [
            'accept'             => 'application/json',
            'content-type'       => 'application/json',
            'host'               => preg_replace('/^http(s)?:\/\//', '', $this->getEndpoint()),
            'user-agent'         => $this->buildUserAgent(),
            'x-amz-access-token' => $this->restrictedDataToken,
            'x-amz-date'         => date("Ymd\THis\Z", $this->time), // ISO8601 Zulu string
        ];

        foreach ($headers as $name => $value) {
            $baseHeaders[trim(strtolower($name))] = trim($value);
        }

        return $baseHeaders;
    }

    protected function applyQuery(array $query): array
    {
        $baseQuery = [
            'marketplaceIds' => $this->marketplaceId,
        ];

        $query = array_merge($baseQuery, $query);

        if ($this->beta) {
            $query['version'] = 'beta';
        }

        return $query;
    }

    protected function buildContext(Request $request): array
    {
        return [
            'json'    => $request->getBody(),
            'headers' => $request->getHeaders(),
            'query'   => $request->getQuery(),
        ];
    }

    protected function handleResponse(ResponseInterface $response): Response
    {
        return new Response($response);
    }

    /**
     * @param    \GuzzleHttp\Exception\RequestException    $exception
     *
     * @return void
     * @throws \App\Services\Amazon\Exceptions\RequestException
     */
    protected function handleRequestException(RequestException $exception): void
    {
        $res = new Response($exception->getResponse());
        throw new AmazonRequestException($res->errors(), $res->getReasonPhrase(), $res->getStatusCode());
    }

    abstract protected function signRequest(Request $request): void;
}
