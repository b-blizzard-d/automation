FROM jenkins/ssh-agent

# ставим PHP-CLI, composer и php-xml (без него phpunit не ставится), mbstring и unzip тоже нужны composer
RUN apt-get update && apt-get install -y php-cli php-xml php-mbstring unzip composer
