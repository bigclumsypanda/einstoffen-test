<?php

declare(strict_types=1);

namespace App\Jobs;

use CodeIgniter\Email\Email;
use CodeIgniter\Queue\BaseJob;
use Config\Services;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class SendEmailJob extends BaseJob
{
    protected int $retryAfter = 60;
    protected int $tries = 3;

    public function __construct(array $data, private ?Email $email = null, private ?LoggerInterface $logger = null)
    {
        parent::__construct($data);
    }

    /**
     * @throws Throwable
     */
    public function process(): ?true
    {
        $db = db_connect();

        try {
            if (!$db->transBegin()) {
                throw new RuntimeException('Unable to start email transaction.');
            }

            if ($this->lockInvoice($db)) {
                return true;
            }

            if ($this->lockOutboxMessage($db)) {
                return true;
            }

            if (!is_string($this->data['to'] ?? null) || empty($this->data['to'])) {
                throw new RuntimeException('Email job has no recipient.');
            }

            $email = $this->email ?? Services::email(null, false);
            $fromEmail = $this->data['from_email'] ?? '';
            $fromName = $this->data['from_name'] ?? 'Application';
            $subject = $this->data['subject'] ?? '';
            $message = $this->data['message'] ?? '';

            $email->clear(true);
            $email->setFrom($fromEmail, $fromName);
            $email->setTo($this->data['to']);
            $email->setSubject($subject);
            $email->setMessage($message);

            if (!$email->send(false)) {
                throw new RuntimeException('SMTP delivery failed: ' . strip_tags($email->printDebugger(['headers'])));
            }

            if (isset($this->data['outbox_id'])) {
                service('outbox')->complete((int) $this->data['outbox_id']);
            }

            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('Unable to commit email completion.');
            }

            return true;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();

            $logger = $this->logger ?? service('logger');

            $logger->error('Email delivery failed for {to}: {reason}', [
                'to' => $this->data['to'] ?? 'unknown',
                'reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function lockInvoice($db): bool
    {
        if (!isset($this->data['invoice_id'])) {
            return false;
        }

        $invoice = $db
            ->query('SELECT id FROM invoices WHERE id = ? FOR UPDATE', [$this->data['invoice_id']])
            ->getRowArray();

        if (is_null($invoice)) {
            $db->transRollback();

            return true;
        }

        return false;
    }

    private function lockOutboxMessage($db): bool
    {
        if (!isset($this->data['outbox_id'])) {
            return false;
        }

        $message = $db
            ->query('SELECT * FROM outbox WHERE id = ? FOR UPDATE', [$this->data['outbox_id']])
            ->getRowArray();

        if ($message === null || $message['completed_at'] !== null) {
            $db->transRollback();

            return true;
        }

        return false;
    }
}
