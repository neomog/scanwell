<?php

namespace App\Exceptions;

use RuntimeException;

class PlanFeatureException extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        string $message = 'Your current plan does not allow this action.'
    ) {
        parent::__construct($message);
    }
}
