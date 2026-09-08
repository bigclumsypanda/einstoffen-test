<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Invoice extends Entity
{
    protected $attributes = [
        'invoice_id' => null,
        'customer_id' => null,
        'status' => null,
        'amount' => null,
        'currency' => null,
        'due_date' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'invoice_id' => 'string',
        'customer_id' => 'integer',
        'status' => 'string',
        'amount' => 'string',
        'currency' => 'string',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];
}
