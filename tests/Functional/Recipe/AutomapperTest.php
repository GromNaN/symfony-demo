<?php

namespace Functional\Recipe;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class AutomapperTest extends ApiTestCase
{
    private const BASE_URL = '/api/automapper_recipes';

    public function testGetList()
    {
        $response = static::createClient()->request('GET', static::BASE_URL);
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'totalItems' => 31,
        ]);
    }

    public function testPost()
    {
        $response = static::createClient()->request('POST', static::BASE_URL, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => 'Lasagna Bolognese',
                'description' => 'A traditional Italian dish, tasty and comforting.',
                'preparationTime' => 30,
                'cookingTime' => 45,
                'ingredients' => [
                    ['quantity' => 12.0, 'unit' => 'sheets', 'ingredient' => ''],
                    ['quantity' => 500.0, 'unit' => 'g', 'ingredient' => ''],
                    ['quantity' => 500.0, 'unit' => 'ml', 'ingredient' => ''],
                    ['quantity' => 100.0, 'unit' => 'g', 'ingredient' => ''],
                    ['quantity' => 2.0, 'unit' => 'cloves', 'ingredient' => ''],
                    ['quantity' => 100.0, 'unit' => 'g', 'ingredient' => ''],
                    ['quantity' => 50.0, 'unit' => 'g', 'ingredient' => ''],
                    ['quantity' => 2.0, 'unit' => 'tbsp', 'ingredient' => ''],
                    ['quantity' => 400.0, 'unit' => 'ml', 'ingredient' => ''],
                    ['quantity' => 100.0, 'unit' => 'g', 'ingredient' => ''],
                    ['quantity' => 150.0, 'unit' => 'g', 'ingredient' => ''],
                    ['quantity' => 1.0, 'unit' => 'tsp', 'ingredient' => ''],
                    ['quantity' => 1.0, 'unit' => 'tsp', 'ingredient' => ''],
                    ['quantity' => 1.0, 'unit' => 'tsp', 'ingredient' => ''],
                    ['quantity' => 100.0, 'unit' => 'ml', 'ingredient' => ''],
                ],
                'steps' => [
                    'Chop onions, garlic, carrots, and celery finely.',
                    'Heat olive oil in a pan, sauté vegetables until soft.',
                    'Add ground beef and cook until browned.',
                    'Pour in red wine, let it reduce for 5 minutes.',
                    'Add tomato sauce, salt, pepper, and oregano. Simmer for 20 minutes.',
                    'Preheat oven to 180°C (350°F).',
                    'In a baking dish, layer lasagna sheets, meat sauce, and béchamel sauce.',
                    'Repeat layers and top with mozzarella and parmesan.',
                    'Bake for 45 minutes until golden brown.',
                    'Let rest for 10 minutes before serving.',
                ],
                'popularity' => [
                    'average_rating' => 4.8,
                    'number_of_votes' => 320,
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(201);
    }
}
