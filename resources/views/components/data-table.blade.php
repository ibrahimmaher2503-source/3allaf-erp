@props([
    'headers' => [],
    'rows' => [],
    'emptyMessage' => 'No records found.',
    'ariaLabel' => null,
])

<x-tables.table-shell :label="$ariaLabel" {{ $attributes }}>
    @if (count($rows) > 0)
        <table class="data-table min-w-full text-sm">
            <thead>
                <tr>
                    @foreach ($headers as $key => $label)
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-start">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr wire:key="{{ $row['id'] ?? $loop->index }}" class="align-top">
                        @foreach ($headers as $key => $label)
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-800 dark:text-zinc-100">
                                <x-data.value :value="$row[$key] ?? null" />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <x-state.empty :description="$emptyMessage" />
    @endif
</x-tables.table-shell>
