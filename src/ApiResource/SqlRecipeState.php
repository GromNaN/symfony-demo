<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AutoMapper\AutoMapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;

/**
 * @implements ProviderInterface<Recipe>
 */
readonly class SqlRecipeState implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private AutoMapper $autoMapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof Get) {
            assert(isset($uriVariables['id']));

            $results = $this->getStatement((int) $uriVariables['id'])->executeQuery()->fetchAllAssociative();

            $resourceClass = $operation->getClass();
            $results = $this->autoMapper->mapCollection($results, $resourceClass);

            // $results = array_map($this->hydrateRecipe(...), $results);

            return $results[0] ?? null;
        }
    }

    private function getStatement(int $id): Statement
    {
        $stmt = $this->connection->prepare(<<<'SQL'
            select
                id,
                title,
                description,
                preparation_time,
                cooking_time,
                author_name,
                (
                    select json_group_array(json_object('quantity', recipe_ingredient.quantity, 'unit', recipe_ingredient.unit, 'name', ingredient.name))
                    from recipe_ingredient
                    inner join ingredient on recipe_ingredient.ingredient_id = ingredient.id
                    where recipe_ingredient.recipe_id = recipe.id
                    order by ingredient.name
                ) as ingredients,
                (
                    select json_group_array(step.description)
                    from step
                    where step.recipe_id = recipe.id
                    order by step.step_number
                ) as steps,
                (
                    select json_object('average_rating', popularity.average_rating, 'number_of_votes', popularity.number_of_votes)
                    from popularity
                    where popularity.recipe_id = recipe.id
                ) as popularity
            from recipe
            where id = :id;
        SQL);
        $stmt->bindValue('id', $id);

        return $stmt;
    }

    public static function decode(string $json): array
    {
        return json_decode($json, true);
    }

    public function hydrateRecipe(array $data): Recipe
    {
        $recipe = new Recipe();
        $recipe->id = $data['id'];
        $recipe->title = $data['title'];
        $recipe->description = $data['description'];
        $recipe->preparationTime = $data['preparation_time'];
        $recipe->cookingTime = $data['cooking_time'];
        $recipe->authorName = $data['author_name'];
        $recipe->ingredients = json_decode($data['ingredients'], true);
        $recipe->steps = json_decode($data['steps'], true);
        $recipe->popularity = json_decode($data['popularity'], true);

        return $recipe;
    }
}
