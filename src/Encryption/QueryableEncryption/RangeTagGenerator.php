<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;

/**
 * RangeTagGenerator computes searchable range tags for QE values.
 * Supports both point (equality) and range (interval) queries.
 * Uses DataEncryptionKeyStore to derive the tag HMAC key.
 */
final class RangeTagGenerator
{
    public readonly ?int $precision;
    private readonly float $min;
    private readonly float $max;
    private readonly int $fieldId;
    private readonly string $tagKey;
    private readonly int $scale;
    private readonly int $minScaled;
    private readonly int $maxScaled;

    private const LEVEL_COUNT = 3;
    private const MAX_RANGE_TAGS = 10_000;

    public function __construct(
        ?int $precision,
        float $min,
        float $max,
        int $fieldId,
        DataEncryptionKeyStore $dekStore,
        string $dekId = 'tag-key',
    ) {
        if ($min >= $max) {
            throw new \InvalidArgumentException('min must be < max');
        }

        $this->precision = $precision;
        $this->min = $min;
        $this->max = $max;
        $this->fieldId = $fieldId;

        // Derive tag key from DEK
        $dek = $dekStore->getKey($dekId);
        $this->tagKey = $dek->getPlainDek();

        $this->scale = $this->precision !== null ? 10 ** $this->precision : 1;
        $this->minScaled = (int) \floor($this->min * $this->scale);
        $this->maxScaled = (int) \ceil($this->max * $this->scale);
    }

    /**
     * Tags for a single value (equality).
     *
     * @return string[]
     */
    public function generateValueTags(float $value): array
    {
        $v = $this->clampScaled($this->toScaled($value));

        $tokens = [];

        for ($level = 0; $level < self::LEVEL_COUNT; $level++) {
            $segmentSize = $this->segmentSizeForLevel($level);
            $bucketIndex = intdiv($v - $this->minScaled, $segmentSize);

            $tokens[] = $this->computeToken($level, $bucketIndex);
        }

        return $tokens;
    }

    /**
     * Tags for a range query [lower, upper].
     * lower = null => min, upper = null => max.
     *
     * @return string[]
     */
    public function generateRangeQueryTags(?float $lower, ?float $upper): array
    {
        $lower = $lower ?? $this->min;
        $upper = $upper ?? $this->max;

        if ($lower > $upper) {
            return [];
        }

        $ls = $this->clampScaled($this->toScaled($lower));
        $us = $this->clampScaled($this->toScaled($upper));

        if ($ls > $us) {
            return [];
        }

        $tokens = [];

        // Coarsest level for the range
        $level = self::LEVEL_COUNT - 1;
        $segmentSize = $this->segmentSizeForLevel($level);

        $firstBucket = intdiv($ls - $this->minScaled, $segmentSize);
        $lastBucket = intdiv($us - $this->minScaled, $segmentSize);

        $bucketCount = $lastBucket - $firstBucket + 1;
        if ($bucketCount > self::MAX_RANGE_TAGS) {
            throw new \RuntimeException(sprintf(
                'Range too large: would generate %d tags (max %d). ' .
                'Narrow the range or use coarser configuration (sparsity/min/max).',
                $bucketCount,
                self::MAX_RANGE_TAGS
            ));
        }

        for ($bucket = $firstBucket; $bucket <= $lastBucket; $bucket++) {
            $tokens[] = $this->computeToken($level, $bucket);
        }

        return array_values(array_unique($tokens, SORT_STRING));
    }

    private function toScaled(float $v): int
    {
        return (int) \round($v * $this->scale);
    }

    private function clampScaled(int $v): int
    {
        if ($v < $this->minScaled) {
            return $this->minScaled;
        }
        if ($v > $this->maxScaled) {
            return $this->maxScaled;
        }

        return $v;
    }

    private function segmentSizeForLevel(int $level): int
    {
        $size = 1 << $level;

        return max(1, $size);
    }

    private function computeToken(int $level, int $bucketIndex): string
    {
        $data = $this->fieldId . '|' . $level . '|' . $bucketIndex;

        return \hash_hmac('sha256', $data, $this->tagKey, true);
    }
}

