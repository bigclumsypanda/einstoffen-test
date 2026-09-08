<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\QueueFailureListener;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Events\QueueEvent;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use RuntimeException;
use Tests\Support\WorkflowTestCase;

final class OutboxTest extends WorkflowTestCase
{
    public function testFailedPublicationCanBeRetriedWithoutLosingIntent(): void
    {
        $this->createInvoice();

        $queue = $this->createMock(QueueInterface::class);

        $queue->method('setPriority')->with('high')->willReturnSelf();

        $queue->method('push')->willReturnOnConsecutiveCalls(
            QueuePushResult::failure('Redis offline'),
            QueuePushResult::success(1),
        );

        $this->assertSame(0, service('outbox')->publish($queue));
        $this->seeInDatabase('outbox', ['dispatched_at' => null]);
        $this->assertLogContains('error', 'publication failed: Redis offline');
        $this->assertSame(1, service('outbox')->publish($queue));
        $this->assertSame(0, service('outbox')->publish($queue));
    }

    public function testAdminNotificationsPersistWithoutRedis(): void
    {
        $this->assertTrue(service('adminNotifier')->notifyFailedWebhook('INV-X', 'Redis unavailable'));
        $this->seeInDatabase('outbox', ['job' => 'send_email', 'priority' => 'low']);
    }

    public function testTerminalCustomerJobFailureNotifiesAdminWithoutRecursion(): void
    {
        $work = new QueueJob(['id' => 1, 'payload' => ['data' => ['invoice_id' => 123]]]);

        $event = new QueueEvent('queue.job.failed', 'redis', 'default', [
            'job' => $work,
            'exception' => new RuntimeException('Retries exhausted'),
        ]);

        QueueFailureListener::handle($event);

        $this->seeNumRecords(1, 'outbox', ['job' => 'send_email']);

        $work->payload = ['data' => ['admin_notification' => true]];

        $event = new QueueEvent('queue.job.failed', 'redis', 'default', [
            'job' => $work,
            'exception' => new RuntimeException('Admin SMTP failed'),
        ]);

        QueueFailureListener::handle($event);

        $this->seeNumRecords(1, 'outbox', ['job' => 'send_email']);
    }
}
