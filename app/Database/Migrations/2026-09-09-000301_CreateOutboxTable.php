<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOutboxTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'deduplication_key' => ['type' => 'VARCHAR', 'constraint' => 128],
            'invoice_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'job' => ['type' => 'VARCHAR', 'constraint' => 64],
            'priority' => ['type' => 'VARCHAR', 'constraint' => 16],
            'payload' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
            'dispatched_at' => ['type' => 'DATETIME', 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('deduplication_key');
        $this->forge->addKey(['dispatched_at', 'id']);
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('outbox');
    }

    public function down(): void
    {
        $this->forge->dropTable('outbox');
    }
}
