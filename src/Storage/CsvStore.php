<?php

namespace App\Storage;

use App\ApiResource\Address;
use App\ApiResource\FuelPrice;
use App\ApiResource\Station;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CsvStore
{
    private array $headers;
    public function __construct(
        #[Autowire('%kernel.project_dir%/%env(CSV_FILE)%')]
        private string $path,
    )
    {
        $file = fopen($this->path, 'rb');
        $headers = fgetcsv($file, null, ';', escape: '');
        fclose($file);

        $headers[0] = 'id'; // Remove unwanted char from the first header
        $this->headers = array_flip($headers);
    }

    public function find(int $id): ?object
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
            $data = fgetcsv($file, null, ';', escape: '');
            if ($data === false) {
                continue;
            }

            yield $this->hydrate($data);
        }
    }

    private function hydrate(array $data): object
    {
        $data = array_combine(array_keys($this->headers), $data);

        foreach ($data as $key => &$value) {
            if (str_starts_with($value, '[') && str_ends_with($value, ']') || str_starts_with($value, '{') && str_ends_with($value, '}')) {
                $value = json_decode($value, true);
            }
        }


        $object = new Station();
        $object->id = $data['id'];
        $object->services = $data['services'] ? (array) $data['services']['service'] : [];
        $object->address = new Address();
        $object->address->address = $data['Adresse'];
        $object->address->city = $data['Ville'];
        $object->address->postCode = $data['Code postal'];;
        $object->prices = [];

        foreach ($data['prix'] as $values) {
            $price = new FuelPrice();
            $price->id = $values['@id'];
            $price->price = $values['@valeur'];
            $price->name = $values['@nom'];
            $price->updatedAt = new \DateTimeImmutable($values['@maj']);
            $object->prices[] = $price;
        }

        return $object;
    }
}