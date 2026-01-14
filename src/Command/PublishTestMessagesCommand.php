<?php

namespace App\Command;

use App\Message\TestMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:publish-test-messages',
    description: 'Publish test messages to MongoDB Messenger transport to validate Atlas connectivity',
)]
class PublishTestMessagesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('count', InputArgument::OPTIONAL, 'Number of messages to publish', 5)
            ->addOption('delay', 'd', InputOption::VALUE_OPTIONAL, 'Delay between messages in milliseconds', 100)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getArgument('count');
        $delay = (int) $input->getOption('delay');

        $io->title('Publishing Test Messages to MongoDB Atlas');
        $io->info(sprintf('Publishing %d messages with %dms delay...', $count, $delay));

        $progressBar = $io->createProgressBar($count);
        $progressBar->start();

        for ($i = 1; $i <= $count; $i++) {
            $message = new TestMessage(
                content: sprintf('Test message #%d - Generated at %s', $i, date('Y-m-d H:i:s')),
            );

            $this->messageBus->dispatch($message);

            $progressBar->advance();

            if ($delay > 0 && $i < $count) {
                usleep($delay * 1000); // Convert milliseconds to microseconds
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf('Successfully published %d messages to MongoDB transport!', $count));
        $io->note('To consume the messages, run: php bin/console messenger:consume async -vv');

        return Command::SUCCESS;
    }
}

