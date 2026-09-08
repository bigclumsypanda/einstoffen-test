<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Services\InvoiceWebhookService;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\WorkflowTestCase;

final class InvoicesWebhookTest extends WorkflowTestCase
{
    use FeatureTestTrait;

    public function testAcceptsOriginalContractThroughRealRoute(): void
    {
        $result = $this->withBody(json_encode(self::validPayload()))->post('webhooks/invoices');
        $result->assertStatus(202);
        $this->seeInDatabase('invoices', ['invoice_id' => 'INV-2026-00123', 'created_at' => '2026-09-08 10:15:00']);
        $this->seeInDatabase('outbox', ['job' => 'process_shipment', 'priority' => 'high']);
        $this->seeNumRecords(1, 'addresses', []);
        $this->seeInDatabase('addresses', ['country' => 'CH']);
    }

    public function testIgnoresUnknownFieldsIncludingMalformedAddressExtras(): void
    {
        $payload = self::validPayload();
        $payload['customer']['address'] = ['street' => ['anything']];
        $payload['customer']['addresses'] = 'unused';
        $payload['extra'] = ['arbitrary' => true];

        $this->withBody(json_encode($payload))->post('webhooks/invoices')->assertStatus(202);
        $this->seeNumRecords(1, 'addresses', []);
        $this->seeInDatabase('addresses', ['country' => 'CH']);
    }

    public static function malformedBodies(): array
    {
        return [['{broken'], ['[]'], ['null'], ['42'], ['"text"'], ['']];
    }

    #[DataProvider('malformedBodies')]
    public function testRejectsMalformedJsonOrNonObject(string $body): void
    {
        $this->withBody($body)->post('webhooks/invoices')->assertStatus(400);
        $this->seeNumRecords(0, 'customers', []);
        $this->seeNumRecords(0, 'outbox', []);
    }

    public static function invalidFields(): array
    {
        return [
            ['event', []], ['event', 'invoice.unknown'], ['created_at', []], ['created_at', null],
            ['amount', '100000000000'], ['amount', '1.001'], ['amount', true],
            ['currency', 'CH'], ['due_date', '2026-02-30'], ['customer', ['id' => 'C1']],
        ];
    }

    #[DataProvider('invalidFields')]
    public function testRejectsInvalidTypesAndValues(string $field, mixed $value): void
    {
        $payload = self::validPayload();
        $payload[$field] = $value;

        $this->withBody(json_encode($payload))->post('webhooks/invoices')->assertStatus(400);
        $this->seeNumRecords(0, 'outbox', []);
    }

    public function testGetIs405WithAllowHeaderThroughRealRoute(): void
    {
        $result = $this->get('webhooks/invoices');
        $result->assertStatus(405);
        $result->assertHeader('Allow', 'POST');
    }

    public function testDeleteIsIdempotentAndCancelsDurableJobs(): void
    {
        $this->createInvoice();

        $payload = ['event' => 'invoice.deleted', 'invoice_id' => 'INV-2026-00123'];

        $this->withBody(json_encode($payload))->post('webhooks/invoices')->assertStatus(202);
        $this->withBody(json_encode($payload))->post('webhooks/invoices')->assertStatus(202);
        $this->seeNumRecords(0, 'invoices', []);
        $this->seeNumRecords(0, 'outbox', []);
    }

    public function testUnexpectedPersistenceErrorQueuesAdminNotification(): void
    {
        $service = $this->createMock(InvoiceWebhookService::class);
        $service->method('handle')->willThrowException(new RuntimeException('Simulated database failure'));

        Services::injectMock('invoiceWebhookService', $service);

        $this->withBody(json_encode(self::validPayload()))->post('webhooks/invoices')->assertStatus(500);

        $data = $this->message('send_email');

        $this->assertTrue((bool)$data['admin_notification']);
        $this->assertStringContainsString('Simulated database failure', (string) $data['message']);
        $this->assertLogContains('error', 'INV-2026-00123 processing failed: Simulated database failure');
    }
}
