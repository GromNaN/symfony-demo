<?php

declare(strict_types=1);

namespace App\Controller;

use App\Document\SourceType;
use App\Search\VectorSearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class SearchController
{
    public function __construct(
        private readonly VectorSearchService $searchService,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $typeValue = (string) $request->query->get('type', '');
        $model = (string) $request->query->get('model', 'voyage-4-lite');
        $sourceType = SourceType::tryFrom($typeValue);

        $results = $query !== '' ? $this->searchService->search($query, $sourceType, $model) : [];

        return new Response($this->twig->render('search/index.html.twig', [
            'query' => $query,
            'sourceType' => $typeValue,
            'model' => $model,
            'sourceTypes' => SourceType::cases(),
            'results' => $results,
        ]));
    }
}
