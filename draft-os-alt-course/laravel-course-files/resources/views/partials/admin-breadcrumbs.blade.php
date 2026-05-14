@php
    /** @var list<array{label: string, url: ?string}> $adminBreadcrumbs */
    $crumbs = $adminBreadcrumbs ?? [];
@endphp
@if (count($crumbs) > 0)
    <nav class="breadcrumb" aria-label="Хлебные крошки">
        @foreach ($crumbs as $i => $c)
            @if ($i > 0)
                <span class="breadcrumb-sep" aria-hidden="true">›</span>
            @endif
            @if ($c['url'] !== null)
                <a href="{{ $c['url'] }}">{{ $c['label'] }}</a>
            @else
                <span class="breadcrumb-current">{{ $c['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
