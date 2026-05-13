@extends('layouts.course')

@section('title', $mode === 'create' ? 'Новый сотрудник' : 'Редактирование сотрудника')

@section('content')
    <div class="card" style="max-width: 640px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['active' => 'staff'])

        <h1 style="margin-top:0">{{ $mode === 'create' ? 'Добавить сотрудника' : 'Редактировать сотрудника' }}</h1>
        <p class="muted" style="margin:0 0 1rem;line-height:1.5">
            Для инструктора и тестировщика укажите один или несколько курсов. Остальные роли действуют на весь портал.
        </p>

        <form method="post" action="{{ $mode === 'create' ? route('admin.staff.store') : route('admin.staff.update', ['staff' => $staff->id]) }}" style="display:grid;gap:0.85rem">
            @csrf
            <label>
                <span class="muted small">Корпоративный email</span><br>
                <input class="input" type="email" name="email" required value="{{ old('email', $staff?->learner?->email) }}" style="width:100%;max-width:28rem">
            </label>

            <label>
                <span class="muted small">Роль</span><br>
                <select class="input" name="role" id="staff-role" style="width:100%;max-width:28rem">
                    @php $r = old('role', $staff?->role); @endphp
                    <option value="portal_admin" @selected($r === 'portal_admin')>Администратор портала</option>
                    <option value="course_moderator" @selected($r === 'course_moderator')>Модератор курсов</option>
                    <option value="instructor" @selected($r === 'instructor')>Инструктор</option>
                    <option value="course_tester" @selected($r === 'course_tester')>Тестировщик курса</option>
                </select>
            </label>

            <div id="staff-courses-wrap" style="display:none">
                <span class="muted small">Курсы</span>
                <div style="margin-top:0.35rem;display:grid;gap:0.35rem;max-height:14rem;overflow:auto;border:1px solid var(--line,#e5e7eb);border-radius:8px;padding:0.5rem">
                    @php $picked = collect(old('course_ids', $staff?->courses->pluck('id')->all() ?? []))->map(fn ($v) => (int) $v)->all(); @endphp
                    @foreach ($courses as $c)
                        <label style="display:flex;gap:0.5rem;align-items:center;font-size:0.92rem">
                            <input type="checkbox" name="course_ids[]" value="{{ (int) $c->id }}" @checked(in_array((int) $c->id, $picked, true))>
                            <span>{{ $c->title }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.5rem">
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Создать' : 'Сохранить' }}</button>
                <a class="btn btn-ghost" href="{{ route('admin.staff.index') }}">Отмена</a>
            </div>
        </form>

        <script>
            (function () {
                var role = document.getElementById('staff-role');
                var wrap = document.getElementById('staff-courses-wrap');
                if (!role || !wrap) return;
                function sync() {
                    var v = role.value;
                    wrap.style.display = (v === 'instructor' || v === 'course_tester') ? 'block' : 'none';
                }
                role.addEventListener('change', sync);
                sync();
            })();
        </script>
    </div>
@endsection
