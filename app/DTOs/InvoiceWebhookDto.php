<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;
use Exception;

final class InvoiceWebhookDto
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $customerId,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly DateTimeImmutable $dueDate,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        $customer = $data['customer'] ?? null;
        $customerId = $customer['id'] ?? null;
        $customerName = $customer['name'] ?? null;
        $customerEmail = $customer['email'] ?? null;

        return new self(
            $data['invoice_id'] ?? '',
            $customerId,
            $customerName,
            $customerEmail,
            (string) ($data['amount'] ?? ''),
            $data['currency'] ?? '',
            $data['status'] ?? '',
            new DateTimeImmutable($data['due_date'] ?? ''),
            new DateTimeImmutable($data['created_at'] ?? ''),
        );
    }
}
