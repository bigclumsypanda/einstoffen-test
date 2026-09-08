<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class FakeUps extends BaseConfig
{
    /**
     * Base URL of the shipping provider API (the fakeups mock server).
     */
    public string $baseUrl = 'http://mockoon:3000';

    /**
     * Request timeout in seconds.
     */
    public int $timeout = 10;
}
