<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierContact;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveSupplierContactAction
{
    /** @param array<string, mixed> $data */
    public function execute(int $supplierId, array $data, ?int $id = null): SupplierContact
    {
        Gate::authorize('suppliers.edit');

        $role = (string) ($data['role'] ?? '');
        if (! in_array($role, SupplierContact::ROLES, true)) {
            throw new InvalidArgumentException(__('The selected supplier contact role is invalid.'));
        }

        return DB::transaction(function () use ($supplierId, $data, $id, $role): SupplierContact {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            $contact = $id === null
                ? null
                : $supplier->contacts()->lockForUpdate()->findOrFail($id);
            $actorId = Auth::id();
            $attributes = [
                'supplier_id' => $supplier->id,
                'role' => $role,
                'name' => trim((string) ($data['name'] ?? '')),
                'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
                'phone' => filled($data['phone'] ?? null) ? PhoneNormalizer::normalize((string) $data['phone']) : null,
                'mobile' => filled($data['mobile'] ?? null) ? PhoneNormalizer::normalize((string) $data['mobile']) : null,
                'whatsapp' => filled($data['whatsapp'] ?? null) ? PhoneNormalizer::normalize((string) $data['whatsapp']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
                'updated_by' => $actorId,
            ];
            if ($attributes['name'] === '') {
                throw new InvalidArgumentException(__('Supplier contact name is required.'));
            }

            if ($attributes['is_primary']) {
                $supplier->contacts()->where('role', $role)->when($contact !== null, fn ($query) => $query->where('id', '<>', $contact->id))->update(['is_primary' => false]);
            }
            if ($contact === null) {
                $contact = $supplier->contacts()->create($attributes + ['created_by' => $actorId, 'lock_version' => 1]);
                $event = 'supplier_contact_created';
                $before = null;
            } else {
                $before = $contact->only(['role', 'name', 'email', 'phone', 'mobile', 'whatsapp', 'notes', 'is_primary', 'status', 'lock_version']);
                $contact->fill($attributes + ['lock_version' => ((int) $contact->lock_version) + 1])->save();
                $event = 'supplier_contact_updated';
            }

            $saved = $contact->fresh();
            app(RecordAuditEvent::class)->execute(
                category: 'supplier_master_data',
                event: $event,
                source: $saved,
                before: $before,
                after: $saved->only(['supplier_id', 'role', 'name', 'email', 'phone', 'mobile', 'whatsapp', 'notes', 'is_primary', 'status', 'lock_version']),
                metadata: ['actor_id' => $actorId],
            );

            return $saved;
        });
    }
}
