<?php

declare(strict_types=1);

namespace App\Ingestion\Chunking;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitorAbstract;
use Symfony\AI\Store\Document\TextDocument;

/**
 * Walks a PHP AST and extracts one chunk per "semantic unit": a method/function with its
 * docblock, or a whole class when it declares no methods (e.g. value objects, enums,
 * marker interfaces). This matches the internal MongoDB guidance that "one chunk = one
 * semantically meaningful unit" for source code, rather than a blind line-count split.
 * A unit that is still too large (a very long method) is size-limited by ChunkSplitter.
 */
final class PhpSymbolVisitor extends NodeVisitorAbstract
{
    /** @var list<string> */
    private array $lines;

    /** @var list<ClassLike> */
    private array $classStack = [];

    /** @var list<TextDocument> */
    private array $chunks = [];

    /** @param array<string, mixed> $context */
    public function __construct(
        string $source,
        private readonly ChunkSplitter $splitter,
        private readonly array $context,
    ) {
        $this->lines = explode("\n", $source);
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof ClassLike) {
            $this->classStack[] = $node;
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof ClassMethod && $node->stmts !== null) {
            array_push($this->chunks, ...$this->buildChunk($node, 'method', end($this->classStack) ?: null));
        } elseif ($node instanceof Function_) {
            array_push($this->chunks, ...$this->buildChunk($node, 'function', null));
        } elseif ($node instanceof ClassLike) {
            if ($this->hasConcreteMethod($node)) {
                // The class still carries useful "purpose" text in its docblock even
                // though its methods are chunked separately above; capture it as its
                // own small chunk instead of losing it.
                if ($node->getDocComment() !== null) {
                    array_push($this->chunks, ...$this->buildClassDocChunk($node));
                }
            } else {
                array_push($this->chunks, ...$this->buildChunk($node, 'class', null));
            }

            array_pop($this->classStack);
        }

        return null;
    }

    /** @return list<TextDocument> */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    private function hasConcreteMethod(ClassLike $node): bool
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->stmts !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return list<TextDocument> */
    private function buildChunk(Node $node, string $symbolType, ?ClassLike $enclosing): array
    {
        $startLine = $node->getStartLine();
        $endLine = $node->getEndLine();
        $body = implode("\n", \array_slice($this->lines, $startLine - 1, $endLine - $startLine + 1));

        $docComment = $node->getDocComment();
        $text = $docComment !== null ? $docComment->getText() . "\n" . $body : $body;

        $name = property_exists($node, 'name') && $node->name !== null ? $node->name->toString() : '(anonymous)';
        $symbolName = $enclosing !== null
            ? ($enclosing->name?->toString() ?? '(anonymous)') . '::' . $name
            : $name;

        $metadata = [
            ...$this->context,
            'symbolType' => $symbolType,
            'symbolName' => $symbolName,
            'startLine' => $startLine,
            'endLine' => $endLine,
            'enclosingClass' => $enclosing?->name?->toString(),
        ];

        return $this->splitter->split($text, $metadata);
    }

    /** @return list<TextDocument> */
    private function buildClassDocChunk(ClassLike $node): array
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $name = $node->name?->toString() ?? '(anonymous)';
        $signatureLine = trim($this->lines[$node->getStartLine() - 1] ?? '');
        $text = $docComment->getText() . "\n" . $signatureLine;

        $metadata = [
            ...$this->context,
            'symbolType' => 'class_doc',
            'symbolName' => $name,
            'startLine' => $docComment->getStartLine(),
            'endLine' => $node->getStartLine(),
        ];

        return $this->splitter->split($text, $metadata);
    }
}
