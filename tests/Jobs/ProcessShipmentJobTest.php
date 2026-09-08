<?php

declare(strict_types=1);

namespace App\Tests\Jobs;

use App\Jobs\ProcessShipmentJob;
use App\Services\FakeUps;
use App\Services\MailDispatcher;
use App\Services\ShipmentService;
use Config\Services;
use RuntimeException;
use Tests\Support\WorkflowTestCase;

final class ProcessShipmentJobTest extends WorkflowTestCase
{
    private function provider(int $id): FakeUps
    {
        $provider = $this->createMock(FakeUps::class);
        $externalId = hash('sha256', 'invoice:' . $id . ':INV-2026-00123');

        $provider->method('validateAddress')->willReturn(['valid' => true]);
        $provider->method('createShipment')->willReturn(['id' => $externalId, 'tracking_number' => 'TRACK-1']);

        return $provider;
    }

    public function testCreatesShipmentAndDurableEmailOnce(): void
    {
        $id = $this->createInvoice();
        $provider = $this->provider($id);
        $provider->expects($this->once())->method('createShipment');

        Services::injectMock('shipmentService', new ShipmentService($provider, service('mailer')));

        $job = new ProcessShipmentJob($this->message('process_shipment'));

        $this->assertTrue($job->process());
        $this->assertTrue($job->process());

        $this->seeNumRecords(1, 'shipments', ['invoice_id' => $id]);
        $this->seeNumRecords(1, 'outbox', ['job' => 'send_email']);

        $email = $this->message('send_email');

        $this->assertSame('kontakt@musteroptik.example', $email['to']);
        $this->assertStringContainsString('TRACK-1', (string)$email['message']);
    }

    public function testDeletedInvoiceMakesOldJobHarmless(): void
    {
        $id = $this->createInvoice();
        $data = $this->message('process_shipment');

        service('invoiceWebhookService')->handle(['event' => 'invoice.deleted', 'invoice_id' => 'INV-2026-00123']);

        $provider = $this->provider($id);
        $provider->expects($this->never())->method('createShipment');

        Services::injectMock('shipmentService', new ShipmentService($provider, service('mailer')));

        $this->assertTrue((new ProcessShipmentJob($data))->process());
        $this->seeNumRecords(0, 'outbox', []);
    }

    public function testRecoversProviderResultAfterFailureToPersistEmail(): void
    {
        $id = $this->createInvoice();
        $data = $this->message('process_shipment');
        $provider = $this->provider($id);
        $mailer = $this->createMock(MailDispatcher::class);
        $mailer->method('send')->willReturn(false);
        $shipmentService = new ShipmentService($provider, $mailer);

        try {
            $shipmentService->process($data);

            $this->fail('Expected exception.');
        } catch (RuntimeException) {
            $this->seeNumRecords(0, 'shipments', []);
            $this->seeNumRecords(0, 'outbox', ['job' => 'send_email']);
        }

        $provider = $this->provider($id);

        $provider->expects($this->never())->method('createShipment');

        $provider->method('findShipment')->willReturn([
            'id' => hash('sha256', 'invoice:' . $id . ':INV-2026-00123'),
            'tracking_number' => 'RECOVERED',
        ]);

        $shipmentService = new ShipmentService($provider, service('mailer'));
        $shipmentService->process($data);

        $this->seeInDatabase('shipments', ['tracking_number' => 'RECOVERED']);
        $this->seeNumRecords(1, 'outbox', ['job' => 'send_email']);
    }

    public function testProviderFailureLogsReasonAndNeverQueuesCustomerSuccess(): void
    {
        $id = $this->createInvoice();
        $provider = $this->createMock(FakeUps::class);

        $provider->method('validateAddress')->willReturn(['valid' => true]);
        $provider->method('findShipment')->willThrowException(new RuntimeException('Provider offline'));

        Services::injectMock('shipmentService', new ShipmentService($provider, service('mailer')));

        $shipmentJob = new ProcessShipmentJob($this->message('process_shipment'));

        try {
            $shipmentJob->process();

            $this->fail('Expected exception.');
        } catch (RuntimeException) {
            $this->assertLogContains('error', 'Shipment job failed for invoice ' . $id . ': Provider offline');
            $this->seeNumRecords(0, 'shipments', []);
            $this->seeNumRecords(0, 'outbox', ['job' => 'send_email']);
        }
    }

    public function testAddressValidationUsesSnapshotAndRejectsInvalidAddress(): void
    {
        $this->createInvoice();

        $data = $this->message('process_shipment');
        $data['address'] = ['street' => 'A', 'city' => 'Bern', 'postalCode' => '3000', 'country' => 'CH'];

        $provider = $this->createMock(FakeUps::class);

        $provider
            ->expects($this->once())
            ->method('validateAddress')
            ->with($data['address'])
            ->willReturn(['valid' => false]);

        $provider->expects($this->never())->method('createShipment');

        $this->expectExceptionMessage('Provider rejected');

        $shipmentService = new ShipmentService($provider, service('mailer'));

        $shipmentService->process($data);
    }
}
