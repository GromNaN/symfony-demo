<?php

namespace App\Storage;

class CsvStore
{
    private array $headers;
    public function __construct(
        private string $path,
    )
    {
        $file = fopen($this->path, 'rb');
        $headers = fgetcsv($file, null, ';', escape: '');
        fclose($file);

        $headers[0] = 'id'; // Remove unwanted char from the first header
        $this->headers = array_flip($headers);
    }

    public function find(int $id): ?array
    {
        $file = fopen($this->path, 'rb');
        fgets($file);

        while(!feof($file)) {
            $data = fgetcsv($file, null, ';', escape: '');
            if ($data === false) {
                continue;
            }

            if ($data[0] == $id) {
                return $this->hydrate($data);
            }
        }

        return null;
    }

    /** @return iterable<array> */
    public function all(int $limit = 20, int $offset = 0): \Generator
    {
        // Ignore the first line, it contains the headers
        $offset++;

        $file = fopen($this->path, 'rb');

        // Moves the file pointer after $offet lines
        while($offset-- > 0 && !feof($file)) {
            fgets($file);
        }

        while($limit-- > 0 && !feof($file)) {
            yield $this->hydrate(fgetcsv($file, null, ';', escape: ''));
        }
    }

    private function hydrate(array $data): array
    {
        $data = array_combine(array_keys($this->headers), $data);

        foreach ($data as $key => &$value) {
            if (str_starts_with($value, '[') && str_ends_with($value, ']') || str_starts_with($value, '{') && str_ends_with($value, '}')) {
                $value = json_decode($value, true);
            }
        }

        return $data;
    }
}