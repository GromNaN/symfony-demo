<?php

declare(strict_types=1);

namespace App\Command;

use App\Document\CodeFile;
use App\Document\SourceType;
use App\Ingestion\Chunking\PhpCodeChunker;
use App\Ingestion\Pipeline\IngestionPipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'app:ingest:code', description: 'Ingest local PHP source files into the knowledge base, chunked by symbol.')]
final class IngestCodeCommand extends Command
{
    public function __construct(
        private readonly PhpCodeChunker $chunker,
        private readonly IngestionPipeline $pipeline,
        private readonly string $githubRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Local path to the src/ folder', 'var/repo-cache/src')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branch used to build GitHub blob URLs', '5.x');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getOption('path');
        $branch = (string) $input->getOption('branch');

        if (!is_dir($path)) {
            $io->error(\sprintf('Code path "%s" does not exist. Clone the repository first (see README).', $path));

            return Command::FAILURE;
        }

        $finder = (new Finder())->files()->in($path)->name('*.php');
        $fileCount = 0;
        $chunkCount = 0;

        foreach ($finder as $file) {
            $rawContent = $file->getContents();
            $relativePath = 'src/' . $file->getRelativePathname();
            $url = \sprintf('https://github.com/%s/blob/%s/%s', $this->githubRepo, $branch, $relativePath);

            $codeFile = new CodeFile($relativePath, $rawContent, $url);
            $context = ['path' => $relativePath, 'url' => $url];
            $chunks = $this->chunker->chunk($rawContent, $context);
            $chunkCount += $this->pipeline->ingest($codeFile, SourceType::CodeFile, $chunks);
            ++$fileCount;

            if ($fileCount % 50 === 0) {
                $io->writeln(\sprintf('  … %d files processed', $fileCount));
            }
        }

        $this->pipeline->flush();
        $io->success(\sprintf('Ingested %d PHP files, wrote %d chunks.', $fileCount, $chunkCount));

        return Command::SUCCESS;
    }
}
