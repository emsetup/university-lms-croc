{{-- Облегчённая оболочка для превью в iframe: без шапки, крошек и запросов по курсу. --}}
<!DOCTYPE html>
<html class="ap-html" lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Просмотр — админка')</title>
    <link rel="icon" type="image/png" href="{{ asset('croc-app-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/course.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-panel.css') }}">
    <link rel="stylesheet" href="{{ asset('static/admin/admin.css') }}">
    <style>
        .admin-preview-iframe-body { margin: 0; background: #fff; min-height: 100vh; }
        .admin-preview-iframe-main { padding: 12px 16px 28px; box-sizing: border-box; }
    </style>
    @stack('styles')
</head>
<body class="admin-preview-iframe-body">
    <main class="admin-preview-iframe-main" id="admin-preview-main">
        @yield('content')
    </main>
</body>
</html>
