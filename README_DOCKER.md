# 🐳 Docker Development Environment - Vi-Santé Backend

## ✅ Setup Complete!

Your Docker development environment has been configured with:

- **PHP 8.1-FPM** with all required extensions
- **Nginx** web server
- **MySQL 8.0** database
- **phpMyAdmin** for database management
- **Laravel 9** with Sanctum authentication

---

## 🚀 Quick Start

### Option 1: Automated Setup (Recommended)

```bash
./setup.sh
```

This script will:
1. Build Docker containers
2. Install Composer dependencies
3. Generate application key
4. **Import existing database** (visante.sql) or run migrations
5. Set up storage links
6. Fix permissions

**Note:** The script automatically detects `visante.sql` and imports it instead of running migrations.

### Option 2: Manual Setup

```bash
# Start containers
docker-compose up -d --build

# Install dependencies
docker-compose exec app composer install

# Generate app key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# Create storage link
docker-compose exec app php artisan storage:link
```

---

## 🌐 Access Your Application

| Service | URL | Credentials |
|---------|-----|-------------|
| **Laravel API** | http://localhost:8000 | - |
| **phpMyAdmin** | http://localhost:8080 | visante / secret |

---

## 🧪 Test Your Setup

```bash
# Test a public API endpoint
curl http://localhost:8000/api/site-stats

# Should return JSON with site statistics
```

---

## 📖 Documentation

- **QUICK_START.md** - Quick reference for daily commands
- **DATABASE_IMPORT.md** - Database import guide (for visante.sql)
- **DOCKER_SETUP.md** - Complete documentation with troubleshooting
- **DOCKER_SUMMARY.md** - Architecture and technical details

---

## 🔧 Common Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# Run artisan commands
docker-compose exec app php artisan [command]

# Access MySQL
docker-compose exec mysql mysql -u visante -psecret visante

# Access container shell
docker-compose exec app bash
```

---

## 📱 Connect Mobile App

Update the mobile app's API URL:

```typescript
// For localhost (iOS simulator / Android emulator on same machine)
const DEFAULT_BASE_URL = 'http://localhost:8000/api';

// For Android emulator (special IP)
const DEFAULT_BASE_URL = 'http://10.0.2.2:8000/api';

// For physical device (use your computer's IP)
const DEFAULT_BASE_URL = 'http://192.168.x.x:8000/api';
```

---

## 🐛 Troubleshooting

### Port Already in Use
```bash
# Check what's using port 8000
sudo lsof -i :8000

# Change port in docker-compose.yml if needed
```

### Database Connection Failed
```bash
# Wait for MySQL to start (takes ~30 seconds)
docker-compose logs mysql

# Verify MySQL is ready
docker-compose exec mysql mysql -u visante -psecret -e "SELECT 1"
```

### Permission Issues
```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R visante:www-data storage bootstrap/cache
```

---

## 📋 Files Created

```
viback/
├── docker-compose.yml          # Main orchestration file
├── .env                        # Environment variables
├── .dockerignore              # Docker build exclusions
├── setup.sh                   # Automated setup script
├── docker/
│   ├── php/
│   │   ├── Dockerfile         # PHP 8.1-FPM image
│   │   └── local.ini          # PHP configuration
│   ├── nginx/
│   │   └── nginx.conf         # Nginx configuration
│   └── mysql/
│       └── my.cnf             # MySQL configuration
└── Documentation:
    ├── README_DOCKER.md       # This file
    ├── QUICK_START.md         # Quick reference
    ├── DOCKER_SETUP.md        # Complete guide
    └── DOCKER_SUMMARY.md      # Technical details
```

---

## ⚠️ Important Notes

### Development Only
This Docker setup is for **development only**. Do not use in production without:
- Proper security hardening
- Strong passwords
- HTTPS configuration
- Production-grade database settings
- Removing phpMyAdmin

### Database Credentials
- **Database:** visante
- **Username:** visante
- **Password:** secret
- **Root Password:** root

Change these in `.env` if needed.

---

## 🔗 Next Steps

1. ✅ Docker environment running
2. 🔧 Test API endpoints
3. 🔧 Connect mobile app to http://localhost:8000/api
4. 🔧 Fix CheckOrigin middleware for mobile (see ../BACKEND_MOBILE_ANALYSIS.md)
5. 🔧 Start developing!

---

## 💡 Tips

- **View all logs:** `docker-compose logs -f`
- **Restart a service:** `docker-compose restart app`
- **Clear Laravel cache:** `docker-compose exec app php artisan cache:clear`
- **Run migrations:** `docker-compose exec app php artisan migrate`
- **Access Tinker:** `docker-compose exec app php artisan tinker`

---

## 🆘 Need Help?

1. Check **DOCKER_SETUP.md** for detailed troubleshooting
2. View container logs: `docker-compose logs [service]`
3. Check container status: `docker-compose ps`
4. Verify network: `docker network inspect viback_visante`

---

**Happy Coding!** 🎉

Your Vi-Santé backend is ready for development.

---

*Environment: Docker + PHP 8.1 + MySQL 8.0 + Nginx*  
*Created: November 5, 2025*
