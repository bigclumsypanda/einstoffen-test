<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Exceptions\WebhookProcessingException;
use App\Models\AddressModel;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Services\InvoiceWebhookService;
use App\Services\Outbox;
use CodeIgniter\Test\Fabricator;
use Tests\Support\WorkflowTestCase;

final class InvoiceWebhookServiceTest extends WorkflowTestCase
{
    public function testUpsertsCustomerAndInvoiceWithoutDuplicateIntent(): void
    {
        $id = $this->createInvoice();
        $originalAddress = model(AddressModel::class)->first()->toRawArray();

        $payload = self::validPayload();
        $payload['customer']['name'] = 'Updated customer';
        $payload['status'] = 'paid';
        $payload['amount'] = '350.25';

        $this->assertSame($id, service('invoiceWebhookService')->handle($payload));

        $this->seeNumRecords(1, 'invoices', []);
        $this->seeNumRecords(1, 'customers', []);
        $this->seeNumRecords(1, 'addresses', []);

        $this->assertSame($originalAddress, model(AddressModel::class)->first()->toRawArray());

        $this->seeNumRecords(1, 'outbox', []);
        $this->seeInDatabase('customers', ['name' => 'Updated customer']);
        $this->seeInDatabase('invoices', ['amount' => '350.25', 'status' => 'paid']);

        $this->assertSame('350.25', model(InvoiceModel::class)->find($id)->amount);
    }

    public function testDatabaseFailureRollsBackCustomerUpdate(): void
    {
        $id = $this->createInvoice();
        $payload = self::validPayload();
        $payload['amount'] = '100000000000'; // Bypass HTTP validation to exercise the database guard.
        $payload['customer']['name'] = 'Must roll back';

        try {
            service('invoiceWebhookService')->handle($payload);

            $this->fail('Expected a persistence exception.');
        } catch (WebhookProcessingException) {
            $this->seeInDatabase('customers', ['name' => 'Muster Optik GmbH']);
            $this->assertSame('249.90', model(InvoiceModel::class)->find($id)->amount);
            $this->seeNumRecords(1, 'outbox', []);
        }
    }

    public function testFailureToSaveJobRollsBackNewInvoiceAndCustomer(): void
    {
        $outbox = $this->createMock(Outbox::class);
        $outbox->method('add')->willThrowException(new \RuntimeException('Outbox unavailable'));

        $service = new InvoiceWebhookService(
            model(CustomerModel::class),
            model(InvoiceModel::class),
            model(AddressModel::class),
            $outbox,
        );

        try {
            $service->handle(self::validPayload());
            $this->fail('Expected exception.');
        } catch (WebhookProcessingException) {
            $this->seeNumRecords(0, 'customers', []);
            $this->seeNumRecords(0, 'invoices', []);
        }
    }

    public function testExistingAddressesArePreservedAndSnapshotIsStable(): void
    {
        $fabricator = new Fabricator(CustomerModel::class);

        $overrides = [
            'crm_id' => 'CUST-4711',
            'name' => 'Existing',
            'email' => 'existing@example.test',
        ];

        $customer = $fabricator->setOverrides($overrides)->create();

        $addresses = new Fabricator(AddressModel::class);

        $overrides = [
            'customer_id' => $customer->id,
            'street' => 'Real street 1',
            'city' => 'Bern',
            'postal_code' => '3000',
            'country' => 'CH'
        ];

        $address = $addresses->setOverrides($overrides)->create();

        $overrides = [
            'customer_id' => $customer->id,
            'street' => 'Second street 2',
            'city' => 'Bern',
            'postal_code' => '3000',
            'country' => 'CH',
        ];

        $addresses->setOverrides($overrides)->create();

        $this->createInvoice();

        model(AddressModel::class)->update($address->id, ['street' => 'Changed later']);

        $this->createInvoice();

        $data = $this->message('process_shipment');

        $this->assertSame('Real street 1', $data['address']['street']);
        $this->seeNumRecords(2, 'addresses', []);
        $this->seeInDatabase('addresses', ['id' => $address->id, 'street' => 'Changed later']);
    }

    public function testCustomerDeletionCascadesThroughAllOwnedRecords(): void
    {
        $this->createInvoice();
        $customer = model(CustomerModel::class)->first();
        model(CustomerModel::class)->delete($customer->id);
        $this->seeNumRecords(0, 'invoices', []);
        $this->seeNumRecords(0, 'outbox', []);
    }
}
