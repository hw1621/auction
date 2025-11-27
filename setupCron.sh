#!/bin/bash

echo "Setting up cron job for auction auto-close..."

# Detect PHP path
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo "❌ PHP not found in PATH. Please install PHP first."
    exit 1
fi

echo "✅ PHP found at: $PHP_PATH"

# Detect project directory
PROJECT_PATH="$(cd "$(dirname "$0")"; pwd)"
CHECK_SCRIPT="$PROJECT_PATH/check_auctions.php"

if [ ! -f "$CHECK_SCRIPT" ]; then
    echo "❌ check_auctions.php not found at $CHECK_SCRIPT"
    exit 1
fi

echo "Project path: $PROJECT_PATH"

# The cron job entry
CRON_JOB="* * * * * $PHP_PATH $CHECK_SCRIPT >> $PROJECT_PATH/cron.log 2>&1"

# Remove old cron jobs related to this script
crontab -l 2>/dev/null | grep -v "check_auctions.php" > /tmp/current_cron

# Add new cron job
echo "$CRON_JOB" >> /tmp/current_cron

# Install updated cron list
crontab /tmp/current_cron
rm /tmp/current_cron

echo "✨ Cron job installed successfully!"
echo "⏱ Runs every minute:"
echo "$CRON_JOB"

echo "Log file is at: $PROJECT_PATH/cron.log"
