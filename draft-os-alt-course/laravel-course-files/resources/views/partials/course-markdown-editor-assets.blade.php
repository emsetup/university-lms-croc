@php
    $cmdeCourseId = (int) ($cmdeCourseId ?? session('admin_course_id') ?? 0);
    $cmdePreviewUrl = $cmdePreviewUrl ?? route('admin.theory.markdown-preview', $ap ?? \App\Support\AdminNavigation::adminCourseRouteParams());
@endphp
<link rel="stylesheet" href="{{ asset('vendor/easymde/2.18.0/easymde.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/course-markdown-editor.css') }}">
<script src="{{ asset('vendor/easymde/2.18.0/easymde.min.js') }}"></script>
<script src="{{ asset('js/course-markdown-editor.js') }}"></script>
<script>
    window.CourseMarkdownEditorPage = {
        previewUrl: @json($cmdePreviewUrl),
        csrf: @json(csrf_token()),
        courseId: {{ $cmdeCourseId }}
    };
</script>
