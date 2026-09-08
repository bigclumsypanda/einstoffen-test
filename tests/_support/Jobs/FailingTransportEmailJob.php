<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use App\Jobs\SendEmailJob;

class FailingTransportEmailJob extends SendEmailJob
{
    public function __construct(array $data)
    {
        parent::__construct($data, service('email'));
    }
}
