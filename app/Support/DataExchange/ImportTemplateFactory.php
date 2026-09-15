<?php

declare(strict_types=1);

namespace App\Support\DataExchange;

use App\Models\User;
use App\Modules\Catalog\Actions\ImportProductCardsWorkbookAction;
use App\Modules\Catalog\Actions\StageCatalogReferenceImportAction;
use App\Modules\Catalog\Actions\StageProductImportAction;
use App\Modules\Catalog\Actions\StageSupplierImportAction;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Catalog\Support\SupplierSettlementMethod;
use App\Modules\Customer\Actions\ImportCustomerGroups;
use App\Modules\Customer\Actions\StageCustomerImportAction;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Inventory\Actions\ImportOpeningInventoryWorkbookAction;
use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ImportTemplateFactory
{
    public function __construct(private readonly MasterDataDocument $documents, private readonly AuthorizedCompanyContext $companies) {}

    public function openingInventory(User $actor, mixed $companyId = null): BinaryFileResponse
    {
        $company = $this->companies->resolve($actor, $companyId);
        $rows = Product::query()->sellable()->where('product_type', '!=', 'service')->whereNotNull('average_cost')
            ->where(fn ($query) => $query->whereDoesntHave('storeAssignments')->orWhereHas('storeAssignments', fn ($assignments) => $assignments->where('company_id', $company->id)->where('status', 'active')))
            ->with([
                'barcodes' => fn ($query) => $query->where('status', 'active')->orderByDesc('is_primary'),
                'preferredProductSupplier' => fn ($query) => $query->whereHas('supplier', fn ($suppliers) => $suppliers->whereNull('supplier_group_id')->orWhereHas('supplierGroup', fn ($groups) => $groups->where('company_id', $company->id))),
            ])
            ->orderBy('id')->lazyById(500)->map(fn (Product $product): array => [
                $product->item_code, $product->item_code, $product->preferredProductSupplier?->supplier_item_code ?? '', $product->model_number ?? '',
                $product->barcodes->first()?->barcode ?? '', $product->name_ar, $product->name_en ?? '', '', '', $product->average_cost ?? '0.0000',
            ]);
        $stores = Store::query()->visibleTo($actor)->where('company_id', $company->id)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->with('branch:id,code,name_ar,name_en')->orderBy('code')->get();

        return $this->documents->importTemplate('opening-inventory-template.xlsx', 'toyjoy.opening-inventory.v3', ImportOpeningInventoryWorkbookAction::HEADERS, $rows, [
            ['ar' => 'تحتوي الورقة على أحدث الأصناف الفعالة المؤهلة وقت التنزيل. املأ الموقع والكمية والتكلفة للصفوف المطلوبة، واستخدم موقعاً واحداً في الملف.', 'en' => 'The sheet contains the latest eligible active products. Fill location, quantity, and cost for required rows and use one location per workbook.'],
            ['ar' => 'يمكن البحث بكود الصنف أو المورد أو الموديل أو الباركود؛ أعمدة المرجع مقفلة لمنع الربط الخاطئ.', 'en' => 'Products resolve by internal, supplier, model, or barcode code; locked reference columns prevent incorrect mapping.'],
            ['ar' => 'استخدم كود مخزن أو منفذ بيع ظاهر في ورقة المواقع المصرح بها.', 'en' => 'Use a warehouse or stock-bearing Sales Outlet code from Authorized Locations.'],
        ], ['Authorized Locations' => [['location_code', 'branch_code', 'type', 'name_ar', 'name_en'], ...$stores->map(fn (Store $store): array => [$store->code, $store->branch?->code ?? '', $store->type, $store->name_ar, $store->name_en])->all()]], [7, 8, 9], [0, 1, 2, 3, 4, 5, 6], [8 => '0', 9 => '0.0000'], $company->code.' — '.$company->name_ar, [7 => ['sheet' => 'Authorized Locations', 'last_row' => $stores->count() + 1]]);
    }

    public function productCards(User $actor, bool $staged = false, mixed $companyId = null): BinaryFileResponse
    {
        $company = $this->companies->resolve($actor, $companyId);
        $headers = $staged ? StageProductImportAction::supportedFields() : ImportProductCardsWorkbookAction::HEADERS;
        $categories = Category::query()->where('status', 'active')->orderBy('code')->get(['code', 'name_ar', 'name_en']);
        $brands = Brand::query()->where('status', 'active')->orderBy('code')->get(['code', 'name_ar', 'name_en']);
        $suppliers = Supplier::query()->where('status', 'active')->where(function ($query) use ($company): void { $query->whereNull('supplier_group_id')->orWhereHas('supplierGroup', fn ($groups) => $groups->where('company_id', $company->id)); })->orderBy('code')->get(['code', 'name_ar', 'name_en']);
        $filters = ProductOptionGroup::query()->with(['values' => fn ($query) => $query->where('status', 'active')->orderBy('code')])->where('status', 'active')->orderBy('code')->get()->flatMap(fn ($group) => $group->values->map(fn ($value): array => [$group->code.':'.$value->code, $group->name_ar, $value->name_ar, $value->name_en]));
        $references = [
            'Categories' => $this->referenceRows(['code', 'name_ar', 'name_en'], $categories),
            'Brands' => $this->referenceRows(['code', 'name_ar', 'name_en'], $brands),
            'Suppliers' => $this->referenceRows(['code', 'name_ar', 'name_en'], $suppliers),
            'Product Filters' => [['filter_code', 'group_ar', 'value_ar', 'value_en'], ...$filters->all()],
        ];
        $validations = $staged
            ? [8 => ['sheet' => 'Categories', 'last_row' => $categories->count() + 1], 9 => ['sheet' => 'Brands', 'last_row' => $brands->count() + 1], 19 => ['sheet' => 'Suppliers', 'last_row' => $suppliers->count() + 1]]
            : [2 => ['sheet' => 'Suppliers', 'last_row' => $suppliers->count() + 1], 8 => ['sheet' => 'Categories', 'last_row' => $categories->count() + 1], 9 => ['sheet' => 'Brands', 'last_row' => $brands->count() + 1]];
        $formats = $staged ? [20 => '0.0000', 21 => '0.0000'] : [6 => '0.0000', 7 => '0.0000'];
        return $this->documents->importTemplate($staged ? 'products-import-template.xlsx' : 'product-card-import-template.xlsx', $staged ? 'toyjoy.products.staged.v2' : 'toyjoy.product-card.v2', $headers, [], [
            ['ar' => 'أدخل بيانات حقيقية فقط. استخدم الأكواد الثابتة من أوراق المراجع الحالية.', 'en' => 'Enter genuine data only. Use stable codes from the current reference sheets.'],
            ['ar' => 'لا تغيّر رؤوس الأعمدة؛ الصفوف المرفوضة تعاد في تقرير قابل للتنزيل.', 'en' => 'Do not alter headers; rejected rows are returned in a downloadable report.'],
        ], $references, $staged ? [1, 2, 8] : [0, 3, 4, 6, 7, 8, 11, 12, 26], array_keys($validations), $formats, $company->code.' — '.$company->name_ar, $validations);
    }

    public function supplier(User $actor, mixed $companyId = null): BinaryFileResponse
    {
        $company = $this->companies->resolve($actor, $companyId);
        $groups = SupplierGroup::query()->forCompany($company->id)->where('status', 'active')->orderBy('code')->get(['code', 'name_ar', 'name_en']);
        $methods = collect(SupplierSettlementMethod::options())->map(fn (string $label, string $value): array => [$value, $label]);
        return $this->documents->importTemplate('supplier-import-template.xlsx', 'toyjoy.suppliers.v2', StageSupplierImportAction::templateHeaders(), [], [
            ['ar' => 'استخدم كود مجموعة المورد وطريقة التسوية من أوراق المراجع المصرح بها.', 'en' => 'Use supplier-group and settlement codes from the authorized reference sheets.'],
            ['ar' => 'القيم المدعومة للتسوية: cash, cheques, installments, trust_deposits, other.', 'en' => 'Supported settlement values: cash, cheques, installments, trust_deposits, other.'],
        ], ['Supplier Groups' => $this->referenceRows(['code', 'name_ar', 'name_en'], $groups), 'Settlement Methods' => [['value', 'label'], ...$methods->all()]], [0, 1, 2, 8, 11], [8, 12], [], $company->code.' — '.$company->name_ar, [8 => ['sheet' => 'Settlement Methods', 'last_row' => $methods->count() + 1], 12 => ['sheet' => 'Supplier Groups', 'last_row' => $groups->count() + 1]]);
    }

    public function customers(User $actor, mixed $companyId = null): BinaryFileResponse
    {
        $company = $this->companies->resolve($actor, $companyId);
        $groups = CustomerGroup::query()->forCompany($company->id)->where('status', 'active')->orderBy('code')->get(['code', 'name_ar', 'name_en']);
        $governorates = Governorate::query()->where('status', 'active')->orderBy('code')->get(['code', 'name_ar', 'name_en']);
        $cities = City::query()->visibleToCompany($company->id)->where('status', 'active')->with('governorate:id,code')->orderBy('code')->get(['code', 'governorate_id', 'name_ar', 'name_en']);
        $cityRows = [['code', 'governorate_code', 'name_ar', 'name_en'], ...$cities->map(fn (City $city): array => [$city->code, $city->governorate?->code ?? '', $city->name_ar, $city->name_en])->all()];
        return $this->documents->importTemplate('customers-import-template.xlsx', 'toyjoy.customers.v2', StageCustomerImportAction::FIELDS, [], [
            ['ar' => 'استخدم أكواد مجموعة العميل والمحافظة والمدينة من أوراق المراجع الحالية.', 'en' => 'Use customer-group, governorate, and city codes from the current reference sheets.'],
            ['ar' => 'كل صف مرفوض يظهر في تقرير أخطاء قابل للتنزيل ولا يمنع مراجعة الصفوف الصحيحة.', 'en' => 'Each rejected row appears in a downloadable error workbook without hiding valid-row review.'],
        ], ['Customer Groups' => $this->referenceRows(['code', 'name_ar', 'name_en'], $groups), 'Governorates' => $this->referenceRows(['code', 'name_ar', 'name_en'], $governorates), 'Cities' => $cityRows], [0, 1, 4], [7, 8, 9], [], $company->code.' — '.$company->name_ar, [7 => ['sheet' => 'Customer Groups', 'last_row' => $groups->count() + 1], 8 => ['sheet' => 'Governorates', 'last_row' => $governorates->count() + 1], 9 => ['sheet' => 'Cities', 'last_row' => $cities->count() + 1]]);
    }

    public function customerGroups(User $actor, mixed $companyId = null): BinaryFileResponse
    {
        $company = $this->companies->resolve($actor, $companyId);
        $groups = CustomerGroup::query()->forCompany($company->id)->with('parent:id,code')->orderBy('code')->get(['id', 'code', 'parent_id', 'name_ar', 'name_en', 'sort_order', 'status']);
        $existing = [['code', 'parent_code', 'name_ar', 'name_en', 'sort_order', 'status'], ...$groups->map(fn (CustomerGroup $group): array => [$group->code, $group->parent?->code ?? '', $group->name_ar, $group->name_en, $group->sort_order, $group->status])->all()];
        return $this->documents->importTemplate('customer-groups-import-template.xlsx', 'toyjoy.customer-groups.v2', ImportCustomerGroups::HEADERS, [], [['ar' => 'يجب أن يسبق الصف الأب أبناءه وأن يستخدم parent_code الكود الثابت.', 'en' => 'Parent rows must precede children and parent_code must use the stable code.']], ['Existing Groups' => $existing], [0, 2, 3, 5], [], [4 => '0'], $company->code.' — '.$company->name_ar);
    }

    public function catalogReference(User $actor, string $type, mixed $companyId = null): BinaryFileResponse
    {
        $company = $this->companies->resolve($actor, $companyId);
        $model = match ($type) { 'category' => Category::class, 'brand' => Brand::class, 'age' => AgeLabel::class, 'character' => Character::class, 'colour' => Colour::class, 'gender' => Gender::class, default => null };
        abort_unless($model !== null, 404);
        $headers = StageCatalogReferenceImportAction::templateHeaders($type);
        $records = $model::query()->when($type === 'category', fn ($query) => $query->with('parent:id,code'))->orderBy('code')->get();
        $existing = $records->map(fn ($record): array => array_map(fn (string $header): mixed => $header === 'parent_code' ? ($record->parent?->code ?? '') : ($record->{$header} ?? ''), $headers));
        return $this->documents->importTemplate($type.'-import-template.xlsx', 'toyjoy.catalog-'.$type.'.v2', $headers, [], [['ar' => 'الأكواد الثابتة دائمة. استخدم ورقة المراجع الحالية وتجنب تكرار أي كود.', 'en' => 'Stable codes are permanent. Use the current reference sheet and do not duplicate codes.']], ['Existing References' => [$headers, ...$existing->all()]], array_values(array_filter(array_map(fn (string $header, int $index): ?int => in_array($header, ['code', 'name_ar', 'name_en', 'status'], true) ? $index : null, $headers, array_keys($headers)), fn ($index) => $index !== null)), [], [], $company->code.' — '.$company->name_ar);
    }

    /** @param list<string> $headers @param iterable<object> $records @return list<array<int,mixed>> */
    private function referenceRows(array $headers, iterable $records): array
    {
        $rows = [$headers];
        foreach ($records as $record) $rows[] = array_map(fn (string $field): mixed => $record->{$field}, $headers);
        return $rows;
    }
}
