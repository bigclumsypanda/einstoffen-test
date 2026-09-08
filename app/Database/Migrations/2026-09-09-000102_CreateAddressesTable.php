<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAddressesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'street' => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'city' => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'postal_code' => ['type' => 'varchar', 'constraint' => 32, 'null' => false],
            'country' => ['type' => 'varchar', 'constraint' => 128, 'null' => false],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('addresses');
    }

    public function down(): void
    {
        $this->forge->dropTable('addresses');
    }
}
