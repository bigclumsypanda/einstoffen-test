<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Entities\Address;

final class AddressDto
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly string $country,
    ) {
    }

    public static function fromEntity(Address $address): self
    {
        return new self($address->street, $address->city, $address->postal_code, $address->country);
    }

    /** @return array{street: string, city: string, postalCode: string, country: string} */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
