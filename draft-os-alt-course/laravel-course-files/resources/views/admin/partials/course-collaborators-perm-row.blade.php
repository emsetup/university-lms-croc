@php
    $rowClass = ! empty($isSection) ? 'ap-collab-perm-row ap-collab-perm-row--section' : 'ap-collab-perm-row';
@endphp
<div class="{{ $rowClass }}">
    <div class="ap-collab-perm-row__label">
        @if (! empty($icon))
            <span class="ap-collab-perm-row__icon" aria-hidden="true">@include('partials.ap-icon', ['name' => $icon, 'size' => 'sm'])</span>
        @endif
        <div>
            <span class="ap-collab-perm-row__name">{{ $label }}</span>
            @if (! empty($hint))
                <span class="ap-collab-perm-row__hint ap-muted">{{ $hint }}</span>
            @endif
        </div>
    </div>
    <select class="ap-collab-grant-select ap-collab-grant-select--hidden" data-resource-type="{{ $resourceType }}" data-resource-id="{{ $resourceId }}" tabindex="-1" aria-hidden="true">
        @foreach ($options as $opt)
            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
        @endforeach
    </select>
    <div class="ap-collab-perm-btns" role="group" aria-label="{{ $label }}">
        @foreach ($options as $opt)
            <button type="button"
                    class="ap-collab-perm-btn"
                    data-value="{{ $opt['value'] }}"
                    title="{{ $opt['label'] }}">{{ $opt['label'] }}</button>
        @endforeach
    </div>
</div>
