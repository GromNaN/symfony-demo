<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use App\State\StationStateProvider;

#[ApiResource(provider: StationStateProvider::class)]
class Station
{
    public int $id;

    /** Address of the Gas station */
    public Address $address;

    /** @var array{0: float, 1: float} Geographic coordinates */
    public array $coordinates;

    /** @var list<string> List of provided services */
    public array $services;

    /** @var list<FuelPrice> */
    public array $prices;
}
