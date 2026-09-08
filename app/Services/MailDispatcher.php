<?php

declare(strict_types=1);

namespace App\Services;

use Config\Email as EmailConfig;

class MailDispatcher
{
    public function __construct(private Outbox $outbox)
    {
    }

    public function send(string $to, string $subject, string $message, ?int $invoiceId = null, ?string $key = null,): bool
    {
        $config = config(EmailConfig::class);

        $jobData = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'from_email' => $config->fromEmail,
            'from_name' => $config->fromName,
            'invoice_id' => $invoiceId,
            'admin_notification' => $invoiceId === null,
        ];

        $key = $key ?? bin2hex(random_bytes(16));

        $this->outbox->add(
            'send_email',
            $jobData,
            'low',
            $key,
            $invoiceId
        );

        return true;
    }
}
