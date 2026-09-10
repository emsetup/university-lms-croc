# Push-мониторинг practice.croc.ru

Стенд сам проверяет локальные страницы и шлёт heartbeat на белорусский сервер.
Коллектор алертит в **отдельный** Telegram-бот (не Zabbix), если сигналы пропали или проверки упали.

## Компоненты

| Где | Что |
|-----|-----|
| Стенд `172.26.76.216` | `portal-agent.timer` → `/usr/local/bin/portal-send-heartbeat.sh` |
| `belarus` (`update.cherryhaze.ru`) | FastAPI `:8099` + nginx `/portal-monitor/*` |

Публичный URL: `https://update.cherryhaze.ru/portal-monitor/heartbeat`

## Telegram-бот (отдельный)

1. В Telegram: [@BotFather](https://t.me/BotFather) → `/newbot` → имя вроде `practice-croc-monitor`.
2. Сохранить токен `123456:AAE…`.
3. Написать боту `/start` (из личного чата или добавить в группу).
4. Узнать `chat_id`:
   - личный чат: открыть `https://api.telegram.org/bot<TOKEN>/getUpdates` после `/start`;
   - или временно написать боту и взять `message.chat.id`.

Прописать на belarus и сразу тест-сообщение:

```bash
export TELEGRAM_BOT_TOKEN='…'
export TELEGRAM_CHAT_ID='…'
bash portal-monitor/scripts/set-telegram-bot.sh
```

## Деплой коллектора / агента

```bash
export HEARTBEAT_TOKEN='…длинный секрет…'   # можно опустить при повторном деплое — возьмётся с belarus
export TELEGRAM_BOT_TOKEN='…'
export TELEGRAM_CHAT_ID='…'

cd draft-os-alt-course
bash portal-monitor/scripts/deploy-collector-belarus.sh
STAND_SSH=emednikov@172.26.76.216 bash portal-monitor/scripts/deploy-agent-stand.sh
```

## Пороги (env коллектора `/etc/portal-monitor.env`)

- `SILENCE_SECONDS=180` — нет heartbeat → DOWN
- `WATCH_INTERVAL=30` — цикл watcher
- `REMINDER_SECONDS=21600` — повторный алерт пока DOWN/DEGRADED

## Проверки агента

- `https://127.0.0.1/` и `/login` с `Host: practice.croc.ru`
- `http://127.0.0.1:8090/health` (или порт 8090 слушает)
- `nginx` и `php*-fpm` active

## Диагностика

```bash
# на belarus
curl -sS http://127.0.0.1:8099/health
curl -sS -H "Authorization: Bearer $HEARTBEAT_TOKEN" http://127.0.0.1:8099/status
journalctl -u portal-heartbeat-collector -f

# на стенде
sudo systemctl start portal-agent.service
sudo journalctl -u portal-agent -n 50
```

Тест DOWN: `sudo systemctl stop portal-agent.timer` на стенде → через ~3 мин алерт в Telegram.
