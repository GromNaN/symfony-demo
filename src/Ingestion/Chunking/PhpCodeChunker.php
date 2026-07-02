<?php

declare(strict_types=1);

namespace App\Ingestion\Chunking;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class PhpCodeChunker implements ChunkerInterface
{
    private readonly Parser $parser;

    public function __construct(private readonly ChunkSplitter $splitter)
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function chunk(string $content, array $context = []): array
    {
        try {
            $ast = $this->parser->parse($content);
        } catch (Error) {
            $ast = null;
        }

        if ($ast === null) {
            // Unparsable file (rare): fall back to a single whole-file chunk rather than failing ingestion.
            return $this->splitter->split($content, [...$context, 'symbolType' => 'file']);
        }

        $visitor = new PhpSymbolVisitor($content, $this->splitter, $context);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getChunks();
    }
}
