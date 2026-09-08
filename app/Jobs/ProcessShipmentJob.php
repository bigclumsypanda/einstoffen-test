<?php

declare(strict_types=1);

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use Throwable;

class ProcessShipmentJob extends BaseJob
{
    protected int $retryAfter = 30;
    protected int $tries = 3;

    /**
     * @throws Throwable
     */
    public function process(): ?true
    {
        try {
            service('shipmentService')->process($this->data);

            return true;
        } catch (Throwable $e) {
            log_message('error', 'Shipment job failed for invoice {invoice_id}: {reason}', [
                'invoice_id' => $this->data['invoice_id'] ?? 'unknown', 'reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
