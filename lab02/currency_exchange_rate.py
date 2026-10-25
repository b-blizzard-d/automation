# Лаба 2: берём курс валют с API и кладём ответ в json
import sys
import os
import json
import re
from datetime import datetime

import requests

# ключ берём из окружения, если нет, то дефолтный
URL = os.getenv("API_URL", "http://localhost:8080")
KEY = os.getenv("API_KEY", "EXAMPLE_API_KEY")

here = os.path.dirname(os.path.abspath(__file__))


def log_err(msg):
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    with open(os.path.join(here, "error.log"), "a", encoding="utf-8") as f:
        f.write(f"[{now}] {msg}\n")


def bail(msg):
    # пишем в консоль, в лог и выходим
    print(msg, file=sys.stderr)
    log_err(msg)
    sys.exit(1)


FIRST_DAY = datetime(2025, 1, 1)
LAST_DAY = datetime(2025, 9, 15)


def main():
    if len(sys.argv) != 4:
        print("Пиши так: python3 currency_exchange_rate.py EUR MDL 2025-01-01", file=sys.stderr)
        sys.exit(1)

    frm = sys.argv[1].upper()
    to = sys.argv[2].upper()
    day = sys.argv[3]

    # проверки до запроса, чтобы зря не дёргать сервер
    for cur in (sys.argv[1], sys.argv[2]):
        if not re.fullmatch(r"[A-Z]{3}", cur.upper()):
            bail(f'Валюта "{cur}" какая-то странная, нужно 3 буквы')

    try:
        d = datetime.strptime(day, "%Y-%m-%d")
    except ValueError:
        bail(f'Дата "{day}" не похожа на ГГГГ-ММ-ДД')
    if d < FIRST_DAY or d > LAST_DAY:
        bail(f"Курсы есть только с 2025-01-01 по 2025-09-15, а ты просишь {day}")

    try:
        resp = requests.post(URL, params={"from": frm, "to": to, "date": day},
                             data={"key": KEY}, timeout=10)
    except requests.exceptions.Timeout:
        bail(f"Сервер слишком долго думает ({URL}), не дождалась")
    except requests.exceptions.RequestException:
        bail(f"Сервер молчит ({URL}). Он вообще запущен?")

    if resp.status_code != 200:
        bail(f"Сервер ответил кодом {resp.status_code}, что-то сломалось")

    try:
        res = resp.json()
    except ValueError:
        bail("Сервер вернул что-то не то (не JSON)")

    if res.get("error"):
        bail(f"API ругается: {res['error']}")
    if not res.get("data"):
        bail("В ответе нет data, странно")

    # сохраняем ответ как есть, имя файла типа eur-mdl-2025-01-01.json
    os.makedirs(os.path.join(here, "data"), exist_ok=True)
    fname = f"{frm.lower()}-{to.lower()}-{day}.json"
    with open(os.path.join(here, "data", fname), "w", encoding="utf-8") as f:
        json.dump(res, f, ensure_ascii=False, indent=2)

    # rate у сервиса это сколько frm дают за 1 to, поэтому так и пишем
    print(f"Курс {frm} -> {to} на {day}: {res['data']['rate']}, записала в data/{fname}")


if __name__ == "__main__":
    main()
