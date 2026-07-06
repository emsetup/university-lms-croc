@php
    /** @var string $previewUrl */
    /** @var string|null $previewLabel */
    $label = $previewLabel ?? 'Предпросмотр';
    $class = $previewClass ?? 'btn btn-ghost';
    $icon = $previewIcon ?? 'external-link';
@endphp
<a
    class="{{ $class }}"
    href="{{ $previewUrl }}"
    target="_blank"
    rel="noopener noreferrer"
    title="{{ $label }} — откроется в новой вкладке как у обучающегося"
>
    @if (! empty($previewShowIcon))
        @include('partials.ap-icon', ['name' => $icon, 'size' => 'sm'])
    @endif
    <span>{{ $label }}</span>
</a>
