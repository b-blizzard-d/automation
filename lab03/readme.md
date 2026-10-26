# Лаба 3 — cron в докере

### Что тут

Тот же скрипт `currency_exchange_rate.py` из lab02, только теперь его сам запускает cron внутри контейнера.
Каждый день в 6:00 он берёт курс MDL -> EUR за вчера, а по пятницам в 17:00 — курс MDL -> USD за каждый день прошлой недели (7 запусков подряд).

### Как запустить

Сначала должен работать сервис из lab02prep на `http://localhost:8080` (контейнер ходит к нему через `host.docker.internal:8080`).

```bash
cd lab03
docker compose up -d --build
```

Компоуз ругнётся, что `version` устарел, — это просто предупреждение, всё собирается.

Образ взяла `python:3.12-slim`, там уже есть python и pip, доставляю только `cron` и `tzdata`.

### Как проверить, что работает

Логи контейнера — это и есть `tail -f /var/log/cron.log`:

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

Запустить ту же команду, что и cron в 6 утра, руками и глянуть лог:

```bash
docker exec lab03_cron sh -c '/usr/local/bin/python /app/currency_exchange_rate.py MDL EUR $(date -d yesterday +%F) >> /var/log/cron.log 2>&1'
docker exec lab03_cron tail /var/log/cron.log
```

```
Курсы есть только с 2025-01-01 по 2025-09-15, а ты просишь 2026-10-25
```

С нормальной датой всё ок:

```bash
docker exec lab03_cron /usr/local/bin/python /app/currency_exchange_rate.py MDL EUR 2025-09-14
```

```
Курс MDL -> EUR на 2025-09-14: 19.487, записала в data/mdl-eur-2025-09-14.json
```

Чтобы не ждать до 6 утра: временно поставить в `cronjob` `* * * * *` вместо `0 6 * * *`, снова `docker compose up -d --build`, подождать минуту и смотреть `docker logs lab03_cron`. Я так и сделала (ещё добавила строку с датой `$(date -d "2025-09-15 -1 day" +\%F)`), через минуту в логе появилось:

```
Курсы есть только с 2025-01-01 по 2025-09-15, а ты просишь 2026-10-25
Курс MDL -> EUR на 2025-09-14: 19.487, записала в data/mdl-eur-2025-09-14.json
```

То есть cron видит `API_URL` и `API_KEY` (их кладёт в `/etc/environment` entrypoint) и достучался до сервиса. Потом вернула расписание обратно.

Остановить и убрать за собой:

```bash
docker compose down --rmi local -v
```

### Почему в логе ошибка

cron живёт по настоящему календарю, а курсы в сервисе есть только с 2025-01-01 по 2025-09-15. Поэтому в 6:00 скрипт просит, например, 2026-10-25 и пишет `Курсы есть только с 2025-01-01 по 2025-09-15, а ты просишь 2026-10-25`. Это нормально: скрипт сам отказывается, ещё до запроса, а строчка всё равно попадает в `/var/log/cron.log`, значит cron работает.

### Файлы

- `cronjob` — два задания для cron (6:00 каждый день и пятница 17:00), ставится через `crontab cronjob` при сборке
- `Dockerfile` — образ на `python:3.12-slim` + cron и tzdata, копирует всю папку в `/app`
- `entrypoint.sh` — пример из задания: сохраняет env, создаёт лог, показывает его и запускает cron
- `docker-compose.yml` — собирает образ, передаёт `API_URL`, `API_KEY`, `TZ` и пускает к хосту через `host.docker.internal`
- `currency_exchange_rate.py` — скрипт из lab02, без изменений
- `requirements.txt` — `requests`
- `data/` — json-ки, оставшиеся из lab02
- `error.log` — лог ошибок скрипта из lab02 (в контейнере туда дописываются новые)
