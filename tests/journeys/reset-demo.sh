#!/bin/sh
# Rebuilds the demo database used only for the journey videos (club_manager_demo; never the normal one).
set -e
HERE="$(cd "$(dirname "$0")" && pwd)"
cd "$HERE/../.."
export PGPASSWORD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')"
dropdb -h 127.0.0.1 -U postgres --if-exists --force club_manager_demo
createdb -h 127.0.0.1 -U postgres club_manager_demo
export DB_DATABASE=club_manager_demo
php artisan migrate --force --no-interaction >/dev/null
php artisan db:seed --force --no-interaction >/dev/null
php artisan tinker --execute "require '$HERE/demo-data.php';"
