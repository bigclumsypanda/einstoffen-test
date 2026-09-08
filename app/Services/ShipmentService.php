<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Customer;
use App\Entities\Invoice;
use App\Entities\Shipment;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\ShipmentModel;
use RuntimeException;
use Throwable;

class ShipmentService
{
    public function __construct(private FakeUps $provider, private MailDispatcher $mailer)
    {
    }

    public function process(array $data): void
    {
        $db = db_connect();

        try {
            if (!$db->transBegin()) {
                throw new RuntimeException('Unable to start shipment transaction.');
            }

            // Serialize workers and deletion on this invoice, including the bounded provider call.
            $queryData = [$data['invoice_id']];

            $row = $db
                ->query('SELECT id FROM invoices WHERE id = ? FOR UPDATE', $queryData)
                ->getRowArray();

            if (is_null($row)) {
                $db->transRollback();
                log_message('info', 'Shipment skipped for deleted invoice {invoice_id}', $data);

                return;
            }

            /**
             * @var Invoice $invoice
             * @var CustomerModel $customer
             */
            $invoice = model(InvoiceModel::class)->find($row['id']);
            $customer = model(CustomerModel::class)->find($invoice->customer_id);

            if ($customer === null) {
                throw new RuntimeException('Customer not found for invoice ' . $invoice->invoice_id);
            }

            $shipments = model(ShipmentModel::class);
            $shipment = $this->ensureShipment($data, $invoice, $customer, $shipments);

            // The shipment and its durable email intent commit together.
            if (
                ! $this->mailer->send(
                    $customer->email,
                    'Your shipment has been dispatched',
                    "Dear {$customer->name},\n\nTracking number: {$shipment->tracking_number}.",
                    $invoice->id,
                    'shipment-email:' . $invoice->id,
                )
            ) {
                throw new RuntimeException('Unable to persist customer email.');
            }
            if (isset($data['outbox_id'])) {
                service('outbox')->complete((int) $data['outbox_id']);
            }
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('Unable to commit shipment transaction.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }

    private function ensureShipment(array $data, Invoice $invoice, Customer $customer, ShipmentModel $shipments): Shipment
    {
        $existing = $shipments->where('invoice_id', $invoice->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $payload = ['name' => $customer->name, 'email' => $customer->email];

        if (isset($data['address'])) {
            $valid = $this->provider->validateAddress($data['address']);

            if (($valid['valid'] ?? null) !== true) {
                throw new RuntimeException('Provider rejected the shipment address.');
            }

            $payload['address'] = $data['address'];
        }

        // Recover the external result after a DB failure instead of creating another shipment.
        $externalId = hash('sha256', 'invoice:' . $invoice->id . ':' . $invoice->invoice_id);
        $response = $this->provider->findShipment($externalId);
        $response ??= $this->provider->createShipment(['id' => $externalId] + $payload);

        $id = $response['id'] ?? null;
        $tracking = $response['tracking_number'] ?? $id;
        $status = $response['status'] ?? 'created';

        $hasValidId = is_string($id) && $id === $externalId;
        $hasValidTracking = is_string($tracking) && $tracking !== '' && strlen($tracking) <= 64;
        $hasValidStatus = is_string($status) && $status !== '' && strlen($status) <= 32;

        if (!$hasValidId || !$hasValidTracking || !$hasValidStatus) {
            throw new RuntimeException('Provider returned an invalid shipment response.');
        }

        $shipmentId = $shipments->insert([
            'shipment_id' => $id, 'tracking_number' => $tracking,
            'user' => $customer->name, 'invoice_id' => $invoice->id,
            'address_id' => $data['address_id'] ?? null, 'status' => $status,
        ]);

        if ($shipmentId === false) {
            throw new RuntimeException('Unable to persist shipment.');
        }

        return $shipments->find($shipmentId);
    }
}
