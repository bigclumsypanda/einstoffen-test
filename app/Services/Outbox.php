<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OutboxModel;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use RuntimeException;
use Throwable;

/**
 * Durable publication intent
 */
class Outbox
{
    public function __construct(private OutboxModel $model)
    {
    }

    public function add(string $job, array $payload, string $priority, string $key, ?int $invoiceId = null): int
    {
        $existing = $this->model->where('deduplication_key', $key)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $id = $this->model->insert([
            'deduplication_key' => $key,
            'invoice_id' => $invoiceId,
            'job' => $job,
            'priority' => $priority,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($id === false) {
            throw new RuntimeException('Unable to save outbox message.');
        }
        return (int) $id;
    }

    /**
     * Publish a bounded batch. Failure leaves the durable row available for the next run.
     */
    public function publish(?QueueInterface $queue = null): int
    {
        $rows = $this->model->where('dispatched_at', null)->orderBy('id')->findAll(100);
        $count = 0;
        foreach ($rows as $row) {
            try {
                $queue ??= service('queue');
                $data = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
                $data['outbox_id'] = (int) $row['id'];
                $result = $queue->setPriority($row['priority'])->push('default', $row['job'], $data);
                if (! $result->getStatus()) {
                    throw new RuntimeException($result->getError() ?? 'Queue publication failed.');
                }
                if (! $this->model->update($row['id'], ['dispatched_at' => gmdate('Y-m-d H:i:s')])) {
                    throw new RuntimeException('Unable to mark outbox message as dispatched.');
                }
                $count++;
            } catch (Throwable $e) {
                log_message('error', 'Outbox {id} publication failed: {reason}', [
                    'id' => $row['id'], 'reason' => $e->getMessage(),
                ]);
                break;
            }
        }
        return $count;
    }

    public function complete(int $id): void
    {
        if (! $this->model->update($id, ['completed_at' => gmdate('Y-m-d H:i:s')])) {
            throw new RuntimeException('Unable to mark outbox message as completed.');
        }
    }
}
