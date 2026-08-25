<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests;

use Doctrine\Bundle\MongoDBBundle\Command\LoadDataFixturesDoctrineODMCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

trait ResetFixturesTrait
{
    /**
     * Boots the Kernel for this test.
     */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        $kernel = parent::bootKernel($options);

        $command = self::getContainer()->get('doctrine_mongodb.odm.command.load_data_fixtures');
        \assert($command instanceof LoadDataFixturesDoctrineODMCommand);

        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $command->run(
            $input,
            new NullOutput()
        );

        return $kernel;
    }
}
