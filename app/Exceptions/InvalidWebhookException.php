<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidWebhookException extends InvalidArgumentException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid webhook payload.');
    }
}
