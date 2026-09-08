<?php

declare(strict_types=1);

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Exceptions\InvalidWebhookException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Invoices extends BaseController
{
    public function create(): ResponseInterface
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->response->setHeader('Allow', 'POST')->setStatusCode(405)->setJSON([
                'error' => 'Method not allowed',
            ]);
        }

        try {
            $payload = service('webhookValidator')->parse((string) $this->request->getBody());
        } catch (InvalidWebhookException $e) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => $e->getMessage(), 'messages' => $e->errors]);
        }

        try {
            service('invoiceWebhookService')->handle($payload);
        } catch (Throwable $e) {
            service('adminNotifier')->notifyFailedWebhook($payload['invoice_id'], $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['error' => 'Processing failed']);
        }

        $message = $payload['event'] === 'invoice.deleted' ? 'Invoice deleted.' : 'Invoice accepted.';

        return $this->response
            ->setStatusCode(202)
            ->setJSON(['message' => $message]);
    }
}
