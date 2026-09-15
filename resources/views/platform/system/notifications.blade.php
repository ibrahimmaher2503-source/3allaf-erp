<?php

use Livewire\Attributes\{Computed, Title};
use Livewire\Component;

new #[Title('Notifications')] class extends Component
{
    public function refresh(): void
    {
        // Database notifications are loaded on the next render.
    }

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()->latest()->paginate(20);
    }
}; ?>

<x-app.page :title="__('Notifications')" :description="__('Role-scoped operational alerts appear here when a configured source delivers them.')" max-width="5xl" class="space-y-6" data-guide="notifications-header">
    <x-slot:actions><flux:button icon="arrow-path" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh">{{ __('Refresh') }}</flux:button></x-slot:actions>
    <div wire:loading.flex wire:target="refresh" role="status" aria-live="polite" class="items-center gap-2 text-sm text-text-muted"><flux:icon name="arrow-path" class="size-4 animate-spin" />{{ __('Checking notifications…') }}</div>
    <flux:card wire:loading.remove wire:target="refresh">
        @forelse ($this->notifications as $notification)
            @php($data = $notification->data)
            <a href="{{ $data['url'] ?? route('notifications.index') }}" class="block rounded-lg border border-border p-4 transition hover:bg-surface-muted/50">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <flux:heading size="sm">{{ $data['title'] ?? __('Notification') }}</flux:heading>
                        <flux:text>{{ $data['message'] ?? __('An operational alert is available.') }}</flux:text>
                        @if (filled($data['filename'] ?? null))<flux:text class="text-xs text-text-muted">{{ $data['filename'] }}</flux:text>@endif
                    </div>
                </div>
            </a>
        @empty
            <x-state.empty :title="__('No notifications')" :description="__('There are no delivered operational alerts in your current authorized scope.')" icon="bell" />
        @endforelse
    </flux:card>
</x-app.page>
