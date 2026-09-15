<?php

declare(strict_types=1);

namespace App\Support\Bulk;

use Illuminate\Validation\ValidationException;

// @phpstan-ignore trait.unused
trait WithBulkSelection
{
    public array $selectedIds = [];

    public function updatedSelectedIds(): void
    {
        $this->selectedIds = $this->normalizeBulkIds($this->selectedIds);
    }

    /**
     * Select or clear the IDs currently visible on the server-rendered page.
     * Cross-page selection is deliberately a separate queued-operation contract.
     *
     * @param  array<int, int|string>  $ids
     */
    public function toggleBulkPage(array $ids): void
    {
        $pageIds = $this->normalizeBulkIds($ids);
        $selected = $this->normalizeBulkIds($this->selectedIds);

        $this->selectedIds = count(array_diff($pageIds, $selected)) === 0
            ? array_values(array_diff($selected, $pageIds))
            : array_values(array_unique([...$selected, ...$pageIds]));
    }

    public function clearBulkSelection(): void
    {
        $this->selectedIds = [];
    }

    /**
     * @return array<int, int>
     */
    protected function requireBulkSelection(int $limit = 100): array
    {
        $ids = $this->normalizeBulkIds($this->selectedIds);

        if ($ids === []) {
            throw ValidationException::withMessages([
                'selectedIds' => __('Select at least one record.'),
            ]);
        }

        if (count($ids) > $limit) {
            throw ValidationException::withMessages([
                'selectedIds' => __('Bulk actions are limited to :limit records until queued cross-page processing is enabled.', ['limit' => $limit]),
            ]);
        }

        return $ids;
    }

    protected function forEachBulkSelected(callable $callback, int $limit = 100): int
    {
        $ids = $this->requireBulkSelection($limit);

        foreach ($ids as $id) {
            $callback($id);
        }

        return count($ids);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function normalizeBulkIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }
}
