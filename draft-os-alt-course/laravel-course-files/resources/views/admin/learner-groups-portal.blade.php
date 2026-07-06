@extends('layouts.admin')

@section('title', 'Группы обучающихся')

@section('content')
    <div class="ap-course-settings-page">
        @include('admin.partials.learner-groups-manager', [
            'groups' => $groups,
            'learners' => $learners,
            'groupScope' => 'portal',
        ])
    </div>
@endsection
