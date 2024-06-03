<?php

namespace App;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[Autoconfigure(lazy: true)]
#[Autoconfigure(shared: false)]
#[AutoconfigureTag('bar')]
#[AutoconfigureTag('foo', ['priority' => 2])]
interface MyInterface
{
}