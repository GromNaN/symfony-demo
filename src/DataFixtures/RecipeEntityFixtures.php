<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\RecipeIngredient;
use App\Entity\Step;
use App\Entity\Popularity;

class RecipeEntityFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        assert($manager instanceof EntityManager);

        // List of classic French recipes
        $recipeNames = [
            'Boeuf Bourguignon', 'Quiche Lorraine', 'Ratatouille', 'Coq au Vin', 'Tartiflette',
            'Cassoulet', 'Croque Monsieur', 'Bouillabaisse', 'Choucroute Garnie', 'Confit de Canard',
            'Salade Niçoise', 'Pot-au-feu', 'Sole Meunière', 'Blanquette de Veau', 'Clafoutis',
            'Crêpes Suzette', 'Pain Perdu', 'Tarte Tatin', 'Moules Marinières', 'Gratin Dauphinois',
            'Pissaladière', 'Andouillette', 'Oeufs Cocotte', 'Poule au Pot', 'Quenelles de Brochet',
            'Baba au Rhum', 'Soufflé au Fromage', 'Gougères', 'Aligot', 'Navarin d’Agneau'
        ];

        // Common ingredients
        $ingredientNames = [
            'Beef', 'Chicken', 'Pork', 'Potatoes', 'Carrots', 'Onions', 'Garlic', 'Cheese', 'Butter',
            'Flour', 'Milk', 'Cream', 'Eggs', 'Tomatoes', 'White Wine', 'Red Wine', 'Bread', 'Sugar',
            'Herbs', 'Salt', 'Pepper'
        ];

        // Create ingredients
        $ingredients = [];
        foreach ($ingredientNames as $name) {
            $ingredient = new Ingredient();
            $ingredient->name = $name;
            $manager->persist($ingredient);
            $ingredients[] = $ingredient;
        }

        // Create recipes
        foreach ($recipeNames as $recipeName) {
            $recipe = new Recipe();
            $recipe->title = $recipeName;
            $recipe->description = "A delicious French dish: " . $recipeName;
            $recipe->preparationTime = rand(10, 60);
            $recipe->cookingTime = rand(20, 180);
            $recipe->author_name = 'Camille';
            $recipe->author_user = $this->getReference('camille@cuisine.dev', User::class);

            //$author = new Author();
            //$author->name = 'Camille';
            //$author->user = $this->getReference('camille@cuisine.dev', User::class);
            //$recipe->author = $author;

            // Assign random ingredients
            $usedIngredients = array_rand($ingredients, rand(3, 6));
            foreach ((array) $usedIngredients as $key) {
                $recipeIngredient = new RecipeIngredient();
                $recipeIngredient->recipe = $recipe;
                $recipeIngredient->ingredient = $ingredients[$key];
                $recipeIngredient->quantity = rand(50, 500);
                $recipeIngredient->unit = ['g', 'ml', 'tbsp', 'cup'][rand(0, 3)];
                $manager->persist($recipeIngredient);
            }

            // Create steps
            for ($i = 1; $i <= 3; $i++) {
                $step = new Step();
                $step->recipe = $recipe;
                $step->stepNumber = $i;
                $step->description = match ($i) {
                    1 => 'Prepare the ingredients.',
                    2 => 'Cook according to instructions.',
                    3 => 'Serve and enjoy!'
                };
                $manager->persist($step);
            }

            // Add popularity
            $popularity = new Popularity();
            $popularity->recipe = $recipe;
            $popularity->averageRating = round(rand(30, 50) / 10, 1);
            $popularity->numberOfVotes = rand(50, 500);
            $manager->persist($popularity);

            $manager->persist($recipe);
        }

        // Save everything
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
