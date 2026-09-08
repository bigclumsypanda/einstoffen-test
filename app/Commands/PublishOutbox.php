<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Throwable;

class PublishOutbox extends BaseCommand
{
    protected $group = 'Application';
    protected $name = 'outbox:publish';
    protected $description = 'Publish durable messages to Redis; use --watch for continuous polling.';
    protected $options = ['--watch' => 'Poll every two seconds.'];

    public function run(array $params)
    {
        do {
            try {
                service('outbox')->publish();
            } catch (Throwable $e) {
                log_message('error', 'Outbox publisher failed: {reason}', ['reason' => $e->getMessage()]);
            }
            // Reconnect after a Redis restart on the next batch.
            Services::resetSingle('queue');
            if (! CLI::getOption('watch')) {
                break;
            }
            sleep(2);
        } while (true);
    }
}
