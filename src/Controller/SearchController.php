<?php

declare(strict_types=1);

namespace App\Controller;

use App\Document\SourceType;
use App\Search\LexicalSearchService;
use App\Search\VectorSearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class SearchController
{
    public function __construct(
        private readonly VectorSearchService $vectorSearch,
        private readonly LexicalSearchService $lexicalSearch,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $typeValue = (string) $request->query->get('type', '');
        $model = (string) $request->query->get('model', 'voyage-4-lite');
        $system = (string) $request->query->get('system', 'vector');
        $sourceType = SourceType::tryFrom($typeValue);

        $results = [];
        if ($query !== '') {
            $results = $system === 'lucene'
                ? $this->lexicalSearch->search($query, $sourceType)
                : $this->vectorSearch->search($query, $sourceType, $model);
        }

        return new Response($this->twig->render('search/index.html.twig', [
            'query' => $query,
            'sourceType' => $typeValue,
            'model' => $model,
            'system' => $system,
            'sourceTypes' => SourceType::cases(),
            'results' => $results,
        ]));
    }
}
