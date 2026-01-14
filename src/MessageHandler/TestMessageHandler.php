<?php

namespace App\MessageHandler;

use App\Message\TestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TestMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(TestMessage $message): void
    {
        $this->logger->info('Processing TestMessage', [
            'content' => $message->getContent(),
            'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        // Simulate some processing time
        sleep(1);

        $this->logger->info('TestMessage processed successfully', [
            'content' => $message->getContent(),
        ]);
    }
}

