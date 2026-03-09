<?php

namespace App;

use App\Encryption\MetadataInjection;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();

        $this->getContainer()->get(MetadataInjection::class)->boot();
    }
}
