<?php

declare(strict_types=1);

namespace App\Command;

use App\Document\Chunk;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Driver\Exception\CommandException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:index:create', description: 'Create (or update) the Atlas vector search index on the chunks collection.')]
final class CreateVectorIndexCommand extends Command
{
    public function __construct(private readonly DocumentManager $dm)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        // The initial autoEmbed backfill of a few hundred/thousand chunks can take longer
        // than the SchemaManager default (10s) — this showed up on the first real run.
        $this->addOption('wait-ms', null, InputOption::VALUE_REQUIRED, 'Max time to wait for the index to become READY', '300000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $schemaManager = $this->dm->getSchemaManager();

        try {
            $io->writeln('Creating search index(es) on the "chunks" collection…');
            $schemaManager->createDocumentSearchIndexes(Chunk::class);
        } catch (CommandException $e) {
            if (!str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }

            $io->writeln('Index already exists, skipping creation.');
        }

        $io->writeln('Waiting for the index to become READY (this triggers the initial autoEmbed backfill)…');
        $schemaManager->waitForSearchIndexes([Chunk::class], (int) $input->getOption('wait-ms'));

        $io->success('Vector search index "chunks_vector_idx" is ready.');

        return Command::SUCCESS;
    }
}
