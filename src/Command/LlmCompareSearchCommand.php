<?php

declare(strict_types=1);

namespace App\Command;

use App\Eval\HaikuSearchEvaluator;
use App\Search\GitHubSearchService;
use App\Search\LexicalSearchService;
use App\Search\SearchResult;
use App\Search\VectorSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Puts an LLM in the search loop: for each query, it (1) answers from its own knowledge with no
 * context, then for each backend (2) reformulates the query the way it would for that backend,
 * (3) runs the real search, and (4) answers again using only those results. Produces a Markdown
 * report for manual relevance judgment, since answers are free text (no single "Top-1" to score).
 */
#[AsCommand(name: 'app:eval:llm-compare', description: 'Compare Vector Search, Lucene and GitHub Search via an LLM that reformulates queries and answers from results.')]
final class LlmCompareSearchCommand extends Command
{
    /** @var array<string, array{label: string, description: string}> */
    private const BACKENDS = [
        'vector' => [
            'label' => 'Vector Search (autoEmbed)',
            'description' => 'A semantic vector search index (MongoDB Atlas automated embedding) over EasyAdminBundle issues, '
                . 'pull requests, comments, documentation and source code. It understands natural language and finds '
                . 'conceptually related content even without exact keyword overlap — phrase a natural-language query.',
        ],
        'lucene' => [
            'label' => 'Lucene full-text',
            'description' => 'A classic Atlas Search full-text (Lucene) index over the same EasyAdminBundle content. It matches '
                . 'based on keyword/term frequency, not meaning — phrase a short, keyword-focused query.',
        ],
        'github' => [
            'label' => 'GitHub Search',
            'description' => "GitHub's issue and code search for the EasyCorp/EasyAdminBundle repository. It matches literal "
                . 'keywords and supports GitHub search qualifiers — phrase a short, keyword-focused query.',
        ],
    ];

    public function __construct(
        private readonly HaikuSearchEvaluator $evaluator,
        private readonly VectorSearchService $vectorSearch,
        private readonly LexicalSearchService $lexicalSearch,
        private readonly GitHubSearchService $githubSearch,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('queries', null, InputOption::VALUE_REQUIRED, 'Path to the YAML query set', 'config/eval/queries.yaml')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Only process the first N queries')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Write the report to this file instead of stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $queries = Yaml::parseFile((string) $input->getOption('queries'));
        $limit = $input->getOption('limit');
        if ($limit !== null) {
            $queries = \array_slice($queries, 0, (int) $limit);
        }

        $header = "# LLM-assisted search comparison (Claude Haiku)\n\n"
            . "For each question: the model's cold answer (no retrieval), then per backend the query it "
            . "formulated, the results it got, and the answer it gave using only those results.\n\n";

        $outputPath = $input->getOption('output');
        // Each query costs ~7 model calls (real time and money); write incrementally so a crash
        // partway through (a rate limit, a malformed LLM-generated search query...) doesn't throw
        // away everything already computed — happened once with the all-at-the-end version.
        $writeSection = $outputPath !== null
            ? static function (string $text) use ($outputPath): void { file_put_contents((string) $outputPath, $text, \FILE_APPEND); }
            : static function (string $text) use ($output): void { $output->writeln($text); };

        if ($outputPath !== null) {
            file_put_contents((string) $outputPath, $header);
        } else {
            $output->writeln($header);
        }

        foreach ($queries as $entry) {
            $query = (string) $entry['query'];
            $io->section($query);
            $writeSection($this->compareOne($query, $io));
        }

        if ($outputPath !== null) {
            $io->success(\sprintf('Report written to %s.', $outputPath));
        }

        return Command::SUCCESS;
    }

    private function compareOne(string $query, SymfonyStyle $io): string
    {
        $io->writeln('  cold answer…');
        $coldAnswer = $this->evaluator->answerFromKnowledge($query);

        $section = "## {$query}\n\n";
        $section .= "**Cold answer (no retrieval):**\n\n> " . str_replace("\n", "\n> ", $coldAnswer) . "\n\n";
        $section .= "*Did the model already know a specific, correct answer here? ______*\n\n";

        foreach (self::BACKENDS as $key => $backend) {
            $io->writeln("  {$backend['label']}…");

            $formulatedQuery = $this->evaluator->formulateQuery($query, $backend['description']);
            $contextItems = $this->search($key, $formulatedQuery);
            $answer = $this->evaluator->answerWithContext($query, $contextItems);

            $section .= "### {$backend['label']}\n\n";
            $section .= "Reformulated query: `{$formulatedQuery}`\n\n";
            $section .= "Results used:\n";
            foreach ($contextItems as $item) {
                $section .= \sprintf("- [%s](%s)\n", $item['title'], $item['url'] ?? '#');
            }
            $section .= "\n**Answer:**\n\n> " . str_replace("\n", "\n> ", $answer) . "\n\n";
            $section .= "*Pertinent? ______*\n\n";
        }

        return $section . "---\n\n";
    }

    /** @return list<array{title: string, url: ?string, snippet: string}> */
    private function search(string $backend, string $query): array
    {
        return match ($backend) {
            'vector' => $this->fromSearchResults($this->vectorSearch->search($query, limit: 3)),
            'lucene' => $this->fromSearchResults($this->lexicalSearch->search($query, limit: 3)),
            'github' => [
                ...$this->githubSearch->searchIssues($query, 2),
                ...$this->githubSearch->searchCode($query, 2),
            ],
            default => throw new \LogicException("Unknown backend \"{$backend}\"."),
        };
    }

    /**
     * @param list<SearchResult> $results
     *
     * @return list<array{title: string, url: ?string, snippet: string}>
     */
    private function fromSearchResults(array $results): array
    {
        return array_map(static fn (SearchResult $r): array => [
            'title' => (string) ($r->metadata['title'] ?? $r->metadata['symbolName'] ?? $r->parentId),
            'url' => $r->metadata['url'] ?? null,
            'snippet' => mb_substr($r->content, 0, 500),
        ], $results);
    }
}
