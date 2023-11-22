<?php

namespace App\MongoDB\DataFixtures;


use App\MongoDB\Repository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface contract for fixture classes to implement.
 */
#[AutoconfigureTag('mongodb.fixture')]
interface FixtureInterface
{
    /**
     * Load data fixtures into the provided repository
     */
    public function load(Repository $repository);
}
