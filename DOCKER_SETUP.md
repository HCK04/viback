# Docker Development Environment Setup

## Prerequisites

- Docker installed
- Docker Compose installed

Check if you have them:
```bash
docker --version
docker-compose --version
```

If not installed, install Docker Engine and Docker Compose for your distro.

---

## Quick Start

### 1. Build and Start Containers

```bash
# Build and start all services
docker-compose up -d --build

# Check if containers are running
docker-compose ps
```

### 2. Install PHP Dependencies

```bash
# Install Composer dependencies
docker-compose exec app composer install
```

### 3. Generate Application Key

```bash
# Generate Laravel application key
docker-compose exec app php artisan key:generate
```

### 4. Run Database Migrations

```bash
# Run migrations to create database tables
docker-compose exec app php artisan migrate

# If you have seeders, run them
docker-compose exec app php artisan db:seed
```

### 5. Create Storage Link

```bash
# Create symbolic link for storage
docker-compose exec app php artisan storage:link
```

### 6. Set Permissions

```bash
# Fix permissions for storage and cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R visante:www-data storage bootstrap/cache
```

---

## Access Your Application

- **Laravel API:** http://localhost:8000
- **phpMyAdmin:** http://localhost:8080
  - Username: `visante`
  - Password: `secret`

---

## Useful Commands

### Container Management

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# View logs
docker-compose logs -f

# View logs for specific service
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f mysql
```

### Laravel Artisan Commands

```bash
# Run any artisan command
docker-compose exec app php artisan [command]

# Examples:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan migrate:fresh --seed
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:list
docker-compose exec app php artisan tinker
```

### Composer Commands

```bash
# Install packages
docker-compose exec app composer install

# Update packages
docker-compose exec app composer update

# Require new package
docker-compose exec app composer require package/name
```

### Database Commands

```bash
# Access MySQL CLI
docker-compose exec mysql mysql -u visante -psecret visante

# Backup database
docker-compose exec mysql mysqldump -u visante -psecret visante > backup.sql

# Restore database
docker-compose exec -T mysql mysql -u visante -psecret visante < backup.sql
```

### Access Container Shell

```bash
# Access app container
docker-compose exec app bash

# Access MySQL container
docker-compose exec mysql bash

# Access Nginx container
docker-compose exec nginx sh
```

---

## Services Configuration

### PHP-FPM (app)
- **Container:** visante_app
- **PHP Version:** 8.1
- **Extensions:** pdo_mysql, mbstring, exif, pcntl, bcmath, zip, gd
- **Upload limit:** 40MB
- **Memory limit:** 256MB

### Nginx (nginx)
- **Container:** visante_nginx
- **Port:** 8000 → 80
- **Document Root:** /var/www/public

### MySQL (mysql)
- **Container:** visante_mysql
- **Port:** 3306 → 3306
- **Database:** visante
- **Username:** visante
- **Password:** secret
- **Root Password:** root

### phpMyAdmin (phpmyadmin)
- **Container:** visante_phpmyadmin
- **Port:** 8080 → 80
- **Access:** http://localhost:8080

---

## Environment Variables

Edit `.env` file to customize:

```env
DB_HOST=mysql          # Container name
DB_PORT=3306
DB_DATABASE=visante
DB_USERNAME=visante
DB_PASSWORD=secret
```

---

## Troubleshooting

### Port Already in Use

If port 8000 or 3306 is already in use:

```bash
# Check what's using the port
sudo lsof -i :8000
sudo lsof -i :3306

# Kill the process or change port in docker-compose.yml
```

### Permission Issues

```bash
# Fix storage permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R visante:www-data storage bootstrap/cache
```

### Database Connection Failed

```bash
# Wait for MySQL to fully start (takes ~30 seconds)
docker-compose logs mysql

# Check if MySQL is ready
docker-compose exec mysql mysql -u visante -psecret -e "SELECT 1"
```

### Clear All Caches

```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
docker-compose exec app composer dump-autoload
```

### Rebuild Containers

```bash
# Stop and remove containers
docker-compose down

# Remove volumes (WARNING: deletes database data)
docker-compose down -v

# Rebuild from scratch
docker-compose up -d --build --force-recreate
```

---

## Development Workflow

### Making Code Changes

1. Edit files in your local `/viback` directory
2. Changes are automatically reflected (volume mounted)
3. For config changes, clear cache:
   ```bash
   docker-compose exec app php artisan config:clear
   ```

### Adding New Dependencies

```bash
# Add PHP package
docker-compose exec app composer require vendor/package

# Add dev dependency
docker-compose exec app composer require --dev vendor/package
```

### Database Migrations

```bash
# Create new migration
docker-compose exec app php artisan make:migration create_table_name

# Run migrations
docker-compose exec app php artisan migrate

# Rollback last migration
docker-compose exec app php artisan migrate:rollback

# Fresh migration (WARNING: drops all tables)
docker-compose exec app php artisan migrate:fresh
```

---

## Stopping the Environment

```bash
# Stop containers (keeps data)
docker-compose down

# Stop and remove volumes (deletes database)
docker-compose down -v
```

---

## Production Notes

This Docker setup is for **development only**. For production:

1. Use proper secrets management
2. Enable HTTPS
3. Use production-grade MySQL configuration
4. Remove phpMyAdmin
5. Set `APP_ENV=production` and `APP_DEBUG=false`
6. Use proper logging and monitoring

---

## Next Steps

After setup is complete:

1. ✅ Containers running
2. ✅ Dependencies installed
3. ✅ Database migrated
4. ✅ Application key generated
5. 🔧 Test API: http://localhost:8000/api/site-stats
6. 🔧 Connect mobile app to http://localhost:8000/api
7. 🔧 Fix CheckOrigin middleware for mobile (see BACKEND_MOBILE_ANALYSIS.md)

---

**Created:** November 5, 2025  
**For:** Vi-Santé Healthcare Platform
