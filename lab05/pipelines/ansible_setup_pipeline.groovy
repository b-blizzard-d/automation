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

        stage('Ansible') {
            steps {
                // ставим apache и php на test-server
                sh 'cd lab05/ansible && ansible-playbook -i hosts.ini setup_test_server.yml'
            }
        }
    }
}
