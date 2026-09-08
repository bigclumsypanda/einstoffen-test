<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Shipment;
use CodeIgniter\Model;

class ShipmentModel extends Model
{
    protected $table = 'shipments';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = Shipment::class;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'shipment_id',
        'tracking_number',
        'user',
        'invoice_id',
        'address_id',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $skipValidation = false;
    public function findByShipmentId(string $shipmentId): ?Shipment
    {
        return $this->where('shipment_id', $shipmentId)->first();
    }
}
