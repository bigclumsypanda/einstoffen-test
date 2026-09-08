<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Address extends Entity
{
    protected $attributes = [
        'customer_id' => null,
        'street' => null,
        'city' => null,
        'postal_code' => null,
        'country' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'street' => 'string',
        'city' => 'string',
        'postal_code' => 'string',
        'country' => 'string',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];
}
