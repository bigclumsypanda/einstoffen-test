<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class OutboxModel extends Model
{
    protected $table = 'outbox';

    protected $allowedFields = [
        'deduplication_key',
        'invoice_id',
        'job',
        'priority',
        'payload',
        'created_at',
        'dispatched_at',
        'completed_at',
    ];
}
