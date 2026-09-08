<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Shipment extends Entity
{
    protected $attributes = [
        'shipment_id' => null,
        'tracking_number' => null,
        'user' => null,
        'invoice_id' => null,
        'address_id' => null,
        'status' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'shipment_id' => 'string',
        'tracking_number' => 'string',
        'user' => 'string',
        'invoice_id' => 'integer',
        'address_id' => '?integer',
        'status' => 'string',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];
}
