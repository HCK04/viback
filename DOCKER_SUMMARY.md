# Docker Environment Summary

## 📦 What Was Created

### Docker Configuration Files
- ✅ `docker-compose.yml` - Main orchestration file
- ✅ `docker/php/Dockerfile` - PHP 8.1-FPM container
- ✅ `docker/php/local.ini` - PHP configuration
- ✅ `docker/nginx/nginx.conf` - Nginx web server config
- ✅ `docker/mysql/my.cnf` - MySQL configuration
- ✅ `.dockerignore` - Files to exclude from Docker build

### Laravel Configuration
- ✅ `.env` - Environment variables (created from scratch)
- ✅ `setup.sh` - Automated setup script
- ✅ `DOCKER_SETUP.md` - Complete documentation
- ✅ `QUICK_START.md` - Quick reference guide

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────┐
│           Docker Network (visante)          │
├─────────────────────────────────────────────┤
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │  Nginx   │  │ PHP-FPM  │  │  MySQL   │ │
│  │  :8000   │→ │  :9000   │→ │  :3306   │ │
│  └──────────┘  └──────────┘  └──────────┘ │
│                                             │
│  ┌──────────────────────────────────────┐  │
│  │         phpMyAdmin :8080             │  │
│  └──────────────────────────────────────┘  │
│                                             │
└─────────────────────────────────────────────┘
```

---

## 🔧 Services

### 1. PHP-FPM (app)
- **Image:** Custom (PHP 8.1-FPM)
- **Container:** visante_app
- **Purpose:** Run Laravel application
- **Extensions:** pdo_mysql, mbstring, exif, pcntl, bcmath, zip, gd
- **Config:** 40MB upload limit, 256MB memory

### 2. Nginx (nginx)
- **Image:** nginx:alpine
- **Container:** visante_nginx
- **Port:** 8000 → 80
- **Purpose:** Web server and reverse proxy
- **Document Root:** /var/www/public

### 3. MySQL (mysql)
- **Image:** mysql:8.0
- **Container:** visante_mysql
- **Port:** 3306 → 3306
- **Database:** visante
- **User:** visante
- **Password:** secret
- **Root Password:** root

### 4. phpMyAdmin (phpmyadmin)
- **Image:** phpmyadmin:latest
- **Container:** visante_phpmyadmin
- **Port:** 8080 → 80
- **Purpose:** Database management GUI

---

## 📝 Environment Variables (.env)

```env
APP_NAME="Vi-Santé"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql              # ← Container name
DB_PORT=3306
DB_DATABASE=visante
DB_USERNAME=visante
DB_PASSWORD=secret
```

**Important:** `DB_HOST=mysql` uses Docker's internal network. The container name resolves to the MySQL container's IP.

---

## 🚀 Getting Started

### First Time Setup

```bash
# Run the automated setup script
./setup.sh
```

Or manually:

```bash
# 1. Build and start
docker-compose up -d --build

# 2. Install dependencies
docker-compose exec app composer install

# 3. Generate app key
docker-compose exec app php artisan key:generate

# 4. Run migrations
docker-compose exec app php artisan migrate

# 5. Create storage link
docker-compose exec app php artisan storage:link
```

### Daily Usage

```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# View logs
docker-compose logs -f

# Run artisan commands
docker-compose exec app php artisan [command]
```

---

## 🔍 Verification Steps

After setup, verify everything works:

### 1. Check Containers
```bash
docker-compose ps
```
Should show 4 running containers.

### 2. Test API
```bash
curl http://localhost:8000/api/site-stats
```
Should return JSON response.

### 3. Check Database
```bash
docker-compose exec mysql mysql -u visante -psecret -e "SHOW DATABASES;"
```
Should list `visante` database.

### 4. Access phpMyAdmin
Open http://localhost:8080 in browser.

### 5. Check Laravel
```bash
docker-compose exec app php artisan --version
```
Should show Laravel version.

---

## 📊 Database Schema

The migrations will create these tables:
- `users` - All user accounts
- `roles` - User roles (patient, medecin, etc.)
- `role_categories` - Role categories
- `patient_profiles` - Patient-specific data
- `medecin_profiles` - Doctor-specific data
- `appointments` - Appointment bookings
- `annonces` - Professional announcements
- `notifications` - User notifications
- `personal_access_tokens` - Sanctum auth tokens
- And more...

---

## 🔐 Security Notes

### Development Environment
- ✅ Debug mode enabled
- ✅ Detailed error messages
- ✅ Simple passwords (visante/secret)
- ✅ phpMyAdmin exposed

### Production Environment
**DO NOT use this setup in production!**

For production:
- ❌ Disable debug mode
- ❌ Use strong passwords
- ❌ Remove phpMyAdmin
- ❌ Enable HTTPS
- ❌ Use environment secrets
- ❌ Implement proper logging

---

## 🐛 Troubleshooting

### Containers won't start
```bash
# Check logs
docker-compose logs

# Check specific service
docker-compose logs mysql
docker-compose logs app
```

### Port conflicts
```bash
# Find what's using port 8000
sudo lsof -i :8000

# Change port in docker-compose.yml
nginx:
  ports:
    - "8001:80"  # Use different port
```

### Database connection errors
```bash
# MySQL takes ~30 seconds to initialize
# Wait and check logs
docker-compose logs mysql

# Verify MySQL is ready
docker-compose exec mysql mysql -u visante -psecret -e "SELECT 1"
```

### Permission errors
```bash
# Fix Laravel storage permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R visante:www-data storage bootstrap/cache
```

### Composer errors
```bash
# Clear composer cache
docker-compose exec app composer clear-cache

# Reinstall dependencies
docker-compose exec app rm -rf vendor
docker-compose exec app composer install
```

### Migration errors
```bash
# Check database connection
docker-compose exec app php artisan migrate:status

# Fresh migration (WARNING: drops all data)
docker-compose exec app php artisan migrate:fresh
```

---

## 📱 Connecting Mobile App

### For iOS Simulator / Android Emulator on same machine
```typescript
// vimobile/lib/apiClient.ts
const DEFAULT_BASE_URL = 'http://localhost:8000/api';
```

### For Android Emulator (special IP)
```typescript
const DEFAULT_BASE_URL = 'http://10.0.2.2:8000/api';
```

### For Physical Device
```bash
# Find your computer's IP
ip addr show | grep "inet " | grep -v "127.0.0.1"

# Use in mobile app
const DEFAULT_BASE_URL = 'http://192.168.1.x:8000/api';
```

**Note:** Make sure your firewall allows connections on port 8000.

---

## 🔄 Updating Dependencies

### PHP Dependencies
```bash
# Update all
docker-compose exec app composer update

# Update specific package
docker-compose exec app composer update vendor/package

# Add new package
docker-compose exec app composer require vendor/package
```

### Rebuild Containers
```bash
# After changing Dockerfile
docker-compose up -d --build

# Force rebuild
docker-compose build --no-cache
docker-compose up -d
```

---

## 💾 Backup & Restore

### Backup Database
```bash
# Export database
docker-compose exec mysql mysqldump -u visante -psecret visante > backup_$(date +%Y%m%d).sql

# Or with gzip
docker-compose exec mysql mysqldump -u visante -psecret visante | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Restore Database
```bash
# Import database
docker-compose exec -T mysql mysql -u visante -psecret visante < backup.sql

# Or from gzip
gunzip < backup.sql.gz | docker-compose exec -T mysql mysql -u visante -psecret visante
```

---

## 🧹 Cleanup

### Remove Containers (keep data)
```bash
docker-compose down
```

### Remove Everything (including data)
```bash
docker-compose down -v
```

### Clean Docker System
```bash
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Full cleanup
docker system prune -a --volumes
```

---

## 📚 Additional Resources

- **Full Documentation:** DOCKER_SETUP.md
- **Quick Reference:** QUICK_START.md
- **Backend Analysis:** ../BACKEND_MOBILE_ANALYSIS.md
- **Laravel Docs:** https://laravel.com/docs/9.x
- **Docker Docs:** https://docs.docker.com/

---

## ✅ Next Steps

1. ✅ Docker environment set up
2. ✅ Dependencies installed
3. ✅ Database migrated
4. 🔧 Test API endpoints
5. 🔧 Connect mobile app
6. 🔧 Fix CheckOrigin middleware (see BACKEND_MOBILE_ANALYSIS.md)
7. 🔧 Start development!

---

**Environment Ready!** 🎉

Your Vi-Santé backend is now running in Docker with:
- PHP 8.1-FPM
- Nginx
- MySQL 8.0
- phpMyAdmin

Access your API at: **http://localhost:8000**

---

*Created: November 5, 2025*  
*For: Vi-Santé Healthcare Platform*
