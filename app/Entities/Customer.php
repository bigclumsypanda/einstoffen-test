<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Customer extends Entity
{
    protected $attributes = [
        'crm_id' => null,
        'name' => null,
        'email' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'crm_id' => 'string',
        'name' => 'string',
        'email' => 'string',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];
}
