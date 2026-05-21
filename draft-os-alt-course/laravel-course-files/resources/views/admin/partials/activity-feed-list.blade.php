<ul class="ap-activity-feed @if(!empty($wide)) ap-activity-feed--wide @endif"
    @if(!empty($feedId)) id="{{ $feedId }}" @endif
    aria-label="{{ $ariaLabel ?? 'События' }}"
    @if(!empty($feedRole)) data-ap-activity-list @endif>
    @foreach ($items as $ev)
        @php
            $kind = $ev['kind'] ?? '';
            $kindLabel = $ev['kind_label'] ?? ($kind !== '' ? $kind : '');
        @endphp
        <li class="ap-activity-feed__item" data-ap-activity-id="{{ $ev['id'] ?? '' }}">
            <span class="ap-activity-feed__dot @if(!empty($ev['active_today'])) ap-activity-feed__dot--live @endif"
                  aria-hidden="true"></span>
            <div class="ap-activity-feed__body">
                <div class="ap-activity-feed__top">
                    @if ($kindLabel !== '')
                        <span class="ap-activity-kind ap-activity-kind--{{ $kind }}" title="{{ $kindLabel }}">{{ $kindLabel }}</span>
                    @endif
                    <p class="ap-activity-feed__text">
                        <span class="ap-activity-feed__email">{{ ($ev['email'] ?? '') !== '' ? $ev['email'] : '—' }}</span>
                        — {{ $ev['text'] }}
                    </p>
                </div>
                <time class="ap-activity-feed__time"
                      datetime="{{ $ev['at']->toIso8601String() }}">
                    {{ $ev['at']->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                </time>
            </div>
        </li>
    @endforeach
</ul>
