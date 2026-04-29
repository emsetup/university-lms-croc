@extends('layouts.course')

@section('title', 'Вход - курс ОС "Альт"')

@section('content')
    @include('partials.course-audience-intro')

    <div class="card" style="max-width:520px;margin:2rem auto 0">
        <h1 style="margin-top:0">Вход по корпоративной почте</h1>
        <p class="muted">Укажите адрес в домене {{ '@'.$domain }} - прогресс и попытки сохраняются для вашей учетной записи.</p>

        @if ($errors->any())
            <div class="flash err">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label for="email">Электронная почта</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="example@croc.ru" required autocomplete="username">
            </div>
            <button type="submit" class="btn btn-primary">Продолжить</button>
        </form>
        <p class="footer-note">Данные учебного стенда. Не используйте пароли от рабочих систем.</p>
    </div>
@endsection
