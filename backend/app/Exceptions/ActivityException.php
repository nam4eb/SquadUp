<?php

namespace App\Exceptions;

use RuntimeException;

class ActivityException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}
