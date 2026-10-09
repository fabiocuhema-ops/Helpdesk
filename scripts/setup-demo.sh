#!/usr/bin/env bash
set -euo pipefail

composer install
npm install
cp -n .env.example .env || true
php artisan key:generate --force
php artisan migrate:fresh --seed
npm run build
