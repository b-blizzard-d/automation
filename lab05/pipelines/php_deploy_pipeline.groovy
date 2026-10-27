pipeline {
    agent {
        label 'ansible'
    }

    stages {
        stage('Clone') {
            steps {
                git branch: 'main', url: 'https://github.com/b-blizzard-d/automation.git'
            }
        }

        stage('Copy') {
            steps {
                // копируем src и public на test-server
                sh 'cd lab05/ansible && ansible-playbook -i hosts.ini deploy.yml'
            }
        }

        stage('Configure') {
            steps {
                // файлы принадлежат пользователю веб-сервера
                sh 'cd lab05/ansible && ansible test -i hosts.ini -b -m file -a "path=/var/www/tip-splitter owner=www-data group=www-data recurse=yes"'
            }
        }
    }
}
