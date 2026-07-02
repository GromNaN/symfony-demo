<?php

declare(strict_types=1);

namespace App\Ingestion\Chunking;

/**
 * Chunks reStructuredText documentation pages by section first — a title line followed by
 * an underline of repeated punctuation (=, -, ~, ^, "...) is a natural semantic boundary,
 * stronger than a blind character window — then delegates the actual size-limiting of each
 * section to symfony/ai-store's TextSplitTransformer (see ChunkSplitter).
 */
final class RstChunker implements ChunkerInterface
{
    private const UNDERLINE_CHARS = '=\-~^"\'#*+.:_`';

    public function __construct(private readonly ChunkSplitter $splitter)
    {
    }

    public function chunk(string $content, array $context = []): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $chunks = [];
        foreach ($this->splitIntoSections($content) as $section) {
            $sectionContext = $section['title'] !== null
                ? [...$context, 'heading' => $section['title']]
                : $context;

            $chunks = [...$chunks, ...$this->splitter->split($section['body'], $sectionContext)];
        }

        return $chunks;
    }

    /** @return list<array{title: string|null, body: string}> */
    private function splitIntoSections(string $content): array
    {
        $lines = explode("\n", $content);
        $titleLineIndexes = [];

        for ($i = 0; $i < \count($lines) - 1; ++$i) {
            $title = rtrim($lines[$i]);
            $underline = trim($lines[$i + 1]);

            if ($title === '' || $underline === '') {
                continue;
            }

            if (preg_match('/^[' . self::UNDERLINE_CHARS . ']{3,}$/', $underline) === 1 && \strlen($underline) >= \strlen($title)) {
                $titleLineIndexes[] = $i;
            }
        }

        if ($titleLineIndexes === []) {
            return [['title' => null, 'body' => $content]];
        }

        $sections = [];

        if ($titleLineIndexes[0] > 0) {
            $preamble = trim(implode("\n", \array_slice($lines, 0, $titleLineIndexes[0])));
            if ($preamble !== '') {
                $sections[] = ['title' => null, 'body' => $preamble];
            }
        }

        foreach ($titleLineIndexes as $index => $titleLine) {
            $title = trim($lines[$titleLine]);
            $bodyStart = $titleLine + 2;
            $bodyEnd = $titleLineIndexes[$index + 1] ?? \count($lines);
            $body = trim(implode("\n", \array_slice($lines, $bodyStart, $bodyEnd - $bodyStart)));

            if ($body !== '') {
                $sections[] = ['title' => $title, 'body' => $body];
            }
        }

        return $sections;
    }
}
