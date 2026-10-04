#!/bin/bash
set -euo pipefail

echo "=========================================="
echo "Starting FYB Fullstack Application Setup  "
echo "=========================================="

# Create the .env file if it doesn't exist
if [ ! -f backend/.env ]; then
    echo "[2/7] Creating .env file from .env.example..."
    cp backend/.env.example backend/.env
else
    echo "[2/7] .env file already exists, skipping..."
fi

# Stop any existing containers
echo "[3/7] Stopping any existing containers..."
docker compose down

# Build and start the docker containers
echo "[4/7] Building and starting Docker containers..."
docker compose up -d --build

# Wait for the backend container to be ready
echo "[5/7] Waiting for containers to be ready..."
sleep 10

# Check if containers are running
echo ""
echo "Checking container status..."
docker compose ps

# Host bind-mount replaces image ownership; php-fpm runs as www-data.
echo ""
echo "[6/7] Making storage and cache writable..."
docker compose exec -u root backend sh -c 'chgrp -R www-data storage bootstrap/cache && chmod -R g+rwX storage bootstrap/cache'

# Install Laravel dependencies
echo ""
echo "[7/7] Installing laravel dependencies..."
docker compose exec backend composer install

# Generate Key
echo ""
echo "[8/7] Generating laravel key..."
docker compose exec backend php artisan key:generate

# Run database migrations
echo ""
echo "[9/7] Running database migrations..."
docker compose exec backend php artisan migrate --force

# Generate JWT Secret Key
echo ""
echo "[10/7] Generating JWT secret key..."
docker compose exec backend php artisan jwt:secret --force

# Seed demo staff, products, and roles
echo ""
echo "[11/7] Seeding the database..."
docker compose exec backend php artisan db:seed --force

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Services:"
echo "  - App (Vue with Laravel API):  http://localhost:8000"
echo ""
echo "To view logs:"
echo "  docker compose logs -f"
echo ""
echo "To stop services:"
echo "  docker compose down"
echo ""

# Check if frontend is running
if docker compose ps | grep -q "frontend.*Up"; then
    echo "Frontend is running"
else
    echo "Frontend failed to start. Checking logs..."
    echo ""
    docker compose logs frontend
fi

# Check if backend is running
if docker compose ps | grep -q "backend.*Up"; then
    echo "Backend is running"
else
    echo "Backend failed to start. Checking logs..."
    echo ""
    docker compose logs backend
fi
