@extends('layouts.admin')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media-library.css') }}">
@endpush

@section('title', 'Библиотека картинок')

@section('content')
    <div
        id="ap-media-page"
        class="ap-page ap-media-page"
        data-ap-media-api="{{ route('admin.media.api') }}"
        data-ap-media-upload="{{ route('admin.media.upload') }}"
        data-ap-media-csrf="{{ csrf_token() }}"
        data-ap-media-course-id="{{ $courseId > 0 ? $courseId : '' }}"
    >
        <div class="ap-page-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
            <div>
                <h1 class="ap-page-title">Библиотека картинок</h1>
                <p class="ap-page-lead ap-muted">Ваши загруженные изображения и импортированные из курсов. Используйте их в теории, практике и вопросах.</p>
            </div>
        </div>

        <div class="ap-media-lib-drop" data-ap-media-drop>
            <strong>Перетащите изображения сюда</strong><br>
            <span class="small">или нажмите для выбора · JPEG, PNG, GIF, WebP · до 10 МБ</span>
            <input type="file" data-ap-media-file accept="image/jpeg,image/png,image/gif,image/webp" multiple class="ap-media-lib-file-input">
        </div>
        <div class="ap-media-lib-uploads" data-ap-media-uploads></div>
        <div class="ap-media-lib-grid" data-ap-media-grid></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/media-library.js') }}" defer></script>
    <script src="{{ asset('js/course-lightbox.js') }}" defer></script>
@endpush
