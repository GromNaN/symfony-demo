<?php

declare(strict_types=1);

namespace App\Command;

use App\Document\Comment;
use App\Document\Issue;
use App\Document\SourceType;
use App\Ingestion\Chunking\MarkdownChunker;
use App\Ingestion\GitHub\CommentFetcher;
use App\Ingestion\GitHub\IssueFetcher;
use App\Ingestion\Pipeline\IngestionPipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:ingest:issues', description: 'Ingest GitHub issues and their comments into the knowledge base.')]
final class IngestIssuesCommand extends Command
{
    public function __construct(
        private readonly IssueFetcher $issueFetcher,
        private readonly CommentFetcher $commentFetcher,
        private readonly MarkdownChunker $chunker,
        private readonly IngestionPipeline $pipeline,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $issueCount = 0;
        $commentCount = 0;
        $chunkCount = 0;

        foreach ($this->issueFetcher->fetchAll() as $raw) {
            $issue = new Issue(
                number: $raw['number'],
                title: $raw['title'] ?? '',
                body: $raw['body'] ?? '',
                state: $raw['state'] ?? 'unknown',
                url: $raw['html_url'] ?? '',
                author: $raw['user']['login'] ?? 'unknown',
                labels: array_map(static fn (mixed $label): string => \is_array($label) ? (string) $label['name'] : (string) $label, $raw['labels'] ?? []),
                updatedAt: new \DateTimeImmutable($raw['updated_at'] ?? 'now'),
            );

            $context = ['title' => $issue->title, 'url' => $issue->url, 'number' => $issue->number, 'state' => $issue->state];
            $chunks = $this->chunker->chunk($issue->title . "\n\n" . $issue->body, $context);
            $chunkCount += $this->pipeline->ingest($issue, SourceType::Issue, $chunks);
            ++$issueCount;

            foreach ($this->commentFetcher->fetchForIssue($issue->number) as $rawComment) {
                $comment = new Comment(
                    githubCommentId: $rawComment['id'],
                    parentId: $issue->id,
                    body: $rawComment['body'] ?? '',
                    author: $rawComment['user']['login'] ?? 'unknown',
                    url: $rawComment['html_url'] ?? '',
                    createdAt: new \DateTimeImmutable($rawComment['created_at'] ?? 'now'),
                );

                $commentContext = ['parentTitle' => $issue->title, 'url' => $comment->url];
                $commentChunks = $this->chunker->chunk($comment->body, $commentContext);
                $chunkCount += $this->pipeline->ingest($comment, SourceType::Comment, $commentChunks);
                ++$commentCount;
            }

            if ($issueCount % 20 === 0) {
                $io->writeln(\sprintf('  … %d issues processed', $issueCount));
            }
        }

        $this->pipeline->flush();
        $io->success(\sprintf('Ingested %d issues and %d comments, wrote %d chunks.', $issueCount, $commentCount, $chunkCount));

        return Command::SUCCESS;
    }
}
