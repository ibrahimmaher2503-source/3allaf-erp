<?php

use App\Modules\Platform\Actions\GetPlatformStatus;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System Health')] class extends Component {
    /**
     * @var array<string, mixed>
     */
    public array $status = [];

    /**
     * Mount the component.
     */
    public function mount(GetPlatformStatus $getPlatformStatus): void
    {
        Gate::authorize('view-platform-status');

        $this->refreshStatus($getPlatformStatus);
    }

    /**
     * Refresh the platform status indicators.
     */
    public function refreshStatus(GetPlatformStatus $getPlatformStatus): void
    {
        Gate::authorize('view-platform-status');

        $this->status = $getPlatformStatus->execute();

        Flux::toast(variant: 'success', text: __('System health refreshed.'));
    }
}; ?>

<x-app.page
    :title="__('System Health & Monitoring')"
    :description="__('Local platform baseline status, request context, and component indicators.')"
    max-width="7xl"
    class="space-y-6"
    data-guide="health-header"
>
    <x-slot:actions>
        <flux:badge size="sm" variant="outline" icon="finger-print" class="max-w-full whitespace-normal break-all text-start font-mono">
            {{ $status['request_id'] ?? 'REQ-LOCAL' }}
        </flux:badge>

        <flux:button icon="arrow-path" size="sm" wire:click="refreshStatus" variant="subtle" data-guide="health-refresh-action">
            {{ __('Refresh') }}
        </flux:button>
    </x-slot:actions>

    <div data-guide="health-banner">
        @if (($status['status'] ?? 'healthy') === 'healthy')
            <flux:callout variant="success" icon="check-circle" title="{{ __('System Operational') }}">
                {{ __('All baseline local platform services are healthy and responsive.') }}
            </flux:callout>
        @elseif (($status['status'] ?? '') === 'degraded')
            <flux:callout variant="warning" icon="exclamation-triangle" title="{{ __('System Degraded') }}">
                {{ __('One or more non-critical local services are reporting a degraded state.') }}
            </flux:callout>
        @else
            <flux:callout variant="danger" icon="x-circle" title="{{ __('System Critical') }}">
                {{ __('Critical local platform services are currently down or unreachable.') }}
            </flux:callout>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" data-guide="health-grid">
        <!-- Database Status Card -->
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:subheading class="text-xs font-semibold uppercase tracking-wider">{{ __('Database') }}</flux:subheading>
                @if (($status['components']['database']['status'] ?? '') === 'healthy')
                    <flux:badge size="sm" color="green">{{ __('Healthy') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="red">{{ __('Down') }}</flux:badge>
                @endif
            </div>

            <flux:heading size="lg">{{ strtoupper($status['components']['database']['driver'] ?? 'MySQL') }}</flux:heading>
            <flux:text class="text-xs">{{ $status['components']['database']['message'] ?? '' }}</flux:text>
        </flux:card>

        <!-- Storage Status Card -->
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:subheading class="text-xs font-semibold uppercase tracking-wider">{{ __('Storage') }}</flux:subheading>
                @if (($status['components']['storage']['status'] ?? '') === 'healthy')
                    <flux:badge size="sm" color="green">{{ __('Healthy') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="yellow">{{ __('Degraded') }}</flux:badge>
                @endif
            </div>

            <flux:heading size="lg">{{ __('Local Filesystem') }}</flux:heading>
            <flux:text class="text-xs">{{ $status['components']['storage']['message'] ?? '' }}</flux:text>
        </flux:card>

        <!-- Cache Status Card -->
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:subheading class="text-xs font-semibold uppercase tracking-wider">{{ __('Cache') }}</flux:subheading>
                @if (($status['components']['cache']['status'] ?? '') === 'healthy')
                    <flux:badge size="sm" color="green">{{ __('Healthy') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="yellow">{{ __('Degraded') }}</flux:badge>
                @endif
            </div>

            <flux:heading size="lg">{{ strtoupper($status['components']['cache']['driver'] ?? 'File') }}</flux:heading>
            <flux:text class="text-xs">{{ $status['components']['cache']['message'] ?? '' }}</flux:text>
        </flux:card>

        <!-- Environment Card -->
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:subheading class="text-xs font-semibold uppercase tracking-wider">{{ __('Environment') }}</flux:subheading>
                <flux:badge size="sm" color="zinc">{{ $status['application']['environment'] ?? 'local' }}</flux:badge>
            </div>

            <flux:heading size="lg">{{ $status['application']['name'] ?? 'TOY & JOY' }}</flux:heading>
            <flux:text class="text-xs">PHP {{ $status['application']['php_version'] ?? '' }} / Laravel {{ $status['application']['laravel_version'] ?? '' }}</flux:text>
        </flux:card>
    </div>

    <!-- Overview Table -->
    <flux:card class="space-y-4" data-guide="health-table">
        <flux:heading size="lg">{{ __('Platform Overview & Metadata') }}</flux:heading>

        <div class="max-w-full overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Property') }}</flux:table.column>
                    <flux:table.column>{{ __('Value') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ __('Application Name') }}</flux:table.cell>
                        <flux:table.cell>{{ $status['application']['name'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" color="zinc">{{ __('Active') }}</flux:badge></flux:table.cell>
                    </flux:table.row>

                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ __('Application Locale') }}</flux:table.cell>
                        <flux:table.cell>{{ $status['application']['locale'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" color="zinc">{{ __('Configured') }}</flux:badge></flux:table.cell>
                    </flux:table.row>

                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ __('Request Correlation ID') }}</flux:table.cell>
                        <flux:table.cell class="max-w-40 whitespace-normal break-all font-mono text-xs">{{ $status['request_id'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" color="zinc">{{ __('Assigned') }}</flux:badge></flux:table.cell>
                    </flux:table.row>

                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ __('Last Status Check') }}</flux:table.cell>
                        <flux:table.cell class="max-w-40 whitespace-normal break-all font-mono text-xs">{{ $status['timestamp'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" color="green">{{ __('Current') }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>
</x-app.page>
