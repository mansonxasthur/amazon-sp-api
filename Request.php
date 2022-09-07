<?php

namespace App\Services\Amazon;

class Request
{
    public const ALGORITHM          = 'AWS4-HMAC-SHA256';
    public const SERVICE            = 'execute-api';
    public const TERMINATION_STRING = 'aws4_request';

    public function __construct(
        protected string $endpoint,
        protected string $time,
        protected string $method,
        protected string $path,
        protected array  $body,
        protected array  $headers,
        protected array  $query,
    )
    {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function setHeader(string $name, string $value): void
    {
        $this->headers[strtolower($name)] = trim($value);
    }

    public function getAwsRegion(): string
    {
        return match ($this->endpoint) {
            'https://sellingpartnerapi-na.amazon.com' => 'us-east-1',
            'https://sellingpartnerapi-eu.amazon.com' => 'eu-west-1',
            'https://sellingpartnerapi-fe.amazon.com' => 'us-west-1',
            default                                   => 'eu-west-1',
        };
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function header(string $name): string|null
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getUri(): string
    {
        return $this->endpoint . $this->path;
    }

    public function getFullUri(): string
    {
        $query = http_build_query($this->getQuery());
        if (!empty($query)) {
            $query = '?' . $query;
        }

        return $this->endpoint . $this->path . $query;
    }
}
