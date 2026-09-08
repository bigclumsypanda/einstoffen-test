<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\TestLogger;
use Config\Logger;
use Config\Services;
use JsonException;

abstract class WorkflowTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        $this->resetServices();

        parent::setUp();

        Services::injectMock('logger', new TestLogger(config(Logger::class)));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetServices();
    }

    protected static function validPayload(): array
    {
        return [
            'event' => 'invoice.created',
            'invoice_id' => 'INV-2026-00123',
            'customer' => [
                'id' => 'CUST-4711',
                'name' => 'Muster Optik GmbH',
                'email' => 'kontakt@musteroptik.example'
            ],
            'amount' => 249.90,
            'currency' => 'CHF',
            'status' => 'open',
            'due_date' => '2026-10-15',
            'created_at' => '2026-09-08T10:15:00Z',
        ];
    }

    protected function createInvoice(): int
    {
        return service('invoiceWebhookService')->handle(self::validPayload());
    }

    /**
     * @throws JsonException
     */
    protected function message(string $job): array
    {
        $row = $this->db
            ->table('outbox')
            ->where('job', $job)
            ->get()
            ->getRowArray();

        return json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR) + ['outbox_id' => (int) $row['id']];
    }
}
