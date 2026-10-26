# Лаба 4 — Jenkins + агент с PHP

### Что за проект

`tip-splitter` — маленький php-проект: делит счёт в кафе на компанию и считает чаевые официанту. Там один класс `App\BillSplitter` и 6 тестов на PHPUnit 13 (ставится через composer).
Тесты гоняю не у себя на ноуте, а в Jenkins: контроллер и ssh-агент с PHP подняты в докере, агент сам забирает репозиторий и запускает `composer install` и `phpunit`.

```
lab04/
├── Dockerfile            # образ агента: jenkins/ssh-agent + php и composer
├── Jenkinsfile           # пайплайн
├── docker-compose.yml    # контроллер + агент
├── readme.md
└── tip-splitter/
    ├── composer.json
    ├── composer.lock
    ├── src/BillSplitter.php
    └── tests/BillSplitterTest.php
```

Папки `secrets/` и файла `.env` в репе нет, они есть только у меня локально (ключи светить нельзя).

### Как поднять

Сначала сделайте ключ и .env сами:

```bash
cd lab04
mkdir secrets && cd secrets && ssh-keygen -f jenkins_agent_ssh_key
```

Пароль у ключа оставила пустым. Потом открыть `secrets/jenkins_agent_ssh_key.pub`, скопировать оттуда строчку и руками вставить в `lab04/.env`:

```
JENKINS_AGENT_SSH_PUBKEY=ssh-ed25519 AAAA... jenkins@lab4
```

Дальше просто:

```bash
docker compose up -d --build
```

У меня на 8080 висит `php_apache` из lab02prep, так что перед стартом делаю `docker stop php_apache` (что было без этого — в проблемах внизу).
Компоуз ещё пишет, что `version` устарел, — это просто предупреждение, как и в lab03.

Пароль для первого входа лежит в логах контроллера, между строчками со звёздочками:

```bash
docker compose logs jenkins-controller
```

```
jenkins-controller  | [LF]> Jenkins initial setup is required. An admin user has been created and a password generated.
jenkins-controller  | [LF]> Please use the following password to proceed to installation:
jenkins-controller  | [LF]> 
jenkins-controller  | [LF]> 89515223e5304b3ba304943c585ccd27
jenkins-controller  | [LF]> 
jenkins-controller  | [LF]> This may also be found at: /var/jenkins_home/secrets/initialAdminPassword
```

Захожу на http://localhost:8080, вставляю пароль, жму **Install suggested plugins**, жду пока всё поставится и создаю своего админа.

### Агент

В **Manage Jenkins → Plugins → Installed plugins** проверила, что есть **SSH Build Agents plugin** — он приехал вместе с suggested плагинами, ничего доставлять не пришлось.

Ключ: **Manage Jenkins → Credentials → System → Global credentials → Add Credentials**:
- Kind: **SSH Username with private key**
- ID: пустой (Jenkins сам придумает)
- Description: `ключ агента`
- Username: `jenkins`
- Private Key → Enter directly → вставила всё содержимое `secrets/jenkins_agent_ssh_key`, passphrase пустой

Нода: **Manage Jenkins → Nodes → New Node**, имя `ssh-agent1`, тип **Permanent Agent**:
- Remote root directory: `/home/jenkins/agent`
- Labels: `php-agent`
- Launch method: **Launch agents via SSH**
- Host: `ssh-agent` (имя сервиса из компоуза, они в одной сети `jenkins-network`)
- Credentials: `jenkins (ключ агента)`
- Host Key Verification Strategy: **Non verifying Verification Strategy**

Остальное оставила по умолчанию. Про Non verifying: для учебного стенда в своей докер-сети норм, в проде так не надо. Через пару секунд нода стала онлайн, в логе ноды было `Agent successfully connected and online`.

### Пайплайн

**New Item** → имя `lab4` → **Pipeline** → OK. Внизу в разделе Pipeline:
- Definition: **Pipeline script from SCM**
- SCM: **Git**
- Repository URL: `https://github.com/b-blizzard-d/automation.git` (репа публичная, credentials не нужны)
- Branch Specifier: `*/lab04`
- Script Path: `lab04/Jenkinsfile`

Save → **Build Now**.

В `Jenkinsfile` всё как в задании, только дописала по одной команде в каждый stage:
- Install Dependencies: `cd lab04/tip-splitter && composer install`
- Test: `cd lab04/tip-splitter && vendor/bin/phpunit tests`

`phpunit.xml` не делала, просто указываю папку `tests`, PHPUnit 13 так нормально запускается.

### Что вышло

Билд №3, Console Output (порезала только выкачку репы и список пакетов композера):

```
Started
...
[Pipeline] Start of Pipeline
[Pipeline] node
Running on ssh-agent1 in /home/jenkins/agent/workspace/lab4
[Pipeline] {
[Pipeline] stage
[Pipeline] { (Declarative: Checkout SCM)
[Pipeline] checkout
...
Commit message: "добавила php-xml в агент, без него phpunit не ставился"
[Pipeline] }
[Pipeline] // stage
[Pipeline] withEnv
[Pipeline] {
[Pipeline] stage
[Pipeline] { (Install Dependencies)
[Pipeline] echo
Preparing project...
[Pipeline] sh
+ cd lab04/tip-splitter
+ composer install
Installing dependencies from lock file (including require-dev)
Verifying lock file contents can be installed on current platform.
Package operations: 27 installs, 0 updates, 0 removals
...
  - Installing phpunit/phpunit (13.4.3): Extracting archive
Generating autoload files
25 packages you are using are looking for funding.
Use the `composer fund` command to find out more!
[Pipeline] }
[Pipeline] // stage
[Pipeline] stage
[Pipeline] { (Test)
[Pipeline] echo
Running tests...
[Pipeline] sh
+ cd lab04/tip-splitter
+ vendor/bin/phpunit tests
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27

......                                                              6 / 6 (100%)

Time: 00:00, Memory: 20.00 MB

OK (6 tests, 6 assertions)
[Pipeline] }
[Pipeline] // stage
[Pipeline] stage
[Pipeline] { (Declarative: Post Actions)
[Pipeline] echo
Pipeline completed.
[Pipeline] echo
All stages completed successfully!
[Pipeline] }
[Pipeline] // stage
[Pipeline] }
[Pipeline] // withEnv
[Pipeline] }
[Pipeline] // node
[Pipeline] End of Pipeline
Finished: SUCCESS
```

Все 6 тестов зелёные. Билды №1 и №2 упали, почему — ниже.

### Вопросы

**Какие плюсы у Jenkins для автоматизации DevOps-задач?**

- бесплатный и поднимается в докере одной командой, никаких аккаунтов и подписок;
- сам делает то, что я бы иначе каждый раз делала руками: выкачать код, `composer install`, `phpunit`, и так на каждый билд;
- плагины почти под всё: git, ssh-агенты, docker, уведомления и т.д.;
- сразу видно, красный билд или зелёный, и в Console Output понятно, на каком шаге упало.

**Какие ещё бывают агенты Jenkins?**

- SSH-агент — мой вариант: дженкинс заходит на агента по ssh под юзером jenkins;
- inbound — тут всё наоборот, агент сам подключается к дженкинсу;
- облачные агенты (Docker, Kubernetes, EC2) — поднимаются под билд и удаляются после;
- встроенная нода (Built-In Node) — билды прямо на контроллере, но так делать не советуют.

**Какие были проблемы и как решила?**

1. Порт 8080 занят. Контроллер не стартовал: `Bind for 0.0.0.0:8080 failed: port is already allocated`. Там сидит `php_apache` из lab02prep, остановила его через `docker stop php_apache` и запустила заново.
2. Агент не подключался. Сначала оставила стратегию проверки ключа по умолчанию (**Known hosts file**), а в логе ноды по кругу: `No Known Hosts file was found at /var/jenkins_home/.ssh/known_hosts` и `Key exchange was not finished, connection is closed.` Файла known_hosts у контроллера просто нет. Переключила на **Non verifying Verification Strategy** — сразу подключился.
3. Первый билд упал сразу, даже до стадий. Ветку в джобе не трогала, там по умолчанию `*/master`, а у меня `main` и `lab04`: `fatal: couldn't find remote ref refs/heads/master`. Поменяла на `*/lab04`.
4. Второй билд упал на `composer install`:
   ```
   Your lock file does not contain a compatible set of packages. Please run composer update.

     Problem 1
       - phar-io/manifest is locked to version 2.0.4 and an update of this package was not requested.
       - phar-io/manifest 2.0.4 requires ext-dom * -> it is missing from your system. Install or enable PHP's dom extension.
     ...
     Problem 3
       - phpunit/phpunit is locked to version 13.4.3 and an update of this package was not requested.
       - phpunit/phpunit 13.4.3 requires ext-dom * -> it is missing from your system. Install or enable PHP's dom extension.
   ```
   `php-cli` из задания идёт без dom/xml, а PHPUnit без них не ставится. Добавила в `Dockerfile` `php-xml` (и заодно `php-mbstring` и `unzip`, их композер тоже любит), пересобрала агента `docker compose up -d --build`, третий билд прошёл.
