<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Events\QueueEvent;
use Throwable;

final class QueueFailureListener
{
    /**
     * Notify once after retries; never recursively notify about an admin email.
     */
    public static function handle(QueueEvent $event): void
    {
        $job = $event->getMetadata('job');

        if (!$job instanceof QueueJob) {
            return;
        }

        $data = $job->payload['data'] ?? [];
        $reason = $event->getExceptionMessage() ?? 'Unknown queue failure';

        log_message('error', 'Queue job {id} exhausted retries: {reason}', ['id' => $job->id, 'reason' => $reason]);

        if ($data['admin_notification'] ?? false) {
            return;
        }

        try {
            service('adminNotifier')->notifyFailedWebhook((string) ($data['invoice_id'] ?? 'unknown'), $reason);
        } catch (Throwable $e) {
            log_message('error', 'Queue failure notification failed: {reason}', ['reason' => $e->getMessage()]);
        }
    }
}
