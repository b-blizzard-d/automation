FROM python:3.12-slim

# cron и часовые пояса
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y cron tzdata

WORKDIR /app
COPY . /app

RUN pip install -r requirements.txt

# ставим задания
RUN chmod +x entrypoint.sh && crontab cronjob

CMD ["./entrypoint.sh"]
