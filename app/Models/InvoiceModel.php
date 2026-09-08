<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Invoice;
use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table = 'invoices';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = Invoice::class;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'invoice_id',
        'customer_id',
        'status',
        'amount',
        'currency',
        'due_date',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $skipValidation = false;

    public function findByInvoiceId(string $invoiceId): ?Invoice
    {
        return $this->where('invoice_id', $invoiceId)->first();
    }
}
