<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierCommunicationDestination;
use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveSupplierCommunicationDestinationAction
{
    /** @param array<string, mixed> $data */
    public function execute(int $supplierId, array $data, ?int $id = null): SupplierCommunicationDestination
    {
        Gate::authorize('suppliers.edit');
        $purpose = (string) ($data['purpose'] ?? '');
        $channel = (string) ($data['channel'] ?? '');
        if (! in_array($purpose, SupplierCommunicationDestination::PURPOSES, true)) {
            throw new InvalidArgumentException(__('The selected communication purpose is invalid.'));
        }
        if (! in_array($channel, SupplierCommunicationDestination::CHANNELS, true)) {
            throw new InvalidArgumentException(__('The selected communication channel is invalid.'));
        }

        return DB::transaction(function () use ($supplierId, $data, $id, $purpose, $channel): SupplierCommunicationDestination {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            $destination = $id === null
                ? null
                : $supplier->communicationDestinations()->lockForUpdate()->findOrFail($id);
            $actorId = Auth::id();
            $value = trim((string) ($data['destination'] ?? ''));
            if ($value === '') {
                throw new InvalidArgumentException(__('A communication destination is required.'));
            }
            if ($channel === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException(__('Enter a valid email destination.'));
            }
            if (in_array($channel, ['phone', 'whatsapp'], true)) {
                $value = PhoneNormalizer::normalize($value);
            }

            $attributes = [
                'supplier_id' => $supplier->id,
                'purpose' => $purpose,
                'channel' => $channel,
                'destination' => $value,
                'label' => filled($data['label'] ?? null) ? trim((string) $data['label']) : null,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
                'updated_by' => $actorId,
            ];
            if ($attributes['is_primary']) {
                $supplier->communicationDestinations()->where('purpose', $purpose)->when($destination !== null, fn ($query) => $query->where('id', '<>', $destination->id))->update(['is_primary' => false]);
            }
            if ($destination === null) {
                $destination = $supplier->communicationDestinations()->create($attributes + ['created_by' => $actorId, 'lock_version' => 1]);
                $event = 'supplier_communication_destination_created';
                $before = null;
            } else {
                $before = $destination->only(['purpose', 'channel', 'destination', 'label', 'is_primary', 'status', 'lock_version']);
                $destination->fill($attributes + ['lock_version' => ((int) $destination->lock_version) + 1])->save();
                $event = 'supplier_communication_destination_updated';
            }

            $saved = $destination->fresh();
            app(RecordAuditEvent::class)->execute(
                category: 'supplier_master_data',
                event: $event,
                source: $saved,
                before: $before,
                after: $saved->only(['supplier_id', 'purpose', 'channel', 'destination', 'label', 'is_primary', 'status', 'lock_version']),
                metadata: ['actor_id' => $actorId],
            );

            return $saved;
        });
    }
}
