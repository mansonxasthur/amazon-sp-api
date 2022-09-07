<?php

namespace App\Services\Amazon;

use Psr\Http\Message\ResponseInterface;
use App\Services\Amazon\Exceptions\MalformedJsonException;

/**
 * @mixin ResponseInterface
 */
class Response
{
    protected array $body;

    public function __construct(protected ResponseInterface $response)
    {
    }

    public function getBody(): array
    {
        if (!empty($this->body)) {
            return $this->body;
        }

        $this->body = json_decode($this->response->getBody(), flags: JSON_OBJECT_AS_ARRAY) ?: [];
        if(json_last_error()) {
            throw new MalformedJsonException($this->response->getBody()->getContents(), json_last_error_msg(), json_last_error());
        }

        return $this->body['payload'] ?? $this->body;
    }

    public function success(): bool
    {
        return $this->response->getStatusCode() < 300;
    }

    public function errors(): array
    {
        return $this->getBody()['errors'] ?? [];
    }

    public function __call(string $name, array $arguments)
    {
        return $this->response->{$name}(...$arguments);
    }
}
