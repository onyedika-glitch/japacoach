#!/usr/bin/env bash
# Run on the VPS from the repository root: ./deploy.sh
set -euo pipefail

if [ ! -f .env ] || [ ! -f api/.env ]; then
    echo "Create .env (from .env.example) and api/.env (from api/.env.production.example) first."
    exit 1
fi

git pull --ff-only
docker compose build
docker compose up -d --remove-orphans
docker compose exec -T app php artisan queue:restart
docker image prune -f

echo "Deployed. Check status with: docker compose ps"
