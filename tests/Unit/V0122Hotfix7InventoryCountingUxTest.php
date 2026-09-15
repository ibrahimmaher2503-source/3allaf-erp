<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class V0122Hotfix7InventoryCountingUxTest extends TestCase
{
 private function source(string $path):string{return(string)file_get_contents(dirname(__DIR__,2).'/'.$path);}
 public function test_primary_outlet_is_not_a_pos_or_readiness_fallback():void{
  self::assertStringNotContainsString('activeSellingStoreMapping',$this->source('app/Modules/Retail/Support/PosContextResolver.php'));
  self::assertStringNotContainsString('activeSellingStoreMapping',$this->source('app/Modules/Platform/Actions/SaveCashDrawerAction.php'));
  self::assertStringNotContainsString('retailBranchesHaveOneCurrentValidSellingStoreMapping()',preg_replace('/private function retailBranchesHaveOneCurrentValidSellingStoreMapping[\s\S]*/','',$this->source('app/Modules/Platform/Support/InitialSetupStatus.php')));
 }
 public function test_counting_is_multi_location_append_only_and_idempotent():void{
  $migration=$this->source('database/migrations/2026_09_03_000106_add_inventory_count_sessions.php');$record=$this->source('app/Modules/Inventory/Actions/RecordStockCountContributionAction.php');
  foreach(['stock_count_locations','stock_count_members','stock_count_assignments','stock_count_contributions','stock_count_contribution_request_unique'] as $value)self::assertStringContainsString($value,$migration);
  self::assertStringContainsString("where('request_id',\$requestId)",$record);self::assertStringContainsString("sum('quantity')",$record);self::assertStringContainsString('reverses_contribution_id',$record);
 }
 public function test_reconciliation_is_time_aware_and_adjusts_exact_locations():void{
  $submit=$this->source('app/Modules/Inventory/Actions/SubmitStockCountAction.php');$reconcile=$this->source('app/Modules/Inventory/Actions/ReconcileStockCountAction.php');
  foreach(['completed_at','movement_after_completion','normalized_closing_quantity'] as $value)self::assertStringContainsString($value,$submit);
  self::assertStringContainsString("groupBy('store_id')",$reconcile);self::assertStringContainsString("(int)\$storeId",$reconcile);
 }
 public function test_every_transfer_write_stage_enforces_open_count_lock():void{
  foreach(['CreateStockTransferDraftAction','UpdateStockTransferDraftAction','SubmitStockTransferAction','ApproveStockTransferAction','DispatchStockTransferAction','ReceiveStockTransferAction'] as $class)self::assertStringContainsString('AssertNoOpenStockCountTransfer',$this->source("app/Modules/Inventory/Actions/{$class}.php"));
 }
 public function test_human_names_navigation_and_bounded_search_are_present():void{
  self::assertStringContainsString('Warehouses & Sales Outlets',$this->source('app/Modules/Platform/Support/InitialSetupStepRegistry.php'));self::assertStringContainsString('HumanName::name',$this->source('resources/views/inventory/index.blade.php'));self::assertStringContainsString("limit(20)",$this->source('routes/inventory.php'));self::assertStringContainsString("0.1.22-hotfix41",$this->source('app/Support/ApplicationVersion.php'));
 }
}
