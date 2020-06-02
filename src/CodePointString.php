<?php

declare(strict_types=1);

namespace Rowbot\Punycode;

use Generator;
use IteratorAggregate;

use function count;
use function ord;
use function strlen;

/**
 * @implements \IteratorAggregate<int, int>
 */
class CodePointString implements IteratorAggregate
{
    /**
     * @var array<int, int>
     */
    protected $codePoints;

    public function __construct(string $input)
    {
        $this->codePoints = $this->utf8Decode($input);
    }

    /**
     * @return \Generator<int, int>
     */
    public function getIterator(): Generator
    {
        $length = count($this->codePoints);

        for ($i = 0; $i < $length; ++$i) {
            yield $i => $this->codePoints[$i];
        }
    }

    /**
     * Takes a UTF-8 encoded string and converts it into a series of integer code points. Any
     * invalid byte sequences will be replaced by a U+FFFD replacement code point.
     *
     * @see https://encoding.spec.whatwg.org/#utf-8-decoder
     *
     * @return array<int, int>
     */
    private function utf8Decode(string $input): array
    {
        $bytesSeen = 0;
        $bytesNeeded = 0;
        $lowerBoundary = 0x80;
        $upperBoundary = 0xBF;
        $codePoint = 0;
        $codePoints = [];
        $length = strlen($input);

        for ($i = 0; $i < $length; ++$i) {
            $byte = ord($input[$i]);

            if ($bytesNeeded === 0) {
                if ($byte >= 0x00 && $byte <= 0x7F) {
                    $codePoints[] = $byte;

                    continue;
                }

                if ($byte >= 0xC2 && $byte <= 0xDF) {
                    $bytesNeeded = 1;
                    $codePoint = $byte & 0x1F;
                } elseif ($byte >= 0xE0 && $byte <= 0xEF) {
                    if ($byte === 0xE0) {
                        $lowerBoundary = 0xA0;
                    } elseif ($byte === 0xED) {
                        $upperBoundary = 0x9F;
                    }

                    $bytesNeeded = 2;
                    $codePoint = $byte & 0xF;
                } elseif ($byte >= 0xF0 && $byte <= 0xF4) {
                    if ($byte === 0xF0) {
                        $lowerBoundary = 0x90;
                    } elseif ($byte === 0xF4) {
                        $upperBoundary = 0x8F;
                    }

                    $bytesNeeded = 3;
                    $codePoint = $byte & 0x7;
                } else {
                    $codePoints[] = 0xFFFD;
                }

                continue;
            }

            if ($byte < $lowerBoundary || $byte > $upperBoundary) {
                $codePoint = 0;
                $bytesNeeded = 0;
                $bytesSeen = 0;
                $lowerBoundary = 0x80;
                $upperBoundary = 0xBF;
                --$i;
                $codePoints[] = 0xFFFD;

                continue;
            }

            $lowerBoundary = 0x80;
            $upperBoundary = 0xBF;
            $codePoint = ($codePoint << 6) | ($byte & 0x3F);

            if (++$bytesSeen !== $bytesNeeded) {
                continue;
            }

            $codePoints[] = $codePoint;
            $codePoint = 0;
            $bytesNeeded = 0;
            $bytesSeen = 0;
        }

        // String unexpectedly ended, so append a U+FFFD code point.
        if ($bytesNeeded !== 0) {
            $codePoints[] = 0xFFFD;
        }

        return $codePoints;
    }
}
