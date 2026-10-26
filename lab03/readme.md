# Лабораторная работа 3: cron в Docker

### Что здесь

Тот же скрипт `currency_exchange_rate.py` из lab02, только теперь его запускает cron внутри контейнера.
Каждый день в 6:00 он берёт курс MDL -> EUR за вчера, а по пятницам в 17:00 берёт курс MDL -> USD за каждый день прошлой недели (7 запусков подряд).

### Как запустить

Сначала должен работать сервис из lab02prep на `http://localhost:8080` (контейнер обращается к нему через `host.docker.internal:8080`).

```bash
cd lab03
docker compose up -d --build
```

Docker Compose выводит предупреждение, что `version` устарел. Это только предупреждение, образ собирается.

Базовый образ `python:3.12-slim`, в нём уже есть python и pip, дополнительно ставлю только `cron` и `tzdata`.

### Как проверить, что работает

Логи контейнера показывают `tail -f /var/log/cron.log`:

```bash
docker logs -f lab03_cron
```

```
Creating log file...
Log file created at /var/log/cron.log
=== Starting cron daemon ===
=== Monitoring cron logs ===
```

Задания на месте:

```bash
docker exec lab03_cron crontab -l
```

Время местное (Кишинёв), а не UTC:

```bash
docker exec lab03_cron date
```

```
Mon Oct 26 02:23:58 EET 2026
```

Запустить вручную ту же команду, что и cron в 6 утра, и посмотреть лог:

```bash
docker exec lab03_cron sh -c '/usr/local/bin/python /app/currency_exchange_rate.py MDL EUR $(date -d yesterday +%F) >> /var/log/cron.log 2>&1'
docker exec lab03_cron tail /var/log/cron.log
```

```
Курсы есть только с 2025-01-01 по 2025-09-15, запрошена дата 2026-10-25
```

С датой из нужного периода всё работает:

```bash
docker exec lab03_cron /usr/local/bin/python /app/currency_exchange_rate.py MDL EUR 2025-09-14
```

```
Курс MDL -> EUR на 2025-09-14: 19.487, сохранила в data/mdl-eur-2025-09-14.json
```

Чтобы не ждать до 6 утра: временно поставить в `cronjob` `* * * * *` вместо `0 6 * * *`, снова выполнить `docker compose up -d --build`, подождать минуту и посмотреть `docker logs lab03_cron`. Я так и сделала (ещё добавила строку с датой `$(date -d "2025-09-15 -1 day" +\%F)`), через минуту в логе появилось:

```
Курсы есть только с 2025-01-01 по 2025-09-15, запрошена дата 2026-10-25
Курс MDL -> EUR на 2025-09-14: 19.487, сохранила в data/mdl-eur-2025-09-14.json
```

Значит, cron видит `API_URL` и `API_KEY` (их записывает в `/etc/environment` entrypoint) и подключается к сервису. Потом вернула расписание обратно.

Остановить и удалить контейнер, образ и тома:

```bash
docker compose down --rmi local -v
```

### Почему в логе ошибка

cron работает по настоящему календарю, а курсы в сервисе есть только с 2025-01-01 по 2025-09-15. Поэтому в 6:00 скрипт запрашивает, например, 2026-10-25 и пишет `Курсы есть только с 2025-01-01 по 2025-09-15, запрошена дата 2026-10-25`. Так и должно быть: скрипт сам отказывается ещё до запроса, а строчка всё равно попадает в `/var/log/cron.log`, значит cron работает.

### Файлы

- `cronjob`: два задания для cron (6:00 каждый день и пятница 17:00), ставится через `crontab cronjob` при сборке
- `Dockerfile`: образ на `python:3.12-slim` с cron и tzdata, копирует всю папку в `/app`
- `entrypoint.sh`: пример из задания, сохраняет переменные окружения, создаёт лог, показывает его и запускает cron
- `docker-compose.yml`: собирает образ, передаёт `API_URL`, `API_KEY`, `TZ` и даёт доступ к хосту через `host.docker.internal`
- `currency_exchange_rate.py`: скрипт из lab02, без изменений
- `requirements.txt`: `requests`
- `data/`: JSON-файлы, оставшиеся из lab02
- `error.log`: лог ошибок скрипта из lab02 (в контейнере туда дописываются новые)
