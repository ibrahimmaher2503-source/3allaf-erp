<?php

declare(strict_types=1);

namespace App\Modules\Assets\Queries;

use App\Models\User;
use App\Modules\Assets\Models\AssetCheckout;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\RentalAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class AssetDashboard
{
    /** @return array<string, mixed> */
    public function for(User $actor, ?int $storeId = null): array
    {
        $assets = $this->assets($actor, $storeId);
        $reservations = $this->reservations($actor, $storeId);
        $checkouts = $this->checkouts($actor, $storeId);
        $returns = $this->returns($actor, $storeId);
        $events = $this->events($actor, $storeId);
        $activeAssets = (clone $assets)->whereNot('rental_assets.status', 'retired')->count();
        $checkedOut = (clone $assets)->where('rental_assets.status', 'checked_out')->count();

        $statusCounts = (clone $assets)
            ->selectRaw('rental_assets.status, COUNT(*) as aggregate')
            ->groupBy('rental_assets.status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        $outstandingCheckouts = (clone $checkouts)
            ->whereNotIn('asset_checkouts.id', AssetReturn::query()->select('asset_returns.checkout_id'));
        $overdueReturns = (clone $outstandingCheckouts)
            ->whereHas('reservation', fn (Builder $query) => $query->where('asset_reservations.ends_at', '<', now()));

        return [
            'active_assets' => $activeAssets,
            'status_counts' => $statusCounts,
            'available_assets' => $statusCounts['available'] ?? 0,
            'reserved_assets' => $statusCounts['reserved'] ?? 0,
            'checked_out_assets' => $checkedOut,
            'inspection_queue' => $statusCounts['under_inspection'] ?? 0,
            'maintenance_assets' => $statusCounts['under_maintenance'] ?? 0,
            'unavailable_assets' => array_sum(array_intersect_key($statusCounts, array_flip(['damaged', 'under_maintenance', 'lost', 'retired']))),
            'upcoming_reservations' => (clone $reservations)->active()
                ->whereBetween('asset_reservations.starts_at', [now(), now()->addDays(14)])->count(),
            'upcoming_checkouts' => (clone $reservations)->where('asset_reservations.status', 'reserved')
                ->whereBetween('asset_reservations.starts_at', [now(), now()->addDays(7)])->count(),
            'outstanding_returns' => (clone $outstandingCheckouts)->count(),
            'overdue_returns' => (clone $overdueReturns)->count(),
            'pending_events' => (clone $events)->where('asset_events.status', 'submitted')->count(),
            'utilization_percent' => $activeAssets > 0 ? round(($checkedOut / $activeAssets) * 100, 1) : 0.0,
            'condition_exceptions' => (clone $assets)->whereIn('rental_assets.condition', ['fair', 'poor'])->count(),
            'upcoming_rows' => (clone $reservations)->active()->with(['asset:id,code,name_ar,name_en,status', 'store:id,code,name_ar,name_en'])
                ->where('asset_reservations.ends_at', '>=', now())->orderBy('asset_reservations.starts_at')->limit(6)->get(),
            'overdue_rows' => (clone $overdueReturns)->with(['asset:id,code,name_ar,name_en', 'reservation:id,asset_id,source_reference,ends_at'])
                ->orderBy('asset_checkouts.checked_out_at')->limit(6)->get(),
            'recent_returns' => (clone $returns)->with(['asset:id,code,name_ar,name_en,status'])
                ->latest('asset_returns.returned_at')->limit(6)->get(),
            'recent_events' => (clone $events)->with(['asset:id,code,name_ar,name_en', 'responsibleUser:id,name'])
                ->latest('asset_events.id')->limit(6)->get(),
            'category_distribution' => (clone $assets)->selectRaw("COALESCE(NULLIF(rental_assets.category, ''), ?) as category_label, COUNT(*) as aggregate", [__('Uncategorized')])
                ->groupBy('category_label')->orderByDesc('aggregate')->limit(6)->get(),
            'cost_visible' => Gate::forUser($actor)->allows('rental_assets.cost_view'),
            'transfer_workflow_supported' => false,
            'rental_revenue_supported' => false,
        ];
    }

    private function assets(User $actor, ?int $storeId): Builder
    {
        return RentalAsset::query()->visibleTo($actor)
            ->when($storeId !== null, fn (Builder $query) => $query->where('rental_assets.store_id', $storeId));
    }

    private function reservations(User $actor, ?int $storeId): Builder
    {
        return AssetReservation::query()->whereHas('asset', fn (Builder $query) => $this->scopeAsset($query, $actor, $storeId));
    }

    private function checkouts(User $actor, ?int $storeId): Builder
    {
        return AssetCheckout::query()->whereHas('asset', fn (Builder $query) => $this->scopeAsset($query, $actor, $storeId));
    }

    private function returns(User $actor, ?int $storeId): Builder
    {
        return AssetReturn::query()->whereHas('asset', fn (Builder $query) => $this->scopeAsset($query, $actor, $storeId));
    }

    private function events(User $actor, ?int $storeId): Builder
    {
        return AssetEvent::query()->whereHas('asset', fn (Builder $query) => $this->scopeAsset($query, $actor, $storeId));
    }

    private function scopeAsset(Builder $query, User $actor, ?int $storeId): Builder
    {
        return $query->visibleTo($actor)
            ->when($storeId !== null, fn (Builder $scope) => $scope->where('rental_assets.store_id', $storeId));
    }
}
