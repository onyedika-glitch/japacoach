#!/usr/bin/env bash
# Run the whole of Japa Coach locally in Docker: ./dev.sh
# Then open http://localhost:8088. Stop with: docker compose -f docker-compose.yml -f docker-compose.local.yml down
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -f .env ]; then
    printf 'DOMAIN=:80\nSPEECH_SERVICE_TOKEN=%s\nWHISPER_MODEL=base.en\nWHISPER_THREADS=2\n' "$(openssl rand -hex 24)" > .env
    echo "Created .env for local use."
fi

COMPOSE="docker compose -f docker-compose.yml -f docker-compose.local.yml"

echo "Building and starting (the first build downloads ~2 GB and takes a while)…"
$COMPOSE up -d --build

echo "Waiting for the app to finish migrating…"
until $COMPOSE exec -T app php artisan migrate:status >/dev/null 2>&1; do sleep 3; done

echo "Loading starter content (skips anything already loaded)…"
$COMPOSE exec -T app php artisan db:seed --force

echo "Queueing examiner and listening audio (renders in the background)…"
$COMPOSE exec -T app php artisan audio:render

echo
echo "Japa Coach is running at http://localhost:8088"
echo "Make yourself admin after signing up: $COMPOSE exec app php artisan user:make-admin you@example.com"
echo "Following logs (Ctrl+C stops the logs, not the app)…"
$COMPOSE logs -f --tail=20 app queue speech-queue speech
