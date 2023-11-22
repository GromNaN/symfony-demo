<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\MongoDB\Codec\PostCodec;
use App\MongoDB\Codec\UserCodec;
use App\MongoDB\DataFixtures\FixtureInterface;
use App\MongoDB\Document\Post;
use App\MongoDB\Document\User;
use App\MongoDB\Repository;
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Collection;
use MongoDB\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'mongodb:database:drop',
    description: 'Drop the database',
)]
class MongoDBDatabaseDrop extends Command
{
    public function __construct(
        #[AutowireDatabase('blog')]
        private Database $database,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->database->drop();

        return Command::SUCCESS;
    }
}
