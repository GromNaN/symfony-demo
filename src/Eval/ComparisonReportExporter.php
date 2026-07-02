<?php

declare(strict_types=1);

namespace App\Eval;

use App\Search\SearchResult;

final class ComparisonReportExporter
{
    /** @param list<ComparisonRow> $rows */
    public static function toMarkdown(array $rows): string
    {
        $lines = [
            '| Query | Kind | Vector Search Top-1 | Lucene Top-1 | GitHub Issues Top-1 | GitHub Code Top-1 | Vector ms | Lucene ms | GitHub ms | Pertinent (VS)? | Pertinent (Lucene)? | Pertinent (GH)? |',
            '|---|---|---|---|---|---|---|---|---|---|---|---|',
        ];

        foreach ($rows as $row) {
            $lines[] = \sprintf(
                '| %s | %s | %s | %s | %s | %s | %.0f | %.0f | %.0f | | | |',
                self::escape($row->query),
                $row->kind,
                self::searchResultCell($row->vectorTop),
                self::searchResultCell($row->lexicalTop),
                self::githubCell($row->githubIssueTop),
                self::githubCell($row->githubCodeTop),
                $row->vectorLatencyMs,
                $row->lexicalLatencyMs,
                $row->githubLatencyMs,
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param list<ComparisonRow> $rows */
    public static function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['query', 'kind', 'vector_top1', 'vector_top1_url', 'lucene_top1', 'lucene_top1_url', 'github_issues_top1', 'github_issues_top1_url', 'github_code_top1', 'github_code_top1_url', 'vector_ms', 'lucene_ms', 'github_ms', 'pertinent_vs', 'pertinent_lucene', 'pertinent_gh']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->query,
                $row->kind,
                self::searchResultLabel($row->vectorTop),
                $row->vectorTop?->metadata['url'] ?? '',
                self::searchResultLabel($row->lexicalTop),
                $row->lexicalTop?->metadata['url'] ?? '',
                $row->githubIssueTop['title'] ?? '',
                $row->githubIssueTop['url'] ?? '',
                $row->githubCodeTop['title'] ?? '',
                $row->githubCodeTop['url'] ?? '',
                $row->vectorLatencyMs,
                $row->lexicalLatencyMs,
                $row->githubLatencyMs,
                '',
                '',
                '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private static function searchResultLabel(?SearchResult $result): string
    {
        if ($result === null) {
            return '';
        }

        return (string) ($result->metadata['title'] ?? $result->metadata['symbolName'] ?? $result->parentId);
    }

    private static function searchResultCell(?SearchResult $result): string
    {
        if ($result === null) {
            return '(no result)';
        }

        return \sprintf('[%s](%s)', self::escape(self::searchResultLabel($result)), $result->metadata['url'] ?? '#');
    }

    /** @param array{title: string, url: string, snippet: ?string}|null $result */
    private static function githubCell(?array $result): string
    {
        if ($result === null) {
            return '(no result)';
        }

        return \sprintf('[%s](%s)', self::escape($result['title']), $result['url']);
    }

    private static function escape(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }
}
