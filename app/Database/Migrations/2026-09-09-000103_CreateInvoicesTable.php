<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInvoicesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'invoice_id' => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'customer_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'status' => ['type' => 'varchar', 'constraint' => 32, 'null' => false],
            'amount' => ['type' => 'decimal', 'constraint' => '12,2', 'null' => false],
            'currency' => ['type' => 'varchar', 'constraint' => 8, 'null' => false],
            'due_date' => ['type' => 'date', 'null' => false],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('invoice_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('invoices');
    }

    public function down(): void
    {
        $this->forge->dropTable('invoices');
    }
}
