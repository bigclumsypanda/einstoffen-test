<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

class MigrateApplication extends BaseCommand
{
    protected $group = 'Application';
    protected $name = 'app:migrate';
    protected $description = 'Migrate all namespaces, returning a failing exit code on error.';

    public function run(array $params)
    {
        try {
            if (!service('migrations')->setNamespace(null)->latest()) {
                return EXIT_ERROR;
            }

            CLI::write('Application and queue migrations applied.', 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }
    }
}
