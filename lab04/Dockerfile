FROM jenkins/ssh-agent

# ставим PHP-CLI, composer и php-xml (без него phpunit не ставится), mbstring и unzip на всякий случай
RUN apt-get update && apt-get install -y php-cli php-xml php-mbstring unzip composer
