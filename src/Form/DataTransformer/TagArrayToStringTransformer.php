<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\DataTransformer;

use App\Document\Tag;
use Symfony\Component\Form\DataTransformerInterface;

use function Symfony\Component\String\u;

/**
 * This data transformer is used to translate the array of tags into a comma separated format
 * that can be displayed and managed by Bootstrap-tagsinput js plugin (and back on submit).
 *
 * See https://symfony.com/doc/current/form/data_transformers.html
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Jonathan Boyer <contact@grafikart.fr>
 *
 * @template-implements DataTransformerInterface<Tag[], string>
 */
final class TagArrayToStringTransformer implements DataTransformerInterface
{
    public function transform($value): string
    {
        // The value received is an array of Tag objects generated with
        // Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer::transform()
        // The value returned is a string that concatenates the string representation of those objects

        return implode(',', $value);
    }

    /**
     * @phpstan-param string|null $value
     *
     * @return Tag[]
     */
    public function reverseTransform($value): array
    {
        if (null === $value || u($value)->isEmpty()) {
            return [];
        }

        $names = array_filter(array_unique(u($value)->split(',')));

        $tags = [];
        foreach ($names as $name) {
            $tags[] = new Tag(u($name)->trim());
        }

        return $tags;
    }

    /**
     * @param string[] $strings
     *
     * @return string[]
     */
    private function trim(array $strings): array
    {
        $result = [];

        foreach ($strings as $string) {
            $result[] = trim($string);
        }

        return $result;
    }
}
