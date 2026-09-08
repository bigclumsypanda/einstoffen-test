<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class FakeUpsException extends RuntimeException
{
    public static function forEmptyResponse(int $status): self
    {
        return new self('FakeUps returned an empty response with status ' . $status . '.', $status);
    }

    public static function forInvalidResponse(int $status): self
    {
        return new self('FakeUps returned a non-JSON response with status ' . $status . '.', $status);
    }
}
