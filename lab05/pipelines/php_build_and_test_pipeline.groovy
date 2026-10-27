pipeline {
    agent {
        label 'php-agent'
    }

    stages {
        stage('Clone') {
            steps {
                git branch: 'main', url: 'https://github.com/b-blizzard-d/automation.git'
            }
        }

        stage('Composer') {
            steps {
                // ставим зависимости (phpunit)
                sh 'cd lab05/tip-splitter && composer install'
            }
        }

        stage('PHPUnit') {
            steps {
                // тесты, результат ещё пишется в testdox.txt
                sh 'cd lab05/tip-splitter && vendor/bin/phpunit tests --testdox-text testdox.txt'
            }
        }

        stage('Report') {
            steps {
                archiveArtifacts artifacts: 'lab05/tip-splitter/testdox.txt'
                echo 'Отчёт по тестам лежит в артефактах сборки (testdox.txt).'
            }
        }
    }

    post {
        always {
            echo 'Pipeline completed.'
        }
        success {
            echo 'All stages completed successfully!'
        }
        failure {
            echo 'Errors detected in the pipeline.'
        }
    }
}
