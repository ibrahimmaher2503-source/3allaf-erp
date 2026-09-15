@props(['paginator', 'pageSizes' => [20, 50, 100]])

<div {{ $attributes->class('resource-pagination') }}>
    <p class="resource-pagination__count">
        {{ __('Showing :from–:to of :total records', [
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
            'total' => $paginator->total(),
        ]) }}
    </p>
    <div class="resource-pagination__controls">
        <form method="GET" class="flex items-center gap-2">
            @foreach (request()->except(['per_page', 'page']) as $key => $value)
                @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
            @endforeach
            <label for="page-size-{{ $paginator->getPageName() }}" class="text-xs font-semibold text-text-muted">{{ __('Rows') }}</label>
            <select id="page-size-{{ $paginator->getPageName() }}" name="per_page" class="h-11 rounded-lg border border-border bg-surface px-2 text-sm" onchange="this.form.submit()">
                @foreach ($pageSizes as $size)<option value="{{ $size }}" @selected((int) request('per_page', $paginator->perPage()) === $size)>{{ $size }}</option>@endforeach
            </select>
        </form>
        {{ $paginator->onEachSide(1)->links() }}
    </div>
</div>
