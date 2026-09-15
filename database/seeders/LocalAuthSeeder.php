<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Explicit local-development authentication accounts.
 *
 * This seeder is intentionally opt-in and must never be added to
 * DatabaseSeeder or executed outside APP_ENV=local.
 */
final class LocalAuthSeeder extends Seeder
{
    /** @var array<string, array{name: string, email: string, password: string}> */
    private const ACCOUNTS = [
        'system-administrator' => ['name' => 'Local System Administrator', 'email' => 'local.system-administrator@example.test', 'password' => 'ToyJoyLocal!Admin2026'],
        'branch-manager' => ['name' => 'Local Branch Manager', 'email' => 'local.branch-manager@example.test', 'password' => 'ToyJoyLocal!Branch2026'],
        'cashier' => ['name' => 'Local Cashier', 'email' => 'local.cashier@example.test', 'password' => 'ToyJoyLocal!Cashier2026'],
        'purchasing-officer' => ['name' => 'Local Purchasing Officer', 'email' => 'local.purchasing-officer@example.test', 'password' => 'ToyJoyLocal!Purchase2026'],
        'warehouse-manager' => ['name' => 'Local Warehouse Manager', 'email' => 'local.warehouse-manager@example.test', 'password' => 'ToyJoyLocal!Warehouse2026'],
        'pricing-officer' => ['name' => 'Local Pricing Officer', 'email' => 'local.pricing-officer@example.test', 'password' => 'ToyJoyLocal!Pricing2026'],
        'party-manager' => ['name' => 'Local Party Manager', 'email' => 'local.party-manager@example.test', 'password' => 'ToyJoyLocal!Party2026'],
        'stock-counter' => ['name' => 'Local Stock Counter', 'email' => 'local.stock-counter@example.test', 'password' => 'ToyJoyLocal!Stock2026'],
        'accountant-reviewer' => ['name' => 'Local Accountant Reviewer', 'email' => 'local.accountant-reviewer@example.test', 'password' => 'ToyJoyLocal!Accounts2026'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('LocalAuthSeeder may only run when APP_ENV=local.');
        }

        $administrator = self::ACCOUNTS['system-administrator'];

        $this->call(ProductionSeeder::class);

        DB::transaction(function (): void {
            foreach (self::ACCOUNTS as $roleCode => $account) {
                $username = 'local.'.$roleCode;
                $user = User::query()
                    ->where('username', $username)
                    ->orWhere('email', $account['email'])
                    ->first();

                if ($user !== null && ($user->username !== $username || $user->email !== $account['email'])) {
                    throw new LogicException("Local auth account '$username' conflicts with an existing username or email.");
                }

                $user ??= new User(['username' => $username]);

                $user->forceFill([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => $account['password'],
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'is_super_admin' => $roleCode === 'system-administrator',
                ])->save();

                $user->roles()->sync([
                    Role::query()->where('code', $roleCode)->where('status', 'active')->firstOrFail()->id,
                ]);
            }
        });
    }
}
