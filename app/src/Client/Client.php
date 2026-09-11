<?php

namespace App\Client;

/**
 * A reference record whose contact details are sensitive.
 */
final class Client
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
    ) {}
}
