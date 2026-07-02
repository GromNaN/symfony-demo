<?php

declare(strict_types=1);

namespace App\Command;

use App\Document\Comment;
use App\Document\PullRequest;
use App\Document\SourceType;
use App\Ingestion\Chunking\MarkdownChunker;
use App\Ingestion\GitHub\CommentFetcher;
use App\Ingestion\GitHub\PullRequestFetcher;
use App\Ingestion\Pipeline\IngestionPipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:ingest:pull-requests', description: 'Ingest GitHub pull requests and their comments into the knowledge base.')]
final class IngestPullRequestsCommand extends Command
{
    public function __construct(
        private readonly PullRequestFetcher $pullRequestFetcher,
        private readonly CommentFetcher $commentFetcher,
        private readonly MarkdownChunker $chunker,
        private readonly IngestionPipeline $pipeline,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $prCount = 0;
        $commentCount = 0;
        $chunkCount = 0;

        foreach ($this->pullRequestFetcher->fetchAll() as $raw) {
            $pullRequest = new PullRequest(
                number: $raw['number'],
                title: $raw['title'] ?? '',
                body: $raw['body'] ?? '',
                state: $raw['state'] ?? 'unknown',
                url: $raw['html_url'] ?? '',
                author: $raw['user']['login'] ?? 'unknown',
                merged: (bool) ($raw['merged_at'] ?? false),
                updatedAt: new \DateTimeImmutable($raw['updated_at'] ?? 'now'),
            );

            $context = ['title' => $pullRequest->title, 'url' => $pullRequest->url, 'number' => $pullRequest->number, 'state' => $pullRequest->state];
            $chunks = $this->chunker->chunk($pullRequest->title . "\n\n" . $pullRequest->body, $context);
            $chunkCount += $this->pipeline->ingest($pullRequest, SourceType::PullRequest, $chunks);
            ++$prCount;

            // A pull request is also an "issue" as far as its general comments endpoint is concerned.
            foreach ($this->commentFetcher->fetchForIssue($pullRequest->number) as $rawComment) {
                $comment = new Comment(
                    githubCommentId: $rawComment['id'],
                    parentId: $pullRequest->id,
                    body: $rawComment['body'] ?? '',
                    author: $rawComment['user']['login'] ?? 'unknown',
                    url: $rawComment['html_url'] ?? '',
                    createdAt: new \DateTimeImmutable($rawComment['created_at'] ?? 'now'),
                );

                $commentContext = ['parentTitle' => $pullRequest->title, 'url' => $comment->url];
                $commentChunks = $this->chunker->chunk($comment->body, $commentContext);
                $chunkCount += $this->pipeline->ingest($comment, SourceType::Comment, $commentChunks);
                ++$commentCount;
            }

            if ($prCount % 20 === 0) {
                $io->writeln(\sprintf('  … %d pull requests processed', $prCount));
            }
        }

        $this->pipeline->flush();
        $io->success(\sprintf('Ingested %d pull requests and %d comments, wrote %d chunks.', $prCount, $commentCount, $chunkCount));

        return Command::SUCCESS;
    }
}
