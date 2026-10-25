<?php

namespace App\Exceptions;

use RuntimeException;

class DriveIntegrationException extends RuntimeException
{
    public function __construct(string $message = 'Google Drive storage service is currently unavailable. Your submission was not recorded. Please retry.', int $code = 502)
    {
        parent::__construct($message, $code);
    }
}
