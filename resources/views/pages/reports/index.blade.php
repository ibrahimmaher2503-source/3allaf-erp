@php
    $reportKey = $reportKey ?? 'dashboard';
    $reportMeta = [
        'dashboard' => ['eyebrow' => __('Reporting workspace'), 'description' => __('One trusted view of operational performance, reconciled to the source records behind every number.'), 'icon' => 'squares-2x2', 'tone' => 'primary'],
        'sales' => ['eyebrow' => __('Revenue & settlement'), 'description' => __('Understand sales value, discount pressure, tax, collections, and refunds for the selected operating scope.'), 'icon' => 'chart-bar-square', 'tone' => 'success'],
        'customers' => ['eyebrow' => __('Customer value'), 'description' => __('Review customer growth, loyalty movement, wallet balances, and Gift Card exposure without mixing their ledgers.'), 'icon' => 'users', 'tone' => 'violet'],
        'cash' => ['eyebrow' => __('Cash control'), 'description' => __('See shift status and approved variance signals while preserving reviewer-only financial visibility.'), 'icon' => 'banknotes', 'tone' => 'warning'],
        'purchasing' => ['eyebrow' => __('Supplier operations'), 'description' => __('Compare approved purchasing documents and values across orders, invoices, and returns.'), 'icon' => 'truck', 'tone' => 'info'],
        'inventory' => ['eyebrow' => __('Stock intelligence'), 'description' => __('Track quantity composition, availability, movement activity, and permission-controlled valuation.'), 'icon' => 'cube', 'tone' => 'primary'],
        'parties' => ['eyebrow' => __('Party operations'), 'description' => __('Follow bookings, invoices, payments, operating activity, and outstanding balances in one view.'), 'icon' => 'calendar-days', 'tone' => 'violet'],
        'assets' => ['eyebrow' => __('Rental fleet'), 'description' => __('Monitor asset availability, reservations, returns, condition issues, and controlled cost history.'), 'icon' => 'archive-box', 'tone' => 'info'],
    ][$reportKey] ?? ['eyebrow' => __('Reports'), 'description' => __('Scoped, source-reconciled operational intelligence.'), 'icon' => 'document-chart-bar', 'tone' => 'primary'];

    $focusedReports = [
        'sales' => ['route' => 'reports.sales', 'permission' => 'pos_sales.view', 'label' => __('Sales'), 'icon' => 'chart-bar-square'],
        'customers' => ['route' => 'reports.customers', 'permission' => 'customers.view', 'label' => __('Customers'), 'icon' => 'users'],
        'cash' => ['route' => 'reports.cash', 'permission' => 'shifts_cash_movements.view', 'label' => __('Cash & shifts'), 'icon' => 'banknotes'],
        'purchasing' => ['route' => 'reports.purchasing', 'permission' => 'purchase_orders.view', 'label' => __('Purchasing'), 'icon' => 'truck'],
        'inventory' => ['route' => 'reports.inventory', 'permission' => 'inventory_stock_card.view', 'label' => __('Inventory'), 'icon' => 'cube'],
        'parties' => ['route' => 'reports.parties', 'permission' => 'party_bookings_invoices.view', 'label' => __('Parties'), 'icon' => 'calendar-days'],
        'assets' => ['route' => 'reports.assets', 'permission' => 'rental_assets.view', 'label' => __('Assets'), 'icon' => 'archive-box'],
    ];

    $kpiMeta = [
        'gross_sales' => [__('Gross sales'), 'banknotes', 'info', __('Before discounts and tax')],
        'net_sales' => [__('Net sales before tax'), 'arrow-trending-up', 'success', __('Gross sales less approved discounts')],
        'total_sales' => [__('Final sales value'), 'calculator', 'success', __('Net sales including tax')],
        'net_after_refunds' => [__('Net after refunds'), 'scale', 'success', __('Net sales less completed refunds')],
        'invoice_count' => [__('Approved invoices'), 'document-check', 'primary', __('Documents in the selected range')],
        'average_invoice_value' => [__('Average invoice value'), 'chart-bar', 'info', __('Net sales divided by invoices')],
        'discounts' => [__('Discounts'), 'receipt-percent', 'warning', __('Approved discount value')],
        'tax' => [__('Tax'), 'calculator', 'info', __('Tax captured on selected sales')],
        'payments_collected' => [__('Payments collected'), 'credit-card', 'success', __('Authoritative payment rows')],
        'refunds' => [__('Refunds'), 'receipt-refund', 'danger', __('Completed return settlements')],
        'stock_on_hand' => [__('Stock on hand'), 'cube', 'primary', __('Current scoped quantity')],
        'stock_reserved' => [__('Stock reserved'), 'archive-box', 'warning', __('Committed but not yet released')],
        'stock_available' => [__('Stock available'), 'check-circle', 'success', __('On hand less reserved quantity')],
        'stock_in_transit' => [__('Stock in transit'), 'truck', 'info', __('Dispatched and awaiting receipt')],
        'stock_value' => [__('Stock value'), 'banknotes', 'violet', __('Visible only with cost permission')],
        'purchase_order_count' => [__('Purchase orders'), 'clipboard-document-list', 'primary', __('Approved lifecycle states')],
        'purchase_order_value' => [__('Purchase order value'), 'document-currency-dollar', 'info', __('Approved order value')],
        'purchase_invoice_count' => [__('Purchase invoices'), 'document-check', 'success', __('Approved invoices')],
        'purchase_invoice_value' => [__('Purchase invoice value'), 'banknotes', 'success', __('Approved invoice value')],
        'purchase_return_count' => [__('Supplier returns'), 'arrow-uturn-left', 'warning', __('Approved return documents')],
        'purchase_return_value' => [__('Supplier return value'), 'receipt-refund', 'danger', __('Approved return value')],
        'open_shifts' => [__('Open shifts'), 'clock', 'warning', __('Currently open in scope')],
        'unclosed_shifts' => [__('Unclosed shifts'), 'archive-box-x-mark', 'danger', __('Requiring operational attention')],
        'shift_variance' => [__('Shift variance'), 'scale', 'danger', __('Reviewer-authorized total')],
        'customer_count' => [__('Customers'), 'users', 'primary', __('All visible customer records')],
        'new_customer_count' => [__('New customers'), 'user-plus', 'success', __('Created in the selected range')],
        'loyalty_earned' => [__('Loyalty earned'), 'arrow-trending-up', 'success', __('Source-linked points')],
        'loyalty_redeemed' => [__('Loyalty redeemed'), 'arrow-trending-down', 'warning', __('Source-linked points')],
        'product_wallet_balance' => [__('Product Wallet balance'), 'wallet', 'info', __('Retail wallet kept separate')],
        'party_wallet_balance' => [__('Party Wallet balance'), 'wallet', 'violet', __('Party wallet kept separate')],
        'gift_card_issued' => [__('Gift Card issued'), 'gift', 'info', __('Issued value in range')],
        'gift_card_redeemed' => [__('Gift Card redeemed'), 'arrow-down-tray', 'warning', __('Redeemed value in range')],
        'gift_card_outstanding' => [__('Gift Card outstanding'), 'gift-top', 'violet', __('Current scoped liability')],
        'party_booking_count' => [__('Party bookings'), 'calendar-days', 'primary', __('Bookings in the selected range')],
        'upcoming_party_count' => [__('Upcoming parties'), 'clock', 'warning', __('Confirmed future activity')],
        'party_invoice_count' => [__('Party invoices'), 'document-text', 'info', __('Invoices linked to selected bookings')],
        'party_balance_due' => [__('Party balance due'), 'banknotes', 'danger', __('Outstanding invoice balance')],
        'assets_available' => [__('Assets available'), 'archive-box', 'success', __('Ready for reservation')],
        'assets_reserved' => [__('Assets reserved'), 'calendar', 'warning', __('Currently reserved')],
        'asset_issues' => [__('Asset issues'), 'wrench-screwdriver', 'danger', __('Damaged, maintenance, or lost')],
        'quotation_count' => [__('Quotations'), 'document-text', 'primary', __('Non-posting offers')],
        'quotation_value' => [__('Quotation value'), 'calculator', 'info', __('Non-posting offer value')],
    ];
    $moneyKeys = ['gross_sales', 'net_sales', 'total_sales', 'net_after_refunds', 'average_invoice_value', 'discounts', 'tax', 'payments_collected', 'refunds', 'stock_value', 'purchase_order_value', 'purchase_invoice_value', 'purchase_return_value', 'shift_variance', 'product_wallet_balance', 'party_wallet_balance', 'gift_card_issued', 'gift_card_redeemed', 'gift_card_outstanding', 'party_balance_due', 'quotation_value'];
    $integerKeys = ['invoice_count', 'purchase_order_count', 'purchase_invoice_count', 'purchase_return_count', 'open_shifts', 'unclosed_shifts', 'customer_count', 'new_customer_count', 'loyalty_earned', 'loyalty_redeemed', 'party_booking_count', 'upcoming_party_count', 'party_invoice_count', 'assets_available', 'assets_reserved', 'asset_issues', 'quotation_count'];
    $drilldownRoutes = ['gross_sales' => 'sales.index', 'net_sales' => 'sales.index', 'total_sales' => 'sales.index', 'net_after_refunds' => 'sales.index', 'invoice_count' => 'sales.index', 'average_invoice_value' => 'sales.index', 'discounts' => 'sales.index', 'tax' => 'sales.index', 'payments_collected' => 'sales.index', 'refunds' => 'sales.index', 'stock_on_hand' => 'inventory.index', 'stock_value' => 'inventory.index', 'quotation_count' => 'quotations.index', 'quotation_value' => 'quotations.index', 'purchase_order_count' => 'purchasing.orders', 'purchase_order_value' => 'purchasing.orders', 'purchase_invoice_count' => 'purchasing.invoices', 'purchase_invoice_value' => 'purchasing.invoices', 'purchase_return_count' => 'purchasing.returns', 'purchase_return_value' => 'purchasing.returns', 'open_shifts' => 'pos.shift', 'unclosed_shifts' => 'pos.shift', 'shift_variance' => 'pos.shift', 'customer_count' => 'customers.index', 'new_customer_count' => 'customers.index', 'loyalty_earned' => 'customers.index', 'loyalty_redeemed' => 'customers.index', 'product_wallet_balance' => 'customers.index', 'party_wallet_balance' => 'customers.index', 'gift_card_outstanding' => 'gift.cards.index', 'party_booking_count' => 'parties.bookings.index', 'upcoming_party_count' => 'parties.bookings.index', 'party_invoice_count' => 'parties.invoices.index', 'party_balance_due' => 'parties.invoices.index', 'assets_available' => 'party.assets.index', 'assets_reserved' => 'party.assets.index', 'asset_issues' => 'party.assets.index'];
    $meaningfulFilterKeys = collect($report['filters'])->filter(fn ($value, $key) => filled($value) && ! in_array($key, ['date_from', 'date_to', 'module'], true));
    $filterLabels = [
        'search' => __('Search'), 'branch_id' => __('Branch'), 'store_id' => __('Store'),
        'user_id' => __('User / cashier'), 'product_id' => __('Product'), 'category_id' => __('Category'),
        'product_type' => __('Product type'), 'product_status' => __('Product status'), 'brand_id' => __('Brand'),
        'age_label_id' => __('Age'), 'character_id' => __('Character'), 'colour_id' => __('Colour'),
        'gender_id' => __('Gender'), 'payment_method_id' => __('Payment method'), 'customer_id' => __('Customer'),
        'supplier_id' => __('Supplier'), 'document_status' => __('Document status'), 'party_status' => __('Party status'),
        'sort' => __('Sort'), 'direction' => __('Direction'),
    ];
    $showFor = fn (array $modules) => $reportKey === 'dashboard' || in_array($reportKey, $modules, true);
@endphp

<x-layouts::app :title="$reportTitle ?? __('Reports')">
    <x-app.page :title="$reportTitle ?? __('Dashboard & KPI reports')" :description="$reportMeta['description']" :eyebrow="$reportMeta['eyebrow']" max-width="7xl" data-report-dashboard>
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if (Gate::allows('dashboard_reports.export_xlsx') || Gate::allows('dashboard_reports.export_pdf'))
                    @foreach(['csv' => __('CSV'), 'xlsx' => __('Excel'), 'pdf' => __('PDF')] as $format => $label)
                        @can('dashboard_reports.export_'.($format === 'csv' ? 'xlsx' : $format))
                            <form method="POST" action="{{ route('reports.export') }}">@csrf @foreach($report['filters'] as $key => $value) @continue(in_array($key, ['page', 'per_page'], true)) <input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<input type="hidden" name="format" value="{{ $format }}"><flux:button type="submit" size="sm" :icon="$format === 'xlsx' ? 'table-cells' : 'document-arrow-down'" :aria-label="$format === 'xlsx' ? __('Create Excel export') : __('Create PDF export')">{{ $label }}</flux:button></form>
                        @endcan
                    @endforeach
                @endif
                <flux:button href="{{ route('exports.index') }}" size="sm" variant="subtle" icon="archive-box-arrow-down" wire:navigate>{{ __('Export center') }}</flux:button>
            </div>
        </x-slot:actions>

        @if (session('success')) <flux:callout variant="success">{{ session('success') }}</flux:callout> @endif
        @if ($errors->any()) <flux:callout variant="danger"><ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout> @endif

        @if($reportKey === 'dashboard' && (Gate::allows('dashboard_reports.export_xlsx') || Gate::allows('dashboard_reports.export_pdf')))
        <flux:card class="p-4"><form method="POST" action="{{ route('reports.export') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf
            <flux:select name="dataset" :label="__('Export dataset')" required>
                @foreach(['products_barcodes','customers_groups','suppliers_groups','purchase_orders','purchase_invoices_receiving','opening_inventory','inventory_movements'] as $dataset)<option value="{{ $dataset }}">{{ __((string) str($dataset)->replace('_',' ')->title()) }}</option>@endforeach
            </flux:select>
            <flux:select name="format" :label="__('Format')"><option value="csv">CSV</option><option value="xlsx">XLSX</option><option value="pdf">PDF</option></flux:select>
            <flux:input type="date" name="date_from" :label="__('From')" value="{{ $report['filters']['date_from'] }}" />
            <flux:input type="date" name="date_to" :label="__('To')" value="{{ $report['filters']['date_to'] }}" />
            <flux:select name="branch_id" :label="__('Branch')"><option value="">{{ __('All visible branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} · {{ str_starts_with(app()->getLocale(),'ar')?$branch->name_ar:$branch->name_en }}</option>@endforeach</flux:select>
            <flux:select name="supplier_id" :label="__('Supplier')"><option value="">{{ __('All suppliers') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} · {{ $supplier->name_en }}</option>@endforeach</flux:select>
            <flux:select name="category_id" :label="__('Category')"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->code }} · {{ $category->name_en }}</option>@endforeach</flux:select>
            <flux:select name="document_status" :label="__('Status')"><option value="">{{ __('All Statuses') }}</option>@foreach(['draft','submitted','approved','partially_received','received','awaiting_distribution','rejected','cancelled','closed'] as $status)<option value="{{ $status }}">{{ __((string) str($status)->replace('_',' ')->title()) }}</option>@endforeach</flux:select>
            <div class="flex items-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Request export') }}</flux:button></div>
        </form></flux:card>
        @endif

        <nav class="flex gap-2 overflow-x-auto rounded-xl border border-border bg-surface p-2" aria-label="{{ __('Report categories') }}">
            <a href="{{ route('reports.index') }}" wire:navigate class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border px-3 text-sm font-semibold transition {{ $reportKey === 'dashboard' ? 'border-primary bg-primary text-white shadow-sm' : 'border-border bg-surface text-text-muted hover:border-primary/40 hover:text-text-primary' }}"><flux:icon name="squares-2x2" class="size-4" />{{ __('Overview') }}</a>
            @foreach($focusedReports as $key => $item)
                @can($item['permission'])<a href="{{ route($item['route']) }}" wire:navigate class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border px-3 text-sm font-semibold transition {{ $reportKey === $key ? 'border-primary bg-primary text-white shadow-sm' : 'border-border bg-surface text-text-muted hover:border-primary/40 hover:text-text-primary' }}"><flux:icon :name="$item['icon']" class="size-4" />{{ $item['label'] }}</a>@endcan
            @endforeach
        </nav>

        <section class="overflow-hidden rounded-2xl border border-border/80 bg-surface shadow-card" aria-labelledby="report-filters-heading">
            <div class="flex flex-col gap-2 border-b border-border/70 bg-surface-muted/40 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3"><span class="flex size-9 items-center justify-center rounded-xl bg-primary-soft text-primary"><flux:icon name="funnel" class="size-4" /></span><div><h2 id="report-filters-heading" class="text-sm font-semibold text-text-primary">{{ __('Report scope') }}</h2><p class="text-xs text-text-muted">{{ __('Filters update cards, visuals, details, and exports together.') }}</p></div></div>
                <div class="flex flex-wrap items-center gap-2"><flux:badge color="zinc" size="sm">{{ $report['filters']['date_from'] }} → {{ $report['filters']['date_to'] }}</flux:badge><flux:badge color="teal" size="sm">{{ trans_choice(':count active filter|:count active filters', $meaningfulFilterKeys->count(), ['count' => $meaningfulFilterKeys->count()]) }}</flux:badge></div>
            </div>
            <div class="flex flex-wrap gap-2 border-b border-border/70 px-4 py-2" aria-label="{{ __('Date presets') }}"><span class="me-1 self-center text-xs font-semibold text-text-muted">{{ __('Date presets') }}</span><a class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:border-primary/40" href="{{ request()->fullUrlWithQuery(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString(), 'page' => 1]) }}">{{ __('Today') }}</a><a class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:border-primary/40" href="{{ request()->fullUrlWithQuery(['date_from' => now()->subDays(6)->toDateString(), 'date_to' => now()->toDateString(), 'page' => 1]) }}">{{ __('Last 7 days') }}</a><a class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:border-primary/40" href="{{ request()->fullUrlWithQuery(['date_from' => now()->subDays(29)->toDateString(), 'date_to' => now()->toDateString(), 'page' => 1]) }}">{{ __('Last 30 days') }}</a></div>
            @if($meaningfulFilterKeys->isNotEmpty())<div class="flex flex-wrap gap-2 border-b border-border/70 px-4 py-2" aria-label="{{ __('Active filters') }}">@foreach($meaningfulFilterKeys as $key => $value)<a href="{{ request()->fullUrlWithQuery([$key => null, 'page' => 1]) }}" class="inline-flex min-h-9 items-center gap-1 rounded-full bg-primary-soft px-3 text-xs font-semibold text-primary"><span>{{ $filterLabels[$key] ?? __('Filter') }}: {{ $value }}</span><flux:icon name="x-mark" class="size-3.5" /></a>@endforeach</div>@endif
            <form method="GET" action="{{ url()->current() }}" class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <flux:input name="date_from" type="date" label="{{ __('From') }}" value="{{ $report['filters']['date_from'] }}" />
                <flux:input name="date_to" type="date" label="{{ __('To') }}" value="{{ $report['filters']['date_to'] }}" />
                @if($reportKey === 'sales')<flux:input name="search" type="search" label="{{ __('Search report details') }}" value="{{ $report['filters']['search'] ?? '' }}" />@endif
                <flux:select name="branch_id" label="{{ __('Branch') }}"><option value="">{{ __('All visible branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $report['filters']['branch_id'] === (string) $branch->id)>{{ $branch->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($branch->name_ar ?: $branch->name_en) : ($branch->name_en ?: $branch->name_ar) }}</option>@endforeach</flux:select>
                <flux:select name="store_id" label="{{ __('Store') }}"><option value="">{{ __('All visible stores') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((string) $report['filters']['store_id'] === (string) $store->id)>{{ $store->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($store->name_ar ?: $store->name_en) : ($store->name_en ?: $store->name_ar) }}</option>@endforeach</flux:select>
                @if($showFor(['sales', 'cash']))<flux:select name="user_id" label="{{ __('User / cashier') }}"><option value="">{{ __('All visible users') }}</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) $report['filters']['user_id'] === (string) $user->id)>{{ $user->name }}{{ $user->username ? ' · '.$user->username : '' }}</option>@endforeach</flux:select>@endif
                @if($showFor(['sales', 'inventory']) && $products->isNotEmpty())<flux:select name="product_id" label="{{ __('Product') }}"><option value="">{{ __('All products') }}</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) $report['filters']['product_id'] === (string) $product->id)>{{ $product->item_code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($product->parent?->name_ar ?? $product->name_ar) : ($product->parent?->name_en ?? $product->name_en) }}@if($product->isVariant()) · {{ $product->localizedVariationLabel() }}@endif</option>@endforeach</flux:select>@endif
                @if($showFor(['sales', 'inventory']) && $categories->isNotEmpty())<flux:select name="category_id" label="{{ __('Category') }}"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) $report['filters']['category_id'] === (string) $category->id)>{{ $category->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($category->name_ar ?: $category->name_en) : ($category->name_en ?: $category->name_ar) }}</option>@endforeach</flux:select>@endif
                @if($showFor(['inventory']))
                    <flux:select name="product_type" label="{{ __('Product type') }}"><option value="">{{ __('All product types') }}</option>@foreach(['standard','service','bundle','rental','party_consumable'] as $type)<option value="{{ $type }}" @selected($report['filters']['product_type'] === $type)>{{ __((string) str($type)->replace('_',' ')->title()) }}</option>@endforeach</flux:select>
                    <flux:select name="product_status" label="{{ __('Product status') }}"><option value="">{{ __('All product statuses') }}</option>@foreach(['active','inactive','archived'] as $status)<option value="{{ $status }}" @selected($report['filters']['product_status'] === $status)>{{ __((string) str($status)->title()) }}</option>@endforeach</flux:select>
                    @foreach([['brand_id',$brands,'Brand','All brands'],['age_label_id',$ages,'Age','All ages'],['character_id',$characters,'Character','All characters'],['colour_id',$colours,'Colour','All colours'],['gender_id',$genders,'Gender','All genders']] as [$field,$options,$label,$empty])
                        @if($options->isNotEmpty())<flux:select name="{{ $field }}" label="{{ __($label) }}"><option value="">{{ __($empty) }}</option>@foreach($options as $option)<option value="{{ $option->id }}" @selected((string) $report['filters'][$field] === (string) $option->id)>{{ str_starts_with(app()->getLocale(), 'ar') ? ($option->name_ar ?: $option->name_en) : ($option->name_en ?: $option->name_ar) }}</option>@endforeach</flux:select>@endif
                    @endforeach
                @endif
                @if($showFor(['sales', 'cash']) && $paymentMethods->isNotEmpty())<flux:select name="payment_method_id" label="{{ __('Payment method') }}"><option value="">{{ __('All payment methods') }}</option>@foreach($paymentMethods as $method)<option value="{{ $method->id }}" @selected((string) $report['filters']['payment_method_id'] === (string) $method->id)>{{ $method->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($method->name_ar ?: $method->name_en) : ($method->name_en ?: $method->name_ar) }}</option>@endforeach</flux:select>@endif
                @if($showFor(['sales', 'customers', 'parties']) && $customers->isNotEmpty())<flux:select name="customer_id" label="{{ __('Customer') }}"><option value="">{{ __('All customers') }}</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) $report['filters']['customer_id'] === (string) $customer->id)>{{ str_starts_with(app()->getLocale(), 'ar') ? ($customer->name_ar ?: $customer->name_en) : ($customer->name_en ?: $customer->name_ar) }}@if(isset($customer->phone_display)) · {{ $customer->phone_display }}@endif</option>@endforeach</flux:select>@endif
                @if($showFor(['purchasing']) && $suppliers->isNotEmpty())<flux:select name="supplier_id" label="{{ __('Supplier') }}"><option value="">{{ __('All suppliers') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) $report['filters']['supplier_id'] === (string) $supplier->id)>{{ $supplier->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? ($supplier->name_ar ?: $supplier->name_en) : ($supplier->name_en ?: $supplier->name_ar) }}</option>@endforeach</flux:select>@endif
                @if($showFor(['sales']))<flux:select name="document_status" label="{{ __('Document status') }}"><option value="">{{ __('Approved financial records') }}</option>@foreach(['approved' => __('Approved'), 'suspended' => __('Suspended'), 'cancelled' => __('Cancelled')] as $status => $label)<option value="{{ $status }}" @selected($report['filters']['document_status'] === $status)>{{ $label }}</option>@endforeach</flux:select>@endif
                @if($showFor(['parties']))<flux:select name="party_status" label="{{ __('Party status') }}"><option value="">{{ __('All party statuses') }}</option>@foreach(['draft' => __('Draft'), 'confirmed' => __('Confirmed'), 'closed' => __('Closed'), 'cancelled' => __('Cancelled')] as $status => $label)<option value="{{ $status }}" @selected($report['filters']['party_status'] === $status)>{{ $label }}</option>@endforeach</flux:select>@endif
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3 xl:col-span-4"><flux:button type="submit" variant="primary" icon="funnel">{{ __('Apply filters') }}</flux:button><flux:button href="{{ url()->current() }}" variant="subtle" icon="arrow-path">{{ __('Reset') }}</flux:button><span class="ms-auto hidden text-xs text-text-muted sm:inline">{{ __('Fresh at') }} {{ $report['fresh_at'] }} · {{ __('Maximum 366 days') }}</span></div>
            </form>
        </section>

        <section aria-labelledby="report-kpis-heading">
            <div class="mb-4 flex items-end justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-primary">{{ __('At a glance') }}</p><h2 id="report-kpis-heading" class="mt-1 text-lg font-semibold text-text-primary">{{ __('Performance signals') }}</h2></div><span class="text-xs text-text-muted">{{ trans_choice(':count reconciled metric|:count reconciled metrics', count($report['kpis']), ['count' => count($report['kpis'])]) }}</span></div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($report['kpis'] as $key => $value)
                    @php
                        [$label, $icon, $tone, $description] = $kpiMeta[$key] ?? [str($key)->replace('_', ' ')->title(), 'chart-bar', 'primary', __('Scoped report metric')];
                        $formatted = in_array($key, $integerKeys, true) ? number_format((float) $value, 0) : number_format((float) $value, 2);
                        $href = isset($drilldownRoutes[$key]) && Route::has($drilldownRoutes[$key]) ? route($drilldownRoutes[$key], array_filter($report['filters'], fn ($item) => filled($item))) : null;
                    @endphp
                    <x-reports.kpi :$label :value="$formatted" :$description :$icon :$tone :$href />
                @endforeach
            </div>
        </section>

        @if(!empty($report['visuals']))
            <section aria-labelledby="report-visuals-heading">
                <div class="mb-4"><p class="text-xs font-semibold uppercase tracking-[0.14em] text-primary">{{ __('Visual analysis') }}</p><h2 id="report-visuals-heading" class="mt-1 text-lg font-semibold text-text-primary">{{ __('Patterns worth acting on') }}</h2><p class="mt-1 text-sm text-text-muted">{{ __('Charts use the same scoped values as the cards and accessible data tables below them.') }}</p></div>
                <div class="grid gap-5 xl:grid-cols-2">@foreach(collect($report['visuals'])->take($reportKey === 'dashboard' ? 4 : 3) as $visual)<x-reports.visual :$visual />@endforeach</div>
            </section>
        @endif

        <x-cards.section-card :title="__('Source reconciliation')" :description="__('Every displayed total comes from the authoritative source records under this exact filter set.')">
            <x-slot:actions><flux:badge color="emerald" icon="check-circle">{{ __('Source aligned') }}</flux:badge></x-slot:actions>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @if(in_array('sales', $report['modules'], true))<div class="rounded-xl border border-border bg-surface-muted/30 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $report['sources']['sales_label'] }}</p><p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ number_format($report['sources']['sales_count']) }}</p><p class="mt-1 text-xs text-text-muted">{{ number_format($report['sources']['sales_total'], 2) }} {{ __('final value') }}</p></div>@endif
                @if(in_array('inventory', $report['modules'], true))<div class="rounded-xl border border-border bg-surface-muted/30 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('Stock balances') }}</p><p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ number_format($report['sources']['stock_balance_rows']) }}</p><p class="mt-1 text-xs text-text-muted">{{ array_key_exists('stock_value', $report['kpis']) ? number_format((float) $report['kpis']['stock_value'], 2).' '.__('valuation') : __('Cost field restricted') }}</p></div>@endif
                @if(in_array('purchasing', $report['modules'], true))<div class="rounded-xl border border-border bg-surface-muted/30 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('Purchasing sources') }}</p><p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ number_format($report['sources']['purchase_order_count'] + $report['sources']['purchase_invoice_count'] + $report['sources']['purchase_return_count']) }}</p><p class="mt-1 text-xs text-text-muted">{{ __('Approved orders, invoices, and returns') }}</p></div>@endif
                @if(in_array('cash', $report['modules'], true))<div class="rounded-xl border border-border bg-surface-muted/30 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('Shift sources') }}</p><p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ number_format($report['sources']['shift_unclosed_rows']) }}</p><p class="mt-1 text-xs text-text-muted">{{ __('Open or awaiting closure') }}</p></div>@endif
                @if(in_array('customers', $report['modules'], true))<div class="rounded-xl border border-border bg-surface-muted/30 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('Customer sources') }}</p><p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ number_format($report['sources']['customer_rows']) }}</p><p class="mt-1 text-xs text-text-muted">{{ __('Visible customer population') }}</p></div>@endif
                @if(in_array('parties', $report['modules'], true))<div class="rounded-xl border border-border bg-surface-muted/30 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('Party sources') }}</p><p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ number_format($report['sources']['party_booking_rows']) }}</p><p class="mt-1 text-xs text-text-muted">{{ number_format($report['sources']['party_invoice_rows']) }} {{ __('linked invoices') }}</p></div>@endif
                @if(in_array('assets', $report['modules'], true))<div class="rounded-xl border border-border bg-surface-muted/30 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('Rental assets') }}</p><p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ number_format($report['sources']['asset_rows']) }}</p><p class="mt-1 text-xs text-text-muted">{{ __('Scoped asset register') }}</p></div>@endif
            </div>
            @if(in_array('sales', $report['modules'], true) && $report['sources']['payment_method_summary'] !== [])<div class="mt-4 flex flex-wrap gap-2">@foreach($report['sources']['payment_method_summary'] as $payment)<flux:badge color="sky">{{ $payment['method_code'] }} · {{ number_format((float) $payment['amount'], 2) }}</flux:badge>@endforeach</div>@endif
        </x-cards.section-card>

        <section aria-labelledby="report-details-heading">
            <div class="mb-4"><p class="text-xs font-semibold uppercase tracking-[0.14em] text-primary">{{ __('Evidence') }}</p><h2 id="report-details-heading" class="mt-1 text-lg font-semibold text-text-primary">{{ __('Source detail') }}</h2><p class="mt-1 text-sm text-text-muted">{{ __('Each source section is bounded to 50 rows for responsive review and safe export preparation.') }}</p></div>
            <div class="space-y-5">
                @foreach($report['detail_sections'] as $section)
                    <x-tables.data-panel :title="$section['title']" :description="trans_choice(':count row in the selected scope|:count rows in the selected scope', count($section['rows']), ['count' => count($section['rows'])])">
                        <x-slot:actions><flux:badge color="zinc" size="sm">{{ count($section['rows']) }} / 50</flux:badge></x-slot:actions>
                        <table class="data-table min-w-[720px] w-full text-sm"><thead><tr>@foreach($section['columns'] as $key => $label)<th>@if(isset($section['sortable'][$key]))<a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'direction' => (($report['filters']['sort'] ?? null) === $key && ($report['filters']['direction'] ?? 'desc') === 'asc') ? 'desc' : 'asc', 'page' => 1]) }}" class="underline decoration-dotted underline-offset-4">{{ $label }}@if(($report['filters']['sort'] ?? null) === $key) {{ ($report['filters']['direction'] ?? 'desc') === 'asc' ? '↑' : '↓' }}@endif</a>@else{{ $label }}@endif</th>@endforeach</tr></thead><tbody>
                            @forelse($section['rows'] as $row)<tr class="transition-colors hover:bg-primary-soft/30">@foreach(array_keys($section['columns']) as $key)<td @class(['tabular-nums' => is_numeric($row[$key] ?? null)])>{{ is_numeric($row[$key] ?? null) ? number_format((float) $row[$key], 2) : ($row[$key] ?? '—') }}</td>@endforeach</tr>
                            @empty<tr><td colspan="{{ count($section['columns']) }}"><x-state.empty :title="__('No matching source rows in this report range.')" :description="__('Try widening the date range or clearing a focused filter.')" /></td></tr>@endforelse
                        </tbody></table>
                        @if(!empty($section['pagination']))<nav class="mt-4 flex items-center justify-between gap-3"><span>{{ trans_choice(':count row|:count rows', $section['pagination']['total'], ['count' => $section['pagination']['total']]) }} · {{ __('Page :current of :last', ['current' => $section['pagination']['current_page'], 'last' => $section['pagination']['last_page']]) }}</span><div>@if($section['pagination']['current_page'] > 1)<a href="{{ request()->fullUrlWithQuery(['page' => $section['pagination']['current_page'] - 1]) }}">{{ __('Previous') }}</a>@endif @if($section['pagination']['current_page'] < $section['pagination']['last_page'])<a href="{{ request()->fullUrlWithQuery(['page' => $section['pagination']['current_page'] + 1]) }}">{{ __('Next') }}</a>@endif</div></nav>@endif
                    </x-tables.data-panel>
                @endforeach
            </div>
        </section>
    </x-app.page>
</x-layouts::app>
