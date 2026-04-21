<?php

namespace App\Exception;

class AnthropicApiException extends \RuntimeException
{
    public function __construct(string $message, int $httpStatus = 502, ?\Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus, $previous);
    }
}
