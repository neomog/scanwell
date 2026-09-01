<?php

namespace App\Exceptions;

use RuntimeException;

class ImageScanIdentificationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $status = 422,
        public readonly array $context = []
    ) {
        parent::__construct($message, $status);
    }
}
