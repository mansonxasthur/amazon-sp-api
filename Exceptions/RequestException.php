<?php

namespace App\Services\Amazon\Exceptions;

class RequestException extends \Exception
{
    public function __construct(protected array $errors, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
