<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UniqueInvoiceShipment extends Migration
{
    public function up(): void
    {
        $this->forge->addUniqueKey('invoice_id', 'shipments_invoice_unique');
        $this->forge->processIndexes('shipments');
    }

    public function down(): void
    {
        $this->forge->dropKey('shipments', 'shipments_invoice_unique');
    }
}
