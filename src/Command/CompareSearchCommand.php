<?php

declare(strict_types=1);

namespace App\Command;

use App\Eval\ComparisonReportExporter;
use App\Eval\ComparisonRow;
use App\Search\GitHubSearchService;
use App\Search\VectorSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:eval:compare', description: 'Compare MongoDB Vector Search vs GitHub Search relevance on a fixed query set.')]
final class CompareSearchCommand extends Command
{
    public function __construct(
        private readonly VectorSearchService $vectorSearch,
        private readonly GitHubSearchService $githubSearch,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('queries', null, InputOption::VALUE_REQUIRED, 'Path to the YAML query set', 'config/eval/queries.yaml')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Report format: markdown or csv', 'markdown')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Write the report to this file instead of stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $queries = Yaml::parseFile((string) $input->getOption('queries'));
        $rows = [];

        foreach ($queries as $entry) {
            $query = (string) $entry['query'];
            $kind = (string) ($entry['kind'] ?? 'unknown');
            $io->writeln("Comparing: <info>{$query}</info>");

            $vectorStart = microtime(true);
            $vectorResults = $this->vectorSearch->search($query, limit: 5);
            $vectorLatencyMs = (microtime(true) - $vectorStart) * 1000;

            $githubStart = microtime(true);
            $issueResults = $this->githubSearch->searchIssues($query, 5);
            $codeResults = $this->githubSearch->searchCode($query, 5);
            $githubLatencyMs = (microtime(true) - $githubStart) * 1000;

            $rows[] = new ComparisonRow(
                query: $query,
                kind: $kind,
                vectorTop: $vectorResults[0] ?? null,
                githubIssueTop: $issueResults[0] ?? null,
                githubCodeTop: $codeResults[0] ?? null,
                vectorLatencyMs: $vectorLatencyMs,
                githubLatencyMs: $githubLatencyMs,
            );
        }

        $format = (string) $input->getOption('format');
        $report = $format === 'csv' ? ComparisonReportExporter::toCsv($rows) : ComparisonReportExporter::toMarkdown($rows);

        $outputPath = $input->getOption('output');
        if ($outputPath !== null) {
            file_put_contents((string) $outputPath, $report);
            $io->success(\sprintf('Report written to %s (fill in the "Pertinent" columns by hand).', $outputPath));
        } else {
            $output->writeln($report);
        }

        return Command::SUCCESS;
    }
}
