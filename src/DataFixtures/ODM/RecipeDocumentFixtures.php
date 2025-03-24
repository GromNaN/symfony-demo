<?php

namespace App\DataFixtures\ODM;

use App\Document\Author;
use App\Document\Ingredient;
use App\Document\Popularity;
use App\Document\Recipe;
use App\Document\Step;
use App\Document\User;
use Doctrine\Bundle\MongoDBBundle\Fixture\Fixture;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\ObjectManager;

class RecipeDocumentFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        assert($manager instanceof DocumentManager);

        $this->loadUsers($manager);
        $this->generateLasagna($manager);

        // List of classic French recipes
        $recipeNames = [
            'Boeuf Bourguignon', 'Quiche Lorraine', 'Ratatouille', 'Coq au Vin', 'Tartiflette',
            'Cassoulet', 'Croque Monsieur', 'Bouillabaisse', 'Choucroute Garnie', 'Confit de Canard',
            'Salade Niçoise', 'Pot-au-feu', 'Sole Meunière', 'Blanquette de Veau', 'Clafoutis',
            'Crêpes Suzette', 'Pain Perdu', 'Tarte Tatin', 'Moules Marinières', 'Gratin Dauphinois',
            'Pissaladière', 'Andouillette', 'Oeufs Cocotte', 'Poule au Pot', 'Quenelles de Brochet',
            'Baba au Rhum', 'Soufflé au Fromage', 'Gougères', 'Aligot', 'Navarin d’Agneau',
        ];

        // Common ingredients
        $ingredientNames = [
            'Beef', 'Chicken', 'Pork', 'Potatoes', 'Carrots', 'Onions', 'Garlic', 'Cheese', 'Butter',
            'Flour', 'Milk', 'Cream', 'Eggs', 'Tomatoes', 'White Wine', 'Red Wine', 'Bread', 'Sugar',
            'Herbs', 'Salt', 'Pepper',
        ];

        // Create recipes
        foreach ($recipeNames as $recipeName) {
            $recipe = new Recipe();
            $recipe->title = $recipeName;
            $recipe->description = 'A delicious French dish: '.$recipeName;
            $recipe->preparationTime = rand(10, 60);
            $recipe->cookingTime = rand(20, 180);

            $author = new Author();
            $author->name = 'Camille';
            $author->user = $this->getReference('camille@cuisine.dev', User::class);
            $recipe->author = $author;

            // Assign random ingredients
            $usedIngredients = array_rand($ingredientNames, rand(3, 6));
            foreach ((array) $usedIngredients as $ingredientName) {
                $recipeIngredient = new Ingredient();
                $recipeIngredient->name = $ingredientName;
                $recipeIngredient->quantity = rand(50, 500);
                $recipeIngredient->unit = ['g', 'ml', 'tbsp', 'cup'][rand(0, 3)];
                $recipe->ingredients->add($recipeIngredient);
            }

            // Create steps
            for ($i = 1; $i <= 3; ++$i) {
                $step = new Step();
                $step->stepNumber = $i;
                $step->description = match ($i) {
                    1 => 'Prepare the ingredients.',
                    2 => 'Cook according to instructions.',
                    3 => 'Serve and enjoy!',
                };
                $recipe->steps->add($step);
            }

            // Add popularity
            $popularity = new Popularity();
            $popularity->averageRating = round(rand(30, 50) / 10, 1);
            $popularity->numberOfVotes = rand(50, 500);
            $recipe->popularity = $popularity;

            $manager->persist($recipe);
        }

        // Save everything
        $manager->flush();
    }

    public function loadUsers(ObjectManager $manager): void
    {
        $emails = ['nicolas@cuisine.dev', 'fabien@cuisine.dev', 'eloise@cuisine.dev', 'camille@cuisine.dev', 'chef@cuisine.dev'];

        foreach ($emails as $email) {
            $user = new User();
            $user->email = $email;
            $manager->persist($user);

            $this->addReference($email, $user);
        }

        $manager->flush();
    }

    private function generateLasagna(ObjectManager $manager): void
    {
        $recipe = new Recipe();
        $recipe->id = '67da8b6c606149379d0a7ba7';
        $recipe->title = 'Lasagna Bolognese';
        $recipe->description = 'A traditional Italian dish, tasty and comforting.';
        $recipe->preparationTime = 30;
        $recipe->cookingTime = 45;

        $author = new Author();
        $author->name = 'Camille';
        $author->user = $this->getReference('camille@cuisine.dev', User::class);
        $recipe->author = $author;

        $ingredientList = [
            ['Lasagna Sheets', 'sheets', 12],
            ['Ground Beef', 'g', 500],
            ['Tomato Sauce', 'ml', 500],
            ['Onion', 'g', 100],
            ['Garlic', 'cloves', 2],
            ['Carrot', 'g', 100],
            ['Celery', 'g', 50],
            ['Olive Oil', 'tbsp', 2],
            ['Bechamel Sauce', 'ml', 400],
            ['Parmesan Cheese', 'g', 100],
            ['Mozzarella', 'g', 150],
            ['Salt', 'tsp', 1],
            ['Black Pepper', 'tsp', 1],
            ['Oregano', 'tsp', 1],
            ['Red Wine', 'ml', 100],
        ];

        foreach ($ingredientList as [$name, $unit, $quantity]) {
            $recipeIngredient = new Ingredient();
            $recipeIngredient->name = $name;
            $recipeIngredient->quantity = $quantity;
            $recipeIngredient->unit = $unit;
            $recipe->ingredients->add($recipeIngredient);
        }

        $steps = [
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
        ];

        foreach ($steps as $index => $desc) {
            $step = new Step();
            $step->stepNumber = $index + 1;
            $step->description = $desc;
            $recipe->steps->add($step);
        }

        $popularity = new Popularity();
        $popularity->averageRating = 4.8;
        $popularity->numberOfVotes = 320;
        $recipe->popularity = $popularity;

        $manager->persist($recipe);
        $manager->flush();
    }
}
