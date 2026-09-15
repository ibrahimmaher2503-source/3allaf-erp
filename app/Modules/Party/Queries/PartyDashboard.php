<?php

declare(strict_types=1);

namespace App\Modules\Party\Queries;

use App\Models\User;
use App\Modules\Party\Models\PartyBooking;
use App\Modules\Party\Models\PartyInvoice;
use App\Modules\Party\Models\PartyInvoiceLine;
use App\Modules\Party\Models\PartyOperatingOrder;
use App\Modules\Party\Models\PartyPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PartyDashboard
{
    /** @return array<string, mixed> */
    public function for(User $actor, ?int $storeId = null): array
    {
        $bookings = PartyBooking::query()->visibleTo($actor)
            ->when($storeId !== null, fn (Builder $query) => $query->where('party_bookings.store_id', $storeId));
        $invoices = PartyInvoice::query()->visibleTo($actor)
            ->when($storeId !== null, fn (Builder $query) => $query->whereHas('booking', fn (Builder $booking) => $booking->where('party_bookings.store_id', $storeId)));
        $orders = PartyOperatingOrder::query()->visibleTo($actor)
            ->when($storeId !== null, fn (Builder $query) => $query->where('party_operating_orders.store_id', $storeId));
        $payments = PartyPayment::query()->where('party_payments.status', 'approved')
            ->whereHas('booking', fn (Builder $booking) => $booking->visibleTo($actor)
                ->when($storeId !== null, fn (Builder $scope) => $scope->where('party_bookings.store_id', $storeId)));
        $today = now()->toDateString();
        $periodStart = now()->startOfDay()->subDays(6);
        $periodEnd = now()->endOfDay();
        $openStatuses = ['draft', 'tentative', 'confirmed', 'rescheduled', 'in_operation', 'completed_pending_settlement'];

        return [
            'today' => (clone $bookings)->whereDate('party_bookings.party_date', $today)->whereNot('party_bookings.status', 'cancelled')->count(),
            'upcoming' => (clone $bookings)->whereBetween('party_bookings.party_date', [$today, now()->addDays(7)->toDateString()])->whereIn('party_bookings.status', $openStatuses)->count(),
            'confirmed' => (clone $bookings)->where('party_bookings.status', 'confirmed')->count(),
            'follow_up' => (clone $bookings)->whereIn('party_bookings.status', ['draft', 'tentative', 'rescheduled', 'completed_pending_settlement'])->count(),
            'open_orders' => (clone $orders)->whereIn('party_operating_orders.status', ['draft', 'released', 'in_progress'])->count(),
            'period_bookings' => (clone $bookings)->whereBetween('party_bookings.party_date', [$periodStart->toDateString(), $periodEnd->toDateString()])->whereNot('party_bookings.status', 'cancelled')->count(),
            'period_total' => (string) (clone $invoices)->whereHas('booking', fn (Builder $booking) => $booking->whereBetween('party_bookings.party_date', [$periodStart->toDateString(), $periodEnd->toDateString()]))->sum('party_invoices.total_amount'),
            'period_paid' => (string) (clone $payments)->whereBetween('party_payments.approved_at', [$periodStart, $periodEnd])->sum('party_payments.amount'),
            'outstanding' => (string) (clone $invoices)->whereNotIn('party_invoices.state', ['cancelled', 'corrected_by_reference'])->where('party_invoices.balance_due', '>', 0)->sum('party_invoices.balance_due'),
            'trend' => (clone $bookings)->whereBetween('party_bookings.party_date', [$periodStart->toDateString(), $periodEnd->toDateString()])->whereNot('party_bookings.status', 'cancelled')->selectRaw('party_date, COUNT(*) as booking_count')->groupBy('party_bookings.party_date')->orderBy('party_bookings.party_date')->get()->keyBy(fn ($row) => $row->party_date->format('Y-m-d')),
            'upcoming_rows' => (clone $bookings)->with(['customer:id,name_ar,name_en', 'store:id,code,name_ar,name_en', 'invoice:id,party_booking_id,balance_due,currency_code'])->whereBetween('party_bookings.party_date', [$today, now()->addDays(14)->toDateString()])->whereIn('party_bookings.status', $openStatuses)->orderBy('party_bookings.starts_at')->limit(8)->get(),
            'recent_rows' => (clone $bookings)->with(['customer:id,name_ar,name_en', 'store:id,code,name_ar,name_en'])->latest('party_bookings.updated_at')->limit(6)->get(),
            'top_services' => PartyInvoiceLine::query()->whereIn('party_invoice_lines.party_invoice_id', (clone $invoices)->select('party_invoices.id'))->where('party_invoice_lines.line_type', 'service')->whereBetween('party_invoice_lines.created_at', [$periodStart, $periodEnd])->select('party_invoice_lines.description_ar', 'party_invoice_lines.description_en', DB::raw('SUM(party_invoice_lines.quantity) as service_quantity'))->groupBy('party_invoice_lines.description_ar', 'party_invoice_lines.description_en')->orderByDesc('service_quantity')->limit(5)->get(),
            'payment_mix' => (clone $payments)->whereBetween('party_payments.approved_at', [$periodStart, $periodEnd])->select('party_payments.method_type', DB::raw('SUM(party_payments.amount) as total_amount'))->groupBy('party_payments.method_type')->orderByDesc('total_amount')->get(),
        ];
    }
}
