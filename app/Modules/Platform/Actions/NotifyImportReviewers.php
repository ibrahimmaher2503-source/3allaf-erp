<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Notifications\ImportReadyForReviewNotification;

final class NotifyImportReviewers
{
    public function execute(int $requesterId, string $reviewPermission, string $importType, string $filename, string $routeName, int $batchId): void
    {
        $url = route($routeName, ['batch' => $batchId]);

        User::query()
            ->where('status', 'active')
            ->whereKeyNot($requesterId)
            ->get()
            ->filter(fn (User $user): bool => $user->can($reviewPermission))
            ->each(fn (User $user) => $user->notify(new ImportReadyForReviewNotification($importType, $filename, $url)));
    }
}
