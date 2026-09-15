<div class="rounded-xl border border-border p-4" x-data="{ open: false }">
    <button type="button" class="min-h-11 w-full text-start font-semibold" x-on:click="open = !open">
        {{ __('Advanced print settings') }}
    </button>
    <div x-show="open" class="mt-3 grid gap-3 md:grid-cols-3">
        <flux:select name="store_id" :label="__('Selling outlet — optional')">
            <flux:select.option value="">{{ __('Use Product Card price') }}</flux:select.option>
            @foreach ($stores as $store)
                <flux:select.option value="{{ $store->id }}">
                    {{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select name="template_id" :label="__('Label template — optional')">
            <flux:select.option value="">{{ __('Automatic default') }}</flux:select.option>
            @foreach ($templates as $template)
                <flux:select.option value="{{ $template->id }}">
                    {{ str_starts_with(app()->getLocale(), 'ar') ? $template->name_ar : $template->name_en }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select name="printer_id" :label="__('Printer profile — optional')">
            <flux:select.option value="">{{ __('Automatic / browser print') }}</flux:select.option>
            @foreach ($printers as $printer)
                <flux:select.option value="{{ $printer->id }}">{{ $printer->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>
</div>
