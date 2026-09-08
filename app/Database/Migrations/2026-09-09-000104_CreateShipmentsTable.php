<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShipmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'shipment_id' => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'tracking_number' => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'user' => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'invoice_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'address_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'varchar', 'constraint' => 32, 'null' => false],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('shipment_id');
        $this->forge->addKey('invoice_id');
        $this->forge->addKey('address_id');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('address_id', 'addresses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shipments');
    }

    public function down(): void
    {
        $this->forge->dropTable('shipments');
    }
}
