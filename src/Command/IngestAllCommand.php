<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:ingest:all', description: 'Run all ingestion steps in sequence (doc, code, issues, pull requests).')]
final class IngestAllCommand extends Command
{
    /** Doc/code first: no rate limit, useful to validate chunking before spending GitHub API quota. */
    private const STEPS = ['app:ingest:doc', 'app:ingest:code', 'app:ingest:issues', 'app:ingest:pull-requests'];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $application = $this->getApplication();
        if ($application === null) {
            $io->error('No application available.');

            return Command::FAILURE;
        }

        foreach (self::STEPS as $step) {
            $io->section($step);
            $exitCode = $application->find($step)->run(new ArrayInput([]), $output);

            if ($exitCode !== Command::SUCCESS) {
                $io->error(\sprintf('"%s" failed (exit code %d), stopping.', $step, $exitCode));

                return $exitCode;
            }
        }

        $io->success('All ingestion steps completed.');

        return Command::SUCCESS;
    }
}
