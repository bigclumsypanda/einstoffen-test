<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AddressDto;

final class DemoAddressGenerator
{
    public function generate(): AddressDto
    {
        $addresses = [
            new AddressDto('Musterstrasse 10', 'Zürich', '8000', 'CH'),
            new AddressDto('Bahnhofstrasse 22', 'Bern', '3000', 'CH'),
            new AddressDto('Rue de Lausanne 5', 'Genève', '1201', 'CH'),
        ];

        return $addresses[array_rand($addresses)];
    }
}
