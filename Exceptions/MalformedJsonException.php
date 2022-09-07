<?php

namespace App\Services\Amazon\Exceptions;

class MalformedJsonException extends \Exception
{
    public function __construct(protected string $body, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function body(): string
    {
        return $this->body;
    }
}
