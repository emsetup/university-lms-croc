@php
    $v = (int) ($variant ?? 0);
@endphp
<span class="portal-slot-card__icon" aria-hidden="true">
    @switch($v)
        @case(0)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l1.4 4.2L18 12l-4.6 1.4L12 22l-1.4-4.6L6 12l4.6-1.4L12 2z"/>
            </svg>
            @break
        @case(1)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 5a2 2 0 0 1 2-2h6v16H4a2 2 0 0 1-2-2V5z"/>
                <path d="M22 5a2 2 0 0 0-2-2h-6v16h6a2 2 0 0 0 2-2V5z"/>
                <path d="M12 3v18"/>
                <path d="M8 8h4M8 12h3M16 8h-3M16 12h-2"/>
            </svg>
            @break
        @case(2)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 6l3 6-3 2-3-2 3-6z"/>
            </svg>
            @break
        @case(3)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
            @break
        @case(4)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 17l6-6 4 4 7-7"/>
                <path d="M14 8h6v6"/>
            </svg>
            @break
        @case(5)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="16" rx="2"/>
                <path d="M16 3v4M8 3v4M3 11h18"/>
            </svg>
            @break
        @case(6)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7v5l3 2"/>
            </svg>
            @break
        @case(7)
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 8v8M8 12h8"/>
            </svg>
            @break
        @default
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 17.9 5.7 21l2.3-7-6-4.6h7.6L12 2z"/>
            </svg>
    @endswitch
</span>
