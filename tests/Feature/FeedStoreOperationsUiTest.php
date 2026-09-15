<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryBatch;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserStoreScope;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

final class FeedStoreOperationsUiTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_authorized_operator_can_open_page_and_create_cash_account_and_batch(): void
    {
        [$actor, $company] = $this->fixtures();
        $product = Product::factory()->feed()->withBatchTracking()->create();

        $this->actingAs($actor)->get(route('feed-store.operations'))
            ->assertOk()
            ->assertSee('Feed store operations')
            ->assertSee(route('feed-store.customer-receipts.store'), false)
            ->assertSee(route('feed-store.supplier-payments.store'), false)
            ->assertSee(route('feed-store.expenses.store'), false);

        $this->actingAs($actor)->post(route('feed-store.cash-accounts.store'), [
            'company_id' => $company->id,
            'code' => 'SAFE-01',
            'name_ar' => 'خزينة آمنة',
            'name_en' => 'Safe treasury',
            'type' => 'cash',
            'currency_code' => 'egp',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($actor)->post(route('feed-store.batches.store'), [
            'product_id' => $product->id,
            'batch_number' => 'BATCH-UI-01',
            'production_date' => '2026-09-01',
            'expiry_date' => '2027-09-01',
        ])->assertRedirect()->assertSessionHas('success');

        self::assertTrue(CashAccount::query()->where('company_id', $company->id)->where('code', 'SAFE-01')->where('currency_code', 'EGP')->exists());
        self::assertTrue(InventoryBatch::query()->where('product_id', $product->id)->where('batch_number', 'BATCH-UI-01')->exists());
    }

    public function test_operator_without_any_feed_store_action_permission_is_denied(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);

        $this->actingAs($actor)->get(route('feed-store.operations'))->assertForbidden();
    }

    public function test_customer_operator_can_use_the_shared_operations_entry(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $role = Role::query()->create(['code' => 'feed-customer-'.str()->random(8), 'name_ar' => 'تحصيل العملاء', 'name_en' => 'Customer collection', 'status' => 'active']);
        $permission = Permission::query()->firstOrCreate(['code' => 'customers.edit'], ['module' => 'customers', 'action' => 'edit', 'sensitivity' => 'normal', 'status' => 'active']);
        $role->permissions()->attach($permission);
        $actor->roles()->attach($role);
        UserStoreScope::query()->create(['user_id' => $actor->id, 'store_id' => $store->id, 'status' => 'active']);

        self::assertTrue($actor->can('access-feed-store-operations'));
        $this->actingAs($actor)->get(route('feed-store.operations'))->assertOk();
        self::assertSame('access-feed-store-operations', collect(config('navigation'))->flatMap(fn (array $section) => $section['items'])->firstWhere('route', 'feed-store.operations')['permission']);
    }

    /** @return array{User, Company} */
    private function fixtures(): array
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);

        return [$actor, $company];
    }
}
