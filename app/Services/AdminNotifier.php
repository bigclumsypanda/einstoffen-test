<?php

declare(strict_types=1);

namespace App\Services;

use Config\Email as EmailConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class AdminNotifier
{
    public function __construct(private MailDispatcher $mailer, private LoggerInterface $logger)
    {
    }

    /** A notification failure must never replace the original processing error. */
    public function notifyFailedWebhook(string $invoiceId, string $reason): bool
    {
        $this->logger->error('Invoice {invoice_id} processing failed: {reason}', [
            'invoice_id' => $invoiceId, 'reason' => $reason,
        ]);

        try {
            $recipient = trim((string) env('webhook.adminEmail', config(EmailConfig::class)->recipients));

            if ($recipient === '') {
                throw new RuntimeException('No administrator email configured.');
            }

            return $this->mailer->send(
                $recipient,
                '[Webhook] Invoice processing failed',
                "Invoice: {$invoiceId}\nReason: {$reason}",
            );
        } catch (Throwable $e) {
            $this->logger->error('Unable to persist admin notification for {invoice_id}: {reason}', [
                'invoice_id' => $invoiceId, 'reason' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
