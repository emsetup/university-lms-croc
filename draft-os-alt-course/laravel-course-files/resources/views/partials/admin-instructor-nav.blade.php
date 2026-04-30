{{-- Навигация администратора / преподавателя (?key= в каждой ссылке) --}}
@php
    $navKey = $navKey ?? $adminKey ?? (string) request('key', '');
    $active = $active ?? '';
@endphp
@if ($navKey !== '')
    <nav class="ai-nav" aria-label="Панель администратора курса">
        <div class="ai-nav__inner">
            <span class="ai-nav__brand">Админ курса</span>
            <div class="ai-nav__links">
                <a href="{{ route('admin.panel', ['key' => $navKey]) }}"
                   class="ai-nav__a @if ($active === 'panel') ai-nav__a--active @endif">Панель</a>
                <a href="{{ route('admin.theory.index', ['key' => $navKey]) }}"
                   class="ai-nav__a @if ($active === 'theory') ai-nav__a--active @endif">Содержимое курса</a>
                <a href="{{ route('teacher.course-report', ['key' => $navKey]) }}"
                   class="ai-nav__a @if ($active === 'learners') ai-nav__a--active @endif">Обучающиеся</a>
                <a href="{{ route('admin.certificates', ['key' => $navKey]) }}"
                   class="ai-nav__a @if ($active === 'certificates') ai-nav__a--active @endif">Сертификаты</a>
                <a href="{{ route('login') }}" class="ai-nav__a ai-nav__a--external" target="_blank" rel="noopener noreferrer">Вход обучающегося ↗</a>
            </div>
        </div>
    </nav>
    <style>
        .ai-nav {
            margin: 0 0 1.25rem;
            padding: 0.55rem 0.85rem;
            border-radius: 12px;
            border: 1px solid var(--line, #dfe8e4);
            background: linear-gradient(165deg, #f4faf7, #fff);
            box-shadow: 0 2px 12px rgba(15, 42, 30, 0.05);
        }
        .ai-nav__inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem 1rem;
            justify-content: space-between;
        }
        .ai-nav__brand {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text, #0f172a);
            letter-spacing: 0.01em;
        }
        .ai-nav__links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.5rem;
            align-items: center;
        }
        .ai-nav__a {
            display: inline-block;
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--accent, #0a7);
            border: 1px solid transparent;
        }
        .ai-nav__a:hover {
            background: rgba(10, 119, 85, 0.08);
            text-decoration: none;
        }
        .ai-nav__a--active {
            background: rgba(10, 119, 85, 0.14);
            border-color: rgba(10, 119, 85, 0.35);
            color: var(--text, #0f172a);
        }
        .ai-nav__a--external {
            color: var(--muted, #5c6b76);
            font-weight: 500;
        }
        .ai-nav__a--external:hover {
            color: var(--accent, #0a7);
        }
    </style>
@endif
