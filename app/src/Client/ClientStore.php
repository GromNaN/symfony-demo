<?php

namespace App\Client;

/**
 * Reference data for the demo: a handful of in-memory clients. A real app
 * would load the record from Doctrine, an API or any other source.
 */
final class ClientStore
{
    /** @var Client[] */
    private array $clients = [];

    public function __construct()
    {
        foreach (range(1, 6) as $i) {
            $this->clients[] = new Client(
                $i,
                sprintf('Client %d', $i),
                sprintf('client%03d@example.org', $i),
                sprintf('+1 555 010 %04d', $i),
            );
        }
    }

    /** @return Client[] */
    public function all(): array
    {
        return $this->clients;
    }

    public function find(int $id): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->id === $id) {
                return $client;
            }
        }

        return null;
    }
}
