<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AddressDto;
use App\DTOs\InvoiceWebhookDto;
use App\Exceptions\WebhookProcessingException;
use App\Models\AddressModel;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use Throwable;

class InvoiceWebhookService
{
    public function __construct(
        private CustomerModel $customers,
        private InvoiceModel $invoices,
        private AddressModel $addresses,
        private Outbox $outbox,
    ) {
    }

    public function handle(array $payload): int
    {
        $db = $this->customers->db;

        try {
            if (!$db->transBegin()) {
                throw new WebhookProcessingException('Unable to start invoice transaction.');
            }

            $id = match ($payload['event']) {
                'invoice.created' => $this->persist(InvoiceWebhookDto::fromArray($payload)),
                'invoice.deleted' => $this->delete($payload['invoice_id']),
                default => throw new WebhookProcessingException('Unsupported invoice event.'),
            };

            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new WebhookProcessingException('Unable to commit invoice transaction.');
            }

            log_message('info', 'Webhook {event} accepted for invoice {invoice_id}', [
                'event' => $payload['event'], 'invoice_id' => $payload['invoice_id'],
            ]);

            return $id;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();

            $message = 'Invoice persistence failed: ' . $e->getMessage();

            throw new WebhookProcessingException($message, 0, $e);
        }
    }

    private function persist(InvoiceWebhookDto $dto): int
    {
        $now = gmdate('Y-m-d H:i:s');

        $upsertData = [
            'crm_id' => $dto->customerId,
            'name' => $dto->customerName,
            'email' => $dto->customerEmail,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $saved = $this->customers
            ->builder()
            ->updateFields(['name', 'email', 'updated_at'])
            ->upsert($upsertData);

        if ($saved === false) {
            throw new WebhookProcessingException('Unable to persist customer.');
        }

        $customer = $this->customers->where('crm_id', $dto->customerId)->first();

        if (is_null($customer)) {
            throw new WebhookProcessingException('Persisted customer not found.');
        }

        $upsertData = [
            'invoice_id' => $dto->invoiceId, 'customer_id' => $customer->id,
            'status' => $dto->status, 'amount' => $dto->amount, 'currency' => $dto->currency,
            'due_date' => $dto->dueDate->format('Y-m-d'),
            'created_at' => $dto->createdAt->format('Y-m-d H:i:s'), 'updated_at' => $now,
        ];

        $saved = $this->invoices
            ->builder()
            ->updateFields(['status', 'amount', 'currency', 'due_date', 'updated_at'])
            ->upsert($upsertData);

        if ($saved === false) {
            throw new WebhookProcessingException('Unable to persist invoice.');
        }

        $invoice = $this->invoices->findByInvoiceId($dto->invoiceId);

        if ($invoice === null) {
            throw new WebhookProcessingException('Persisted invoice not found.');
        }

        if ($invoice->customer_id !== $customer->id) {
            throw new WebhookProcessingException('An invoice cannot be reassigned to another customer.');
        }

        $address = $this->addresses->where('customer_id', $customer->id)->orderBy('id')->first();

        /** Create a demo address for customer */
        if ($address === null) {
            $demo = (new DemoAddressGenerator())->generate();

            $addressId = $this->addresses->insert([
                'customer_id' => $customer->id,
                'street' => $demo->street,
                'city' => $demo->city,
                'postal_code' => $demo->postalCode,
                'country' => $demo->country,
            ]);

            if ($addressId === false) {
                throw new WebhookProcessingException('Unable to persist demo address.');
            }

            $address = $this->addresses->find($addressId);
        }

        $jobData = [
            'invoice_id' => $invoice->id,
            'address_id' => $address?->id,
            'address' => $address,
        ];

        $this->outbox->add(
            'process_shipment',
            $jobData,
            'high',
            'shipment:' . $invoice->id,
            $invoice->id
        );

        return $invoice->id;
    }

    private function delete(string $invoiceId): int
    {
        if (!$this->invoices->where('invoice_id', $invoiceId)->delete()) {
            throw new WebhookProcessingException('Unable to delete invoice.');
        }

        return 0;
    }
}
