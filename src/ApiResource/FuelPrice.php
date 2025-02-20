<?php

namespace App\ApiResource;

class FuelPrice
{
    public int $id;

    /** Fuel name */
    public string $name;

    /** Fuel price */
    public float $price;

    /** Last update date */
    public \DateTimeImmutable $updatedAt;
}
