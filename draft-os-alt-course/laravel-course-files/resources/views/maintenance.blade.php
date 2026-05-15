<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>КРОК · Трек знаний — Обновление</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      min-height: 100vh;
      background: #f4f6f8;
      font-family: -apple-system, BlinkMacSystemFont,
                   'Segoe UI', sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
      color: #111827;
    }

    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 20px;
      padding: 48px 56px;
      max-width: 520px;
      width: 100%;
      text-align: center;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
      animation: fadeUp 0.4s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .logo {
      display: flex;
      align-items: baseline;
      justify-content: center;
      gap: 10px;
      margin-bottom: 40px;
    }
    .logo-name {
      font-size: 28px;
      font-weight: 800;
      color: #00b956;
      letter-spacing: -0.5px;
    }
    .logo-sub {
      font-size: 13px;
      color: #9ca3af;
      font-weight: 400;
    }

    .icon-wrap {
      width: 80px;
      height: 80px;
      background: #f0fdf4;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 28px;
    }
    .icon-wrap svg {
      width: 40px;
      height: 40px;
      color: #00b956;
      animation: spin 3s linear infinite;
    }
    @keyframes spin {
      0%   { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    h1 {
      font-size: 22px;
      font-weight: 700;
      color: #111827;
      margin-bottom: 12px;
      line-height: 1.3;
    }

    .description {
      font-size: 15px;
      color: #6b7280;
      line-height: 1.6;
      margin-bottom: 32px;
    }

    .status-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 20px;
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      color: #16a34a;
      margin-bottom: 28px;
    }
    .status-dot {
      width: 8px;
      height: 8px;
      background: #00b956;
      border-radius: 50%;
      animation: pulse 1.5s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%       { opacity: 0.5; transform: scale(0.8); }
    }

    .contact {
      font-size: 13px;
      color: #9ca3af;
      line-height: 1.5;
    }
    .contact a {
      color: #00b956;
      text-decoration: none;
      font-weight: 500;
    }
    .contact a:hover { text-decoration: underline; }

    .progress-wrap {
      margin: 24px 0;
    }
    .progress-label {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      color: #9ca3af;
      margin-bottom: 8px;
    }
    .progress-track {
      height: 6px;
      background: #f3f4f6;
      border-radius: 999px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      width: 70%;
      background: linear-gradient(90deg, #00b956, #34d399);
      border-radius: 999px;
      animation: progressAnim 2.5s ease-in-out infinite alternate;
    }
    @keyframes progressAnim {
      from { width: 60%; }
      to   { width: 80%; }
    }
  </style>
</head>
<body>
  <div class="card">

    <div class="logo">
      <span class="logo-name">КРОК</span>
      <span class="logo-sub">ТРЕК ЗНАНИЙ</span>
    </div>

    <div class="icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.5" stroke-linecap="round"
           stroke-linejoin="round" aria-hidden="true">
        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1
                 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0
                 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2
                 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2
                 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-
                 -.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2
                 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 0
                 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0
                 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2
                 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2
                 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-
                 -.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73
                 V4a2 2 0 0 0-2-2z"/>
        <circle cx="12" cy="12" r="3"/>
      </svg>
    </div>

    <h1>Портал обновляется</h1>

    <p class="description">
      Мы улучшаем образовательный портал, чтобы сделать
      обучение ещё удобнее. Скоро всё будет готово.
    </p>

    <div class="status-row">
      <div class="status-dot"></div>
      Технические работы в процессе
    </div>

    <div class="progress-wrap">
      <div class="progress-label">
        <span>Прогресс обновления</span>
        <span>Скоро</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill"></div>
      </div>
    </div>

    <p class="contact">
      Вопросы? Напишите нам:<br>
      <a href="mailto:emednikov@croc.ru">emednikov@croc.ru</a>
    </p>

  </div>
</body>
</html>
