<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Support\ProductPricingReadiness;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:diagnose-fractional-quantities', function () {
    $targets=['stock_balances'=>['on_hand','reserved','in_transit'],'stock_movements'=>['quantity'],'purchase_invoice_lines'=>['quantity'],'purchase_invoice_distributions'=>['quantity'],'stock_transfer_lines'=>['quantity_requested','quantity_dispatched','quantity_received'],'sale_lines'=>['quantity'],'purchase_return_lines'=>['quantity'],'opening_inventory_lines'=>['quantity'],'inventory_adjustment_lines'=>['quantity_delta'],'stock_count_lines'=>['expected_quantity','counted_quantity','variance_quantity']];
    $total=0;
    foreach($targets as $table=>$columns){if(!Schema::hasTable($table))continue;foreach($columns as $column){if(!Schema::hasColumn($table,$column))continue;$count=DB::table($table)->whereNotNull($column)->whereRaw("MOD(ABS($column), 1) <> 0")->count();if($count){$this->line("$table.$column: $count");$total+=$count;}}}
    $this->info($total===0?__('No fractional product quantities were found.'):__('Fractional quantity cells found: :count. No data was changed.',['count'=>$total]));
    return $total===0?0:2;
})->purpose('Read-only report of fractional product quantities; never changes data.');

Artisan::command('catalog:repair-base-consumer-price {company} {item-code} {price} {--apply}', function () {
    $company = Company::query()->where('code', (string) $this->argument('company'))->get();
    if ($company->count() !== 1) { $this->error('Company identifier must match exactly one company.'); return 2; }
    $products = Product::query()->where('item_code', (string) $this->argument('item-code'))->get();
    if ($products->count() !== 1) { $this->error('Item code must match exactly one product.'); return 2; }
    $price = filter_var($this->argument('price'), FILTER_VALIDATE_FLOAT);
    if ($price === false || $price <= 0) { $this->error('An explicit positive consumer price is required.'); return 2; }
    $product = $products->first();
    $readiness = app(ProductPricingReadiness::class);
    $before = $readiness->assessProducts([$product])['product_cards_complete'] ? 'ready' : 'not_ready';
    $this->line('MODE='.($this->option('apply') ? 'APPLY' : 'DRY_RUN'));
    $this->line('ITEM_CODE='.$product->item_code);
    $this->line('PRICE_BEFORE='.($product->sale_price ?? 'NULL'));
    $this->line('READINESS_BEFORE='.$before);
    if (! $this->option('apply')) { $this->line('PRICE_AFTER='.number_format((float)$price, 2, '.', '')); $this->line('READINESS_AFTER=not_applied'); return 0; }
    DB::transaction(function () use ($product, $price): void {
        $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
        if (! $locked->updated_at?->equalTo($product->updated_at)) throw new RuntimeException('Product changed after the dry-run guard was evaluated.');
        DB::table('products')->where('id', $locked->id)->update(['sale_price' => number_format((float)$price, 2, '.', '')]);
    });
    $fresh = Product::query()->with('category:id,status')->findOrFail($product->id);
    $after = $readiness->assessProducts([$fresh])['product_cards_complete'] ? 'ready' : 'not_ready';
    $this->line('PRICE_AFTER='.$fresh->sale_price);
    $this->line('READINESS_AFTER='.$after);
    return 0;
})->purpose('Guarded dry-run-first repair of one Product Card base consumer price.');

Schedule::command('platform:backup:run')
    ->dailyAt('02:30')
    ->name('platform-backup-run')
    ->withoutOverlapping(120);

Schedule::command('backup:clean --disable-notifications --isolated')
    ->dailyAt('03:30')
    ->name('platform-backup-clean')
    ->withoutOverlapping(120);

Schedule::command('backup:monitor --isolated')
    ->hourly()
    ->name('platform-backup-monitor')
    ->withoutOverlapping(30);

if (app()->environment('staging') && (bool) env('STAGING_SCHEDULER_PROBE', false)) {
    Schedule::command('platform:staging-scheduler-probe')
        ->everyMinute()
        ->name('staging-scheduler-probe')
        ->withoutOverlapping(1);
}
