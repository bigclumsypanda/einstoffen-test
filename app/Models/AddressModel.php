<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Address;
use CodeIgniter\Model;

class AddressModel extends Model
{
    protected $table = 'addresses';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = Address::class;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'customer_id',
        'street',
        'city',
        'postal_code',
        'country',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $skipValidation = false;
}
