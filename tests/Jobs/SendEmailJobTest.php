<?php

declare(strict_types=1);

namespace App\Tests\Jobs;

use App\Jobs\SendEmailJob;
use CodeIgniter\Email\Email;
use CodeIgniter\Queue\Commands\QueueWork;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Test\Mock\MockEmail;
use Config\Email as EmailConfig;
use Config\Queue;
use Config\Services;
use RuntimeException;
use Tests\Support\Jobs\FailingTransportEmailJob;
use Tests\Support\WorkflowTestCase;

final class SendEmailJobTest extends WorkflowTestCase
{
    private function emailData(): array
    {
        return [
            'to' => 'customer@example.test',
            'subject' => 'Shipment',
            'message' => 'Tracking: TRACK-1',
            'from_email' => 'sender@example.test',
            'from_name' => 'Shipments'
        ];
    }

    public function testSendDeliversToRecipient(): void
    {
        $email = new MockEmail(config(EmailConfig::class));
        $job = new SendEmailJob($this->emailData(), $email);

        $this->assertTrue($job->process());
        $this->assertSame(['customer@example.test'], $email->archive['recipients']);
        $this->assertStringContainsString('TRACK-1', $email->archive['body']);
    }

    public function testMissingRecipientFails(): void
    {
        $this->expectException(RuntimeException::class);

        $job = new SendEmailJob([], new MockEmail(config(EmailConfig::class)));

        $job->process();
    }

    public function testSmtpFailureActuallySchedulesWorkerRetry(): void
    {
        $email = $this->createMock(Email::class);
        $email->method('send')->willReturn(false);

        Services::injectMock('email', $email);

        $config = config(Queue::class);

        // Test job resolves the injected transport while the real worker performs retry handling.
        $config->jobHandlers['test_email'] = FailingTransportEmailJob::class;

        $queue = $this->createMock(DatabaseHandler::class);

        $queue->method('name')->willReturn('database');
        $queue->expects($this->once())->method('later')->willReturn(true);
        $queue->expects($this->never())->method('done');

        Services::injectMock('queue', $queue);

        $worker = new QueueWork(service('logger'), service('commands'));

        $this->setPrivateProperty($worker, 'workerId', 'test-worker');

        $work = new QueueJob([
            'id' => 1,
            'queue' => 'default',
            'priority' => 'low',
            'attempts' => 0,
            'payload' => [
                'job' => 'test_email',
                'data' => $this->emailData()
            ]
        ]);

        $this->getPrivateMethodInvoker($worker, 'handleWork')($work, $config, null, null);
    }

    public function testRepeatedEmailJobDoesNotSendAgain(): void
    {
        service('mailer')->send('customer@example.test', 'Shipment', 'Tracking');

        $data = $this->message('send_email');
        $email = $this->createMock(Email::class);

        $email->expects($this->once())->method('send')->willReturn(true);

        $job = new SendEmailJob($data, $email);

        $this->assertTrue($job->process());
        $this->assertTrue($job->process());
    }

    public function testDeletionCancelsPreviouslyPublishedCustomerEmail(): void
    {
        $id = $this->createInvoice();

        service('mailer')->send('customer@example.test', 'Shipment', 'Tracking', $id);

        $data = $this->message('send_email');

        service('invoiceWebhookService')->handle(['event' => 'invoice.deleted', 'invoice_id' => 'INV-2026-00123']);

        $email = $this->createMock(Email::class);

        $email->expects($this->never())->method('send');
        $this->assertTrue((new SendEmailJob($data, $email))->process());
    }
}
