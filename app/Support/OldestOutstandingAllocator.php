<?php

declare(strict_types=1);

namespace App\Support;

final class OldestOutstandingAllocator
{
    /**
     * @param  iterable<array{id:int, outstanding:numeric-string}>  $documents
     * @return array<int, numeric-string>
     */
    public function allocate(iterable $documents, string $amount): array
    {
        $remaining = bcadd($amount, '0', 4);
        $allocations = [];

        foreach ($documents as $document) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $outstanding = bcadd($document['outstanding'], '0', 4);
            if (bccomp($outstanding, '0', 4) <= 0) {
                continue;
            }

            $allocated = bccomp($remaining, $outstanding, 4) < 0 ? $remaining : $outstanding;
            $allocations[$document['id']] = $allocated;
            $remaining = bcsub($remaining, $allocated, 4);
        }

        return $allocations;
    }
}
