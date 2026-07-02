<?php

declare(strict_types=1);

namespace App\Command;

use App\Document\DocPage;
use App\Document\SourceType;
use App\Ingestion\Chunking\RstChunker;
use App\Ingestion\Pipeline\IngestionPipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * EasyAdminBundle has no active GitHub wiki (`has_wiki: false`); its doc/*.rst folder,
 * ingested here, stands in as the "wiki" source for this demo.
 */
#[AsCommand(name: 'app:ingest:doc', description: 'Ingest local .rst documentation pages into the knowledge base.')]
final class IngestDocCommand extends Command
{
    public function __construct(
        private readonly RstChunker $chunker,
        private readonly IngestionPipeline $pipeline,
        private readonly string $githubRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Local path to the doc/ folder', 'var/repo-cache/doc')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branch used to build GitHub blob URLs', '5.x');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getOption('path');
        $branch = (string) $input->getOption('branch');

        if (!is_dir($path)) {
            $io->error(\sprintf('Doc path "%s" does not exist. Clone the repository first (see README).', $path));

            return Command::FAILURE;
        }

        $finder = (new Finder())->files()->in($path)->name('*.rst');
        $pageCount = 0;
        $chunkCount = 0;

        foreach ($finder as $file) {
            $rawContent = $file->getContents();
            $relativePath = 'doc/' . $file->getRelativePathname();
            $title = $this->extractTitle($rawContent) ?? $file->getFilenameWithoutExtension();
            $url = \sprintf('https://github.com/%s/blob/%s/%s', $this->githubRepo, $branch, $relativePath);

            $docPage = new DocPage($relativePath, $title, $rawContent, $url);
            $context = ['title' => $title, 'url' => $url, 'path' => $relativePath];
            $chunks = $this->chunker->chunk($rawContent, $context);
            $chunkCount += $this->pipeline->ingest($docPage, SourceType::DocPage, $chunks);
            ++$pageCount;
        }

        $this->pipeline->flush();
        $io->success(\sprintf('Ingested %d doc pages, wrote %d chunks.', $pageCount, $chunkCount));

        return Command::SUCCESS;
    }

    private function extractTitle(string $rawContent): ?string
    {
        $lines = explode("\n", $rawContent, 4);

        return trim($lines[0] ?? '') !== '' ? trim($lines[0]) : null;
    }
}
