<link rel="stylesheet" href="{{ asset('css/scroll-to-top.css') }}?v={{ @filemtime(public_path('css/scroll-to-top.css')) ?: 1 }}">

<button type="button"
        class="portal-scroll-top"
        id="portal-scroll-top"
        aria-label="Наверх"
        title="Наверх">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 19V5"/>
        <path d="m5 12 7-7 7 7"/>
    </svg>
</button>
<script src="{{ asset('js/scroll-to-top.js') }}?v={{ @filemtime(public_path('js/scroll-to-top.js')) ?: 1 }}" defer></script>
