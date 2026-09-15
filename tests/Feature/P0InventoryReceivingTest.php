<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\CreateStockTransferDraftAction;
use App\Modules\Inventory\Actions\DispatchStockTransferAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Inventory\Actions\ResolveTransferDifferenceAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferReceipt;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class P0InventoryReceivingTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_fractional_transfer_stays_in_transit_until_cumulative_receipt_is_complete_and_replay_is_safe(): void
    {
        [$transfer, $source, $destination, $product] = $this->approvedTransfer('2.500000');

        self::assertSame('0.000000', $this->balance($product, $destination)->on_hand);
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        self::assertSame('2.500000', $this->balance($product, $source)->on_hand);
        self::assertSame('0.000000', $this->balance($product, $destination)->on_hand);
        self::assertSame('2.500000', $this->balance($product, $destination)->in_transit);

        $action = app(ReceiveStockTransferAction::class);
        $first = $action->execute($transfer->id, [$transfer->lines->first()->id => '1.25'], null, null, 'receive-partial-'.$transfer->id);
        self::assertSame('in_transit', $first->status);
        self::assertSame('1.250000', $first->lines->first()->quantity_received);
        self::assertSame('1.250000', $this->balance($product, $destination)->on_hand);
        self::assertSame('1.250000', $this->balance($product, $destination)->in_transit);

        $action->execute($transfer->id, [$transfer->lines->first()->id => '1.25'], null, null, 'receive-partial-'.$transfer->id);
        self::assertSame(1, StockTransferReceipt::query()->where('stock_transfer_id', $transfer->id)->count());
        self::assertSame(1, StockMovement::query()->where('movement_type', 'transfer_receipt')->where('source_id', $transfer->id)->count());

        $final = $action->execute($transfer->id, [$transfer->lines->first()->id => '1.25'], null, null, 'receive-final-'.$transfer->id);
        self::assertSame('received', $final->status);
        self::assertSame('replenishment', $final->reason_code);
        self::assertSame('2.500000', $final->lines->first()->quantity_received);
        self::assertSame('2.500000', $this->balance($product, $destination)->on_hand);
        self::assertSame('0.000000', $this->balance($product, $destination)->in_transit);
        self::assertSame(2, StockTransferReceipt::query()->where('stock_transfer_id', $transfer->id)->count());
    }

    public function test_fractional_transfer_draft_accepts_two_and_a_half_kg(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actingAs($actor);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $source = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $destination = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $product = Product::factory()->fractional()->create();
        DB::table('document_sequences')->insert([
            'document_type' => 'stock_transfer', 'scope_type' => 'branch', 'scope_id' => $branch->id,
            'scope_key' => 'branch:'.$branch->id, 'prefix' => 'ST-', 'padding_length' => 6,
            'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $transfer = app(CreateStockTransferDraftAction::class)->execute($source->id, $destination->id, [['product_id' => $product->id, 'quantity_requested' => '2.5']], 'replenishment');

        self::assertSame('2.500000', $transfer->lines->first()->quantity_requested);
    }

    public function test_one_shot_full_receipt_rejects_over_receipt(): void
    {
        [$transfer, , $destination, $product] = $this->approvedTransfer('2.500000');
        app(DispatchStockTransferAction::class)->execute($transfer->id);

        try {
            app(ReceiveStockTransferAction::class)->execute($transfer->id, [$transfer->lines->first()->id => '2.500001'], null, null, 'receive-over-'.$transfer->id);
            self::fail('Over-receipt was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('remaining in-transit', $exception->getMessage());
        }

        $received = app(ReceiveStockTransferAction::class)->execute($transfer->id, [$transfer->lines->first()->id => '2.5'], null, null, 'receive-full-'.$transfer->id);
        self::assertSame('received', $received->status);
        self::assertSame('2.500000', $this->balance($product, $destination)->on_hand);
        self::assertSame('0.000000', $this->balance($product, $destination)->in_transit);
    }

    public function test_explicit_shortage_releases_transit_without_creating_received_stock_for_the_difference(): void
    {
        [$transfer, , $destination, $product] = $this->approvedTransfer('2.500000');
        app(DispatchStockTransferAction::class)->execute($transfer->id);

        $received = app(ReceiveStockTransferAction::class)->execute($transfer->id, [$transfer->lines->first()->id => '1.75'], 'shortage', 'Bag lost in transit', 'receive-short-'.$transfer->id);
        self::assertSame('difference_review', $received->status);
        self::assertSame('under_review', $received->difference_status);
        self::assertSame('1.750000', $received->lines->first()->quantity_received);
        self::assertSame('0.750000', $received->lines->first()->difference_quantity);
        self::assertSame('1.750000', $this->balance($product, $destination)->on_hand);
        self::assertSame('0.000000', $this->balance($product, $destination)->in_transit);

        $resolved = app(ResolveTransferDifferenceAction::class)->execute($transfer->id, 'shortage', 'Confirmed carrier shortage');
        self::assertSame('received', $resolved->status);
        self::assertSame('resolved', $resolved->difference_status);
        self::assertSame('1.750000', $this->balance($product, $destination)->on_hand);
    }

    public function test_destination_scope_is_required_to_receive(): void
    {
        [$transfer] = $this->approvedTransfer('1.000000');
        app(DispatchStockTransferAction::class)->execute($transfer->id);
        $outsider = User::factory()->create(['status' => 'active', 'is_super_admin' => false]);
        $permission = Permission::query()->firstOrCreate(['code' => 'transfers.receive'], ['module' => 'inventory', 'action' => 'receive', 'sensitivity' => 'high', 'status' => 'active']);
        $role = Role::query()->create(['code' => 'receiver-'.str()->random(8), 'name_ar' => 'مستلم', 'name_en' => 'Receiver', 'status' => 'active']);
        $role->permissions()->attach($permission);
        $role->users()->attach($outsider);
        $this->actingAs($outsider);

        $this->expectException(AuthorizationException::class);
        app(ReceiveStockTransferAction::class)->execute($transfer->id, [$transfer->lines->first()->id => '1'], null, null, 'unauthorized-'.$transfer->id);
    }

    /** @return array{StockTransfer, Store, Store, Product} */
    private function approvedTransfer(string $quantity): array
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $this->actingAs($actor);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $source = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $destination = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $product = Product::factory()->fractional()->create();
        app(PostInventoryMovement::class)->execute($product->id, $source->id, '5', 'test_opening', '10', 'opening-'.str()->uuid());
        $this->balance($product, $destination);

        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'P03-'.str()->upper(str()->random(8)),
            'source_store_id' => $source->id,
            'destination_store_id' => $destination->id,
            'status' => 'approved',
            'reason_code' => 'replenishment',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'idempotency_key' => (string) str()->uuid(),
        ]);
        $transfer->lines()->create(['product_id' => $product->id, 'quantity_requested' => $quantity, 'unit_cost' => '10']);

        return [$transfer->fresh('lines'), $source, $destination, $product];
    }

    private function balance(Product $product, Store $store): StockBalance
    {
        return StockBalance::query()->firstOrCreate(
            ['product_id' => $product->id, 'store_id' => $store->id],
            ['on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0],
        )->fresh();
    }
}
