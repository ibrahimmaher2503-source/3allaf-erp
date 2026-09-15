<?php

use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Backup\BackupDestination\BackupDestination;

new #[Title('Backup & Restore')] class extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $destinations = [];

    public bool $verifyBackup = false;

    public bool $encrypted = false;

    public function mount(): void
    {
        Gate::authorize('audit_logs.view');
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        Gate::authorize('audit_logs.view');
        $name = (string) config('backup.backup.name', config('app.name'));
        $disks = array_values(array_filter((array) config('backup.backup.destination.disks', []), static fn (mixed $disk): bool => is_string($disk)));
        $this->verifyBackup = (bool) config('backup.backup.verify_backup');
        $this->encrypted = filled(config('backup.backup.password'));
        $this->destinations = array_map(function (string $disk) use ($name): array {
            $destination = BackupDestination::create($disk, $name);
            $newest = $destination->newestBackup();

            return [
                'disk' => $disk,
                'reachable' => $destination->isReachable(),
                'backup_count' => $destination->backups()->count(),
                'newest' => $newest?->date()?->toIso8601String(),
                'size_bytes' => $destination->usedStorage(),
                'connection_error' => $destination->connectionError() === null ? null : __('Unavailable'),
            ];
        }, $disks);
    }

    public function render(): mixed
    {
        return view('platform.system.backups', [
            'history' => AuditLog::query()->whereIn('event', ['backup_started', 'backup_completed', 'backup_failed', 'restore_verified', 'restore_failed'])->latest('id')->limit(20)->get(),
        ]);
    }
}; ?>

<x-app.page :title="__('Backup & Restore')" :description="__('Review backup readiness and follow the controlled recovery workflow. No backup or restore success is implied by this screen.')" max-width="7xl" class="space-y-6" data-guide="backups-header">
    <x-slot:actions>
        <flux:button icon="arrow-path" wire:click="refreshStatus" wire:loading.attr="disabled" wire:target="refreshStatus">{{ __('Refresh status') }}</flux:button>
    </x-slot:actions>

    @if (! $encrypted)
        <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('Encryption warning') }}">{{ __('Backup encryption is not configured. Backup creation and restore verification remain blocked until an owner-configured encrypted destination is available.') }}</flux:callout>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <flux:card class="space-y-2"><flux:heading size="sm">{{ __('Integrity verification') }}</flux:heading><flux:badge color="{{ $verifyBackup ? 'green' : 'amber' }}">{{ $verifyBackup ? __('Configured') : __('Not configured') }}</flux:badge></flux:card>
        <flux:card class="space-y-2"><flux:heading size="sm">{{ __('Recovery control') }}</flux:heading><flux:text class="text-sm text-text-muted">{{ __('Restores are performed only into a separately identified disposable database, then verified by application boot, integrity counts, protected attachment checks, and audit history.') }}</flux:text></flux:card>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Backup destination') }}</flux:heading>
        <flux:table aria-label="{{ __('Backup destinations') }}"><flux:table.columns><flux:table.column>{{ __('Disk') }}</flux:table.column><flux:table.column>{{ __('Readiness') }}</flux:table.column><flux:table.column>{{ __('Backups') }}</flux:table.column><flux:table.column>{{ __('Newest backup') }}</flux:table.column></flux:table.columns><flux:table.rows>
            @forelse ($destinations as $destination)
                <flux:table.row><flux:table.cell>{{ $destination['disk'] }}</flux:table.cell><flux:table.cell><flux:badge color="{{ $destination['reachable'] ? 'green' : 'red' }}">{{ $destination['reachable'] ? __('Reachable') : __('Unavailable') }}</flux:badge></flux:table.cell><flux:table.cell>{{ $destination['backup_count'] }}</flux:table.cell><flux:table.cell>{{ $destination['newest'] ?? __('No backup recorded') }}</flux:table.cell></flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="4"><x-state.empty :title="__('No backup destination configured')" :description="__('An owner-configured destination is required before this workflow can create or restore a backup.')" /></flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows></flux:table>
    </flux:card>

    <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Restore workflow') }}</flux:heading><ol class="list-decimal space-y-2 ps-5 text-sm text-text-muted"><li>{{ __('Select an encrypted, integrity-verified backup.') }}</li><li>{{ __('Restore it only to the named isolated MariaDB verification database.') }}</li><li>{{ __('Boot the application against that database and reconcile configuration, audit, and protected attachments.') }}</li><li>{{ __('Record the exact commands, timestamps, counts, and outcome in the recovery evidence.') }}</li></ol></flux:card>

    <flux:card class="space-y-4"><flux:heading size="lg">{{ __('Recovery audit history') }}</flux:heading><flux:table aria-label="{{ __('Recovery audit history') }}"><flux:table.columns><flux:table.column>{{ __('When') }}</flux:table.column><flux:table.column>{{ __('Event') }}</flux:table.column><flux:table.column>{{ __('Actor') }}</flux:table.column></flux:table.columns><flux:table.rows>@forelse($history as $entry)<flux:table.row><flux:table.cell>{{ $entry->created_at }}</flux:table.cell><flux:table.cell>{{ $entry->event }}</flux:table.cell><flux:table.cell>{{ $entry->actor_name ?? __('System') }}</flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="3"><x-state.empty :title="__('No recovery activity recorded')" :description="__('No backup creation or restore verification has been claimed.')" /></flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></flux:card>
</x-app.page>
