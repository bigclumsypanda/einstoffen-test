<?php

declare(strict_types=1);

namespace App\Validation;

final class InvoiceRules
{
    public function invoiceAmount(mixed $value): bool
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return false;
        }

        /** Accept JSON numbers or decimal strings that fit DECIMAL(12,2), without rounding. */
        return preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', (string) $value) === 1;
    }
}
