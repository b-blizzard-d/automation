# Лабораторная работа 5: Ansible и тестовый сервер

### Что за проект

Это продолжение lab04. К Jenkins и агенту с PHP из lab04 добавились ещё два контейнера на Ubuntu: агент, на котором работает Ansible, и тестовый сервер, куда выкладывается сайт.
Ansible сам ставит на тестовый сервер Apache и PHP, а потом копирует туда мой проект `tip-splitter` из lab04. К проекту добавила одну страницу `public/index.php`: это калькулятор чаевых, он использует класс `App\BillSplitter`.
Всё запускают три пайплайна в Jenkins: тесты, настройка сервера и копирование проекта.

```
lab05/
├── compose.yaml                 # 4 сервиса
├── Dockerfile.ssh_agent         # агент с PHP (это Dockerfile из lab04)
├── Dockerfile.ansible_agent     # агент с Ansible
├── Dockerfile.test_server       # тестовый сервер
├── ansible/
│   ├── hosts.ini                # где сервер и как на него заходить
│   ├── setup_test_server.yml    # Apache, PHP, сайт
│   └── deploy.yml               # копирование проекта
├── pipelines/
│   ├── php_build_and_test_pipeline.groovy
│   ├── ansible_setup_pipeline.groovy
│   └── php_deploy_pipeline.groovy
├── tip-splitter/                # проект из lab04 + public/index.php
└── readme.md
```

Файлы взяла из lab04: `Dockerfile` переименовала в `Dockerfile.ssh_agent`, `docker-compose.yml` в `compose.yaml`. `Jenkinsfile` удалила, его две команды теперь в `php_build_and_test_pipeline.groovy`.
Порт 8080 у меня, как и в lab04, занят `php_apache`, поэтому перед запуском я его останавливаю.

### Ключи

Нужно три пары ключей, все лежат в `lab05/secrets/`:

| Ключ | Кто с кем | Куда идёт `.pub` |
|---|---|---|
| `jenkins_agent_ssh_key` | Jenkins заходит на ssh-agent | в `.env`, строчка `JENKINS_AGENT_SSH_PUBKEY=...` |
| `ansible_agent_key` | Jenkins заходит на ansible-agent | копируется в образ ansible-agent |
| `ansible_key` | ansible-agent заходит на test-server | копируется в образ test-server |

Первый ключ и `.env` взяла из lab04 без изменений. Два новых сделала так (пароль пустой):

```bash
cd lab05/secrets
ssh-keygen -t ed25519 -f ansible_agent_key
ssh-keygen -t ed25519 -f ansible_key
```

Закрытый `ansible_key` попадает в агент через `./secrets/ansible_key:/secrets/ansible_key:ro` в `compose.yaml`. Монтирую только этот файл, а не всю папку, чтобы ключи Jenkins на агенте не лежали. Папки `secrets/` и файла `.env` в репозитории нет (они в `.gitignore`), ключи нельзя публиковать.
Так как `.pub` копируются при сборке, образы нужно собирать уже после создания ключей, а если ключ поменялся, пересобрать.

Запуск:

```bash
cd lab05
docker compose up -d --build
```

### Jenkins

- Пароль для входа есть в логах (`docker compose logs jenkins-controller`):
  ```
  jenkins-controller  | [LF]> Please use the following password to proceed to installation:
  jenkins-controller  | [LF]> 
  jenkins-controller  | [LF]> 978d983b2fc543519a1b1c8ac529d19f
  ```
- http://localhost:8080 → пароль → **Install suggested plugins** → создала администратора.
- **Manage Jenkins → Plugins → Available plugins**, поставила: **Docker**, **Docker Pipeline**, **GitHub Integration**, **SSH Agent**. **SSH Build Agents**, **Git** и **Pipeline** уже были в suggested.
- **Credentials → System → Global credentials → Add Credentials**, два ключа, оба **SSH Username with private key**, Username `jenkins`, ID пустой:
  - `ключ агента`: содержимое `secrets/jenkins_agent_ssh_key`;
  - `ключ для агента ansible`: содержимое `secrets/ansible_agent_key`.
- **Nodes → New Node**, два узла **Permanent Agent**, Remote root directory `/home/jenkins/agent`, **Launch agents via SSH**, **Non verifying Verification Strategy**:
  - `ssh-agent1`: Labels `php-agent`, Host `ssh-agent`, ключ `ключ агента`;
  - `ansible-agent`: Labels `ansible`, Host `ansible-agent`, ключ `ключ для агента ansible`.

Non verifying, как и в lab04: это учебный стенд в своей сети Docker. Оба узла подключились, в логе `Agent successfully connected and online`.

### SSH-агент

`Dockerfile.ssh_agent` это `Dockerfile` из lab04: образ `jenkins/ssh-agent`, в него ставятся `php-cli`, `php-xml`, `php-mbstring`, `unzip` и `composer`. Ключ передаётся через `.env` в переменную `JENKINS_AGENT_SSH_PUBKEY`, образ сам кладёт его в `authorized_keys`. Узел `ssh-agent1`, метка `php-agent`, как в lab04.

### Агент Ansible

`Dockerfile.ansible_agent`, основа `ubuntu:24.04`. Через apt ставятся:
- `ansible`, у меня это `ansible [core 2.16.3]`;
- `openjdk-21-jre-headless`, потому что Jenkins запускает на агенте свою программу на Java (контроллер на Java 21);
- `git`, чтобы забрать репозиторий;
- `openssh-server`, чтобы Jenkins мог зайти.

Потом создаётся пользователь `jenkins`, в его `authorized_keys` копируется `ansible_agent_key.pub`, вход по паролю выключается (`PasswordAuthentication no`), запускается `sshd -D`.

### Тестовый сервер

`Dockerfile.test_server`, тоже `ubuntu:24.04`:
- ставятся `openssh-server`, `python3` (Ansible выполняет на сервере свои модули на Python) и `sudo`;
- создаётся пользователь `ansible`, ему разрешён `sudo` без пароля (`/etc/sudoers.d/ansible`), иначе `become` не работает;
- в `/home/ansible/.ssh/authorized_keys` копируется `ansible_key.pub`, вход по паролю выключен;
- в `compose.yaml` порт `8000:80`, сайт открывается на http://localhost:8000.

Apache и PHP в Dockerfile не ставлю, это делает Ansible.

### Плейбук

`ansible/hosts.ini`:

```ini
[test]
test-server ansible_user=ansible ansible_ssh_private_key_file=/secrets/ansible_key ansible_ssh_common_args='-o StrictHostKeyChecking=no'
```

`test-server` это имя сервиса из compose, пользователь `ansible`, ключ из примонтированной папки. Проверку ключа хоста выключила по той же причине, что и в Jenkins (см. проблемы). `ansible.cfg` не делала, хватило `hosts.ini`.

`setup_test_server.yml`, одна play на группу `test` с `become: yes`:
1. `ставим apache и php`: `apt` ставит `apache2`, `php`, `libapache2-mod-php`. Других расширений моему классу не нужно, а `libapache2-mod-php` сам включает PHP в Apache.
2. `запускаем apache`: `service` с `state: started`. В контейнере apt сам Apache не запускает.
3. `часовой пояс для php`: `copy` кладёт файл `/etc/php/8.3/apache2/conf.d/99-tip-splitter.ini` со строчкой `date.timezone = Europe/Chisinau`. Это и есть настройка PHP.
4. `сайт для tip-splitter`: `copy` создаёт `/etc/apache2/sites-available/tip-splitter.conf`, в нём `DocumentRoot /var/www/tip-splitter/public` и `Require all granted`.
5. `включаем сайт`: `a2ensite tip-splitter`.
6. `выключаем стандартный сайт`: `a2dissite 000-default`.

Задачи 3, 4, 5, 6 вызывают handler `restart apache` (`service`, `state: restarted`). Systemd в контейнере нет, но модуль `service` работает и так.

Первый запуск:

```
PLAY RECAP *********************************************************************
test-server                : ok=8    changed=7    unreachable=0    failed=0    skipped=0    rescued=0    ignored=0
```

При втором запуске `changed=3`: apt, service и оба copy уже `ok`, а две команды `a2ensite`/`a2dissite` всё равно пишут `changed`, и поэтому Apache снова перезапускается. Ничего не ломается, просто `command` не знает, что сайт уже включён.

`deploy.yml` копирует на сервер две папки: `tip-splitter/src` и `tip-splitter/public` в `/var/www/tip-splitter/`. Тесты и `vendor` на сервер не нужны, composer там тоже не нужен: страница подключает класс через `require`.

### Пайплайны

Все три сделаны одинаково: **New Item** → **Pipeline**, Definition **Pipeline script from SCM**, Git, `https://github.com/b-blizzard-d/automation.git`, Branch `*/main`, Script Path `lab05/pipelines/<файл>.groovy`. Первая стадия `Clone` в каждом: `git branch: 'main', url: '...'`.

**lab5-build** (`php_build_and_test_pipeline.groovy`, агент `php-agent`): Clone, Composer (`composer install`), PHPUnit (`vendor/bin/phpunit tests --testdox-text testdox.txt`), Report (`testdox.txt` сохраняется в артефакты сборки).

```
[Pipeline] { (PHPUnit)
[Pipeline] sh
+ cd lab05/tip-splitter
+ vendor/bin/phpunit tests --testdox-text testdox.txt
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27

......                                                              6 / 6 (100%)

Time: 00:00.005, Memory: 20.00 MB

OK (6 tests, 6 assertions)
...
[Pipeline] { (Report)
[Pipeline] archiveArtifacts
Archiving artifacts
[Pipeline] echo
Отчёт по тестам лежит в артефактах сборки (testdox.txt).
...
Finished: SUCCESS
```

Сам `testdox.txt`:

```
Bill Splitter
 [x] Tip
 [x] Total with tip
 [x] Per person no tip
 [x] Per person with tip
 [x] Zero people throws
 [x] Negative total throws
```

**lab5-ansible** (`ansible_setup_pipeline.groovy`, агент `ansible`): Clone, Ansible (`cd lab05/ansible && ansible-playbook -i hosts.ini setup_test_server.yml`).

```
Running on ansible-agent in /home/jenkins/agent/workspace/lab5-ansible
...
+ ansible-playbook -i hosts.ini setup_test_server.yml

PLAY [test] ********************************************************************

TASK [Gathering Facts] *********************************************************
ok: [test-server]

TASK [ставим apache и php] *****************************************************
changed: [test-server]
...
RUNNING HANDLER [restart apache] ***********************************************
changed: [test-server]
...
Finished: SUCCESS
```

**lab5-deploy** (`php_deploy_pipeline.groovy`, агент `ansible`): Clone, Copy (`ansible-playbook -i hosts.ini deploy.yml`), Configure: одна команда `ansible test -i hosts.ini -b -m file -a "path=/var/www/tip-splitter owner=www-data group=www-data recurse=yes"`, чтобы файлы принадлежали пользователю веб-сервера.

```
+ ansible-playbook -i hosts.ini deploy.yml
...
TASK [копируем src] ************************************************************
changed: [test-server]

TASK [копируем public] *********************************************************
changed: [test-server]

PLAY RECAP *********************************************************************
test-server                : ok=3    changed=2    unreachable=0    failed=0    skipped=0    rescued=0    ignored=0
...
[Pipeline] { (Configure)
...
test-server | CHANGED => {
...
    "owner": "www-data",
    "path": "/var/www/tip-splitter",
...
Finished: SUCCESS
```

Запускать по порядку: lab5-build, lab5-ansible, lab5-deploy.

### Результат

```bash
curl -s 'http://localhost:8000/?sum=1000&people=4&tip=10'
```

```
...
<p>Чаевые: 100 лей</p><p>Каждый платит: 275 лей</p></body>
```

1000 лей + 10% = 1100, на четверых 275. Если указать `people=0`, страница пишет `на 0 человек не делится` (это исключение из класса). В браузере на http://localhost:8000 видна обычная форма с тремя полями и кнопкой **Посчитать**. На сервере Apache 2.4.58 и PHP 8.3.6.

### Вопросы

**Чем Ansible удобен для настройки сервера?**

Один раз описываешь в playbook, что должно быть на сервере, и больше не вводишь команды в контейнере одну за другой. На сам сервер ничего своего ставить не нужно, Ansible заходит по SSH. Повторный запуск не ломает то, что уже настроено: у меня второй запуск прошёл без ошибок, apt и copy просто показали `ok`.

**Какие ещё есть модули?**

- `cron`: добавить задачу, например запуск скрипта каждый час, как в lab03;
- `pip`: поставить пакет Python, например `requests`;
- `community.docker.docker_container`: запустить контейнер из образа;
- `community.mysql.mysql_db`: создать базу данных MySQL;
- `community.general.archive`: упаковать папку в архив, например для резервной копии.

**Какие были проблемы?**

1. Контейнер агента Ansible сразу останавливался, Jenkins не смог бы к нему подключиться. В логе было `Missing privilege separation directory: /run/sshd`. В образе `ubuntu:24.04` этой папки нет, а без неё `sshd` не запускается. Добавила `/run/sshd` в `mkdir` в Dockerfile (в тестовом сервере сделала так же сразу).
2. Ansible не мог зайти на сервер. Когда пробовала без последнего параметра в `hosts.ini`:
   ```
   test-server | UNREACHABLE! => {
       "changed": false,
       "msg": "Failed to connect to the host via ssh: Host key verification failed.",
       "unreachable": true
   }
   ```
   Ключа сервера нет в `known_hosts` агента, а спросить «yes/no» Ansible не может. Добавила `ansible_ssh_common_args='-o StrictHostKeyChecking=no'`, после этого всё подключилось.
