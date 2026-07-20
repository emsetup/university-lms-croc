<script>
    window.DocsSearchIndex = @json($docsSearchIndex ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
</script>
<script src="{{ asset('js/docs-search.js') }}?v={{ @filemtime(public_path('js/docs-search.js')) ?: 1 }}" defer></script>
