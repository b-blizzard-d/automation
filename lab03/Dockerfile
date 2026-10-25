FROM python:3.12-slim

# ставим cron
RUN apt-get update && apt-get install -y cron

WORKDIR /app
COPY . /app

RUN pip install -r requirements.txt

# ставим задания
RUN chmod +x entrypoint.sh && crontab cronjob

CMD ["./entrypoint.sh"]
