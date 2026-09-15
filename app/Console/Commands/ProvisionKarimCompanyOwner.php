<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Support\CompanyOwnerPermissionProfile;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProvisionKarimCompanyOwner extends Command
{
    private const USERNAME = 'karim';

    protected $signature = 'company-owner:provision-karim';

    protected $description = 'Root-only, interactive provisioning for the scoped Karim company-owner account';

    public function handle(CompanyOwnerPermissionProfile $profile): int
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            $this->error('This provisioning command must be run by root.');

            return self::FAILURE;
        }

        $displayName = $this->secret('Karim display name (input is hidden)');
        $email = $this->secret('Karim email address (input is hidden)');
        $companyIdentifier = $this->secret('Exact company ID or code (input is hidden)');
        $password = $this->secret('Temporary password (input is hidden)');
        $passwordConfirmation = $this->secret('Confirm temporary password (input is hidden)');

        if (! is_string($password) || ! is_string($passwordConfirmation) || ! hash_equals($password, $passwordConfirmation)) {
            $this->error('The temporary password confirmation does not match.');

            return self::FAILURE;
        }

        try {
            $validated = Validator::make([
                'display_name' => $displayName,
                'email' => $email,
                'company_identifier' => $companyIdentifier,
                'password' => $password,
            ], [
                'display_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
                'company_identifier' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'max:255', Password::defaults()],
            ])->validate();

            DB::transaction(function () use ($profile, $validated): void {
                $identifier = trim((string) $validated['company_identifier']);
                $companies = Company::query()
                    ->where(function (Builder $query) use ($identifier): void {
                        $query->where('code', $identifier);
                        if (ctype_digit($identifier)) {
                            $query->orWhereKey((int) $identifier);
                        }
                    })
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->limit(2)
                    ->get();

                if ($companies->count() !== 1) {
                    throw ValidationException::withMessages([
                        'company_identifier' => 'The company identifier must resolve to exactly one active company.',
                    ]);
                }

                $company = $companies->firstOrFail();
                $branchIds = $company->branches()->where('status', 'active')->lockForUpdate()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $storeIds = $company->stores()->where('status', 'active')->lockForUpdate()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

                if ($branchIds === [] && $storeIds === []) {
                    throw ValidationException::withMessages([
                        'company_identifier' => 'The selected company has no active branch or store locations to authorize.',
                    ]);
                }

                $email = strtolower(trim((string) $validated['email']));
                $user = User::query()->where('username', self::USERNAME)->lockForUpdate()->first();
                $emailOwner = User::query()->where('email', $email)->lockForUpdate()->first();

                if ($emailOwner !== null && ($user === null || $emailOwner->id !== $user->id)) {
                    throw ValidationException::withMessages(['email' => 'That email address belongs to another account.']);
                }

                if ($user !== null) {
                    if ($user->username !== self::USERNAME
                        || (bool) $user->getRawOriginal('is_super_admin')
                        || ! hash_equals(strtolower((string) $user->email), $email)
                        || ! $this->belongsOnlyToCompany($user, (int) $company->id)) {
                        throw ValidationException::withMessages([
                            'username' => 'The existing username cannot be safely matched to this identity and company.',
                        ]);
                    }
                } else {
                    $user = new User;
                }

                $user->forceFill([
                    'name' => trim((string) $validated['display_name']),
                    'username' => self::USERNAME,
                    'email' => $email,
                    'password' => Hash::make((string) $validated['password']),
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'is_super_admin' => false,
                ])->save();

                $role = $profile->synchronize();
                $user->roles()->sync([$role->id]);
                $user->branchScopes()->delete();
                $user->storeScopes()->delete();
                $user->branchScopes()->createMany(array_map(fn (int $id): array => ['branch_id' => $id, 'status' => 'active'], $branchIds));
                $user->storeScopes()->createMany(array_map(fn (int $id): array => ['store_id' => $id, 'status' => 'active'], $storeIds));
                DB::table('sessions')->where('user_id', $user->id)->delete();

                app(RecordAuditEvent::class)->execute(
                    category: 'authorization',
                    event: 'provision_company_owner',
                    source: $user,
                    after: [
                        'username' => self::USERNAME,
                        'company_id' => (int) $company->id,
                        'role_code' => CompanyOwnerPermissionProfile::ROLE_CODE,
                        'branch_scope_count' => count($branchIds),
                        'store_scope_count' => count($storeIds),
                        'is_super_admin' => false,
                    ],
                    metadata: ['root_provisioned' => true],
                );
            }, 3);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Provisioning failed and the transaction was rolled back. No credentials were displayed.');

            return self::FAILURE;
        } finally {
            unset($password, $passwordConfirmation, $validated);
        }

        $this->info("Company-owner account '".self::USERNAME."' is ready within the selected company scope.");
        $this->warn('This application does not currently enforce a first-login password reset; rotate the temporary password immediately through the authenticated profile workflow.');
        $this->line('No email address or plaintext password was displayed or written by this command.');

        return self::SUCCESS;
    }

    private function belongsOnlyToCompany(User $user, int $companyId): bool
    {
        $companyIds = DB::table('user_branch_scopes')
            ->join('branches', 'branches.id', '=', 'user_branch_scopes.branch_id')
            ->where('user_branch_scopes.user_id', $user->id)
            ->pluck('branches.company_id')
            ->merge(DB::table('user_store_scopes')
                ->join('stores', 'stores.id', '=', 'user_store_scopes.store_id')
                ->where('user_store_scopes.user_id', $user->id)
                ->pluck('stores.company_id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        return $companyIds->all() === [$companyId];
    }
}
