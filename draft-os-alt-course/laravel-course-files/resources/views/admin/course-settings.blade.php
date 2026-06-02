@extends('layouts.admin')

@php
    $tab = $settingsTab ?? 'moduli';
@endphp
@section('title', $tab === 'kurs' ? 'О курсе — '.$course->title : ($tab === 'sertifikat' ? 'Сертификат — '.$course->title : 'Модули курса — '.$course->title))

@section('content')
    @php
        $tp = $ap ?? ['adminCourse' => $course->slug];
        $finalEditUrl = route('admin.quiz.edit.final', $tp);
        $dockerLibraryUrl = route('admin.docker.library');
        $kursUrl = route('admin.course.settings', array_merge($tp, ['tab' => 'kurs']));
        $moduliUrl = route('admin.course.settings', $tp);
        $certUrl = route('admin.course.settings', array_merge($tp, ['tab' => 'sertifikat']));
    @endphp

    <div class="ap-course-settings-page">
        <nav class="ap-course-settings-subtabs" aria-label="Разделы настроек курса">
            <a href="{{ $moduliUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'moduli') is-active @endif">Модули</a>
            <a href="{{ $kursUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'kurs') is-active @endif">О курсе</a>
            <a href="{{ $certUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'sertifikat') is-active @endif">Сертификат</a>
        </nav>

        @if ($tab === 'kurs')
            @include('admin.partials.course-settings-meta-form', [
                'course' => $course,
                'courseStatus' => $courseStatus,
                'builtImages' => $builtImages,
                'finalQuestionCount' => $finalQuestionCount,
                'tp' => $tp,
                'dockerLibraryUrl' => $dockerLibraryUrl,
                'finalEditUrl' => $finalEditUrl,
            ])
        @elseif ($tab === 'sertifikat')
            @include('admin.partials.course-settings-certificate-form', [
                'course' => $course,
                'courseStatus' => $courseStatus,
                'tp' => $tp,
            ])
        @else
            @include('admin.partials.course-modules-workbench', [
                'course' => $course,
                'modules' => $modules,
                'ap' => $tp,
                'adminKey' => $adminKey ?? '',
            ])
        @endif
    </div>
@endsection
