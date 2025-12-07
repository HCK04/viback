#!/bin/bash

# Vi-Santé Docker Development Environment Setup Script
# This script automates the initial setup of the Laravel backend

set -e

echo "=========================================="
echo "Vi-Santé Docker Setup"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed${NC}"
    echo "Please install Docker first: https://docs.docker.com/engine/install/"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Error: Docker Compose is not installed${NC}"
    echo "Please install Docker Compose first: https://docs.docker.com/compose/install/"
    exit 1
fi

echo -e "${GREEN}✓ Docker and Docker Compose are installed${NC}"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found${NC}"
    echo "The .env file should have been created. Please check."
    exit 1
fi

echo -e "${GREEN}✓ .env file exists${NC}"
echo ""

# Build and start containers
echo "Building and starting Docker containers..."
docker-compose up -d --build

echo ""
echo "Waiting for MySQL to be ready..."
sleep 10

# Check if containers are running
if [ "$(docker-compose ps -q | wc -l)" -eq 0 ]; then
    echo -e "${RED}Error: Containers failed to start${NC}"
    echo "Check logs with: docker-compose logs"
    exit 1
fi

echo -e "${GREEN}✓ Containers are running${NC}"
echo ""

# Install Composer dependencies
echo "Installing Composer dependencies..."
docker-compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

echo -e "${GREEN}✓ Composer dependencies installed${NC}"
echo ""

# Generate application key
echo "Generating application key..."
docker-compose exec -T app php artisan key:generate --ansi

echo -e "${GREEN}✓ Application key generated${NC}"
echo ""

# Import existing database
if [ -f visante.sql ]; then
    echo "Importing existing database from visante.sql..."
    docker-compose exec -T mysql mysql -u visante -psecret visante < visante.sql
    echo -e "${GREEN}✓ Database imported successfully${NC}"
else
    echo "Running database migrations..."
    docker-compose exec -T app php artisan migrate --force
    echo -e "${GREEN}✓ Database migrations completed${NC}"
fi
echo ""

# Create storage link
echo "Creating storage symlink..."
docker-compose exec -T app php artisan storage:link

echo -e "${GREEN}✓ Storage link created${NC}"
echo ""

# Fix permissions
echo "Setting correct permissions..."
docker-compose exec -T app chmod -R 775 storage bootstrap/cache
docker-compose exec -T app chown -R visante:www-data storage bootstrap/cache

echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Clear caches
echo "Clearing application caches..."
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan route:clear
docker-compose exec -T app php artisan view:clear

echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

echo "=========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "Your application is now running:"
echo ""
echo "  📱 Laravel API:    http://localhost:8000"
echo "  🗄️  phpMyAdmin:    http://localhost:8080"
echo "     Username: visante"
echo "     Password: secret"
echo ""
echo "Useful commands:"
echo "  • View logs:       docker-compose logs -f"
echo "  • Stop:            docker-compose down"
echo "  • Restart:         docker-compose restart"
echo "  • Run artisan:     docker-compose exec app php artisan [command]"
echo ""
echo "Next steps:"
echo "  1. Test API: curl http://localhost:8000/api/site-stats"
echo "  2. Check DOCKER_SETUP.md for more commands"
echo "  3. Fix CheckOrigin middleware for mobile app"
echo ""
echo "=========================================="
