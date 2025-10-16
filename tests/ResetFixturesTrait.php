<?php

namespace App\Tests;

use Doctrine\Bundle\MongoDBBundle\Command\LoadDataFixturesDoctrineODMCommand;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

trait ResetFixturesTrait
{    /**
 * Boots the Kernel for this test.
 */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        $kernel = parent::bootKernel($options);

        $command = self::getContainer()->get('doctrine_mongodb.odm.command.load_data_fixtures');
        assert($command instanceof LoadDataFixturesDoctrineODMCommand);

        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $command->run(
            $input,
            new NullOutput()
        );

        return $kernel;
    }
}