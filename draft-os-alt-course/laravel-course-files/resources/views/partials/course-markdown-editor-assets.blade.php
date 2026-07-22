@php
    $cmdeCourseId = (int) ($cmdeCourseId ?? session('admin_course_id') ?? 0);
    $cmdeAp = $ap ?? \App\Support\AdminNavigation::adminCourseRouteParams();
    try {
        $cmdePreviewUrl = $cmdePreviewUrl ?? route('admin.theory.markdown-preview', $cmdeAp);
    } catch (\Throwable $e) {
        $cmdePreviewUrl = $cmdePreviewUrl ?? '';
    }
    $easymdeCssV = @filemtime(public_path('vendor/easymde/2.18.0/easymde.min.css')) ?: 1;
    $easymdeJsV = @filemtime(public_path('vendor/easymde/2.18.0/easymde.min.js')) ?: 1;
    $cmdeCssV = @filemtime(public_path('css/course-markdown-editor.css')) ?: 1;
    $cmdeJsV = @filemtime(public_path('js/course-markdown-editor.js')) ?: 1;
@endphp
<link rel="stylesheet" href="{{ asset('vendor/easymde/2.18.0/easymde.min.css') }}?v={{ $easymdeCssV }}">
<link rel="stylesheet" href="{{ asset('css/course-markdown-editor.css') }}?v={{ $cmdeCssV }}">
<script src="{{ asset('vendor/easymde/2.18.0/easymde.min.js') }}?v={{ $easymdeJsV }}"></script>
<script src="{{ asset('js/course-markdown-editor.js') }}?v={{ $cmdeJsV }}"></script>
<script>
    window.CourseMarkdownEditorPage = {
        previewUrl: @json($cmdePreviewUrl),
        csrf: @json(csrf_token()),
        courseId: {{ $cmdeCourseId }}
    };
    document.dispatchEvent(new CustomEvent('cmde:ready'));
</script>
