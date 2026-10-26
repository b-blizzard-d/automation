#!/bin/sh
# взяла пример из задания, он уже делает всё нужное

create_log_file() {
    echo "Creating log file..."
    touch /var/log/cron.log
    chmod 666 /var/log/cron.log
    echo "Log file created at /var/log/cron.log"
}

monitor_logs() {
    echo "=== Monitoring cron logs ==="
    tail -f /var/log/cron.log
}

run_cron() {
    echo "=== Starting cron daemon ==="
    exec cron -f
}

# без этого cron не видит API_URL и API_KEY
env > /etc/environment
create_log_file
monitor_logs &
run_cron
