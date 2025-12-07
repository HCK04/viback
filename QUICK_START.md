# Quick Start Guide - Vi-Santé Docker Environment

## 🚀 One-Command Setup

```bash
./setup.sh
```

That's it! The script will:
- ✅ Build Docker containers
- ✅ Install dependencies
- ✅ Generate app key
- ✅ Run migrations
- ✅ Set permissions

---

## 📋 Manual Setup (if script fails)

```bash
# 1. Start containers
docker-compose up -d --build

# 2. Install dependencies
docker-compose exec app composer install

# 3. Generate key
docker-compose exec app php artisan key:generate

# 4. Run migrations
docker-compose exec app php artisan migrate

# 5. Create storage link
docker-compose exec app php artisan storage:link

# 6. Fix permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

---

## 🌐 Access Points

| Service | URL | Credentials |
|---------|-----|-------------|
| **API** | http://localhost:8000 | - |
| **phpMyAdmin** | http://localhost:8080 | visante / secret |

---

## 🔧 Daily Commands

```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# Logs
docker-compose logs -f app

# Artisan
docker-compose exec app php artisan [command]

# Composer
docker-compose exec app composer [command]

# MySQL CLI
docker-compose exec mysql mysql -u visante -psecret visante
```

---

## 🧪 Test Your Setup

```bash
# Test API endpoint
curl http://localhost:8000/api/site-stats

# Should return JSON with site statistics
```

---

## 🐛 Common Issues

### Port 8000 already in use
```bash
# Change port in docker-compose.yml
ports:
  - "8001:80"  # Use 8001 instead
```

### Database connection failed
```bash
# Wait 30 seconds for MySQL to start
docker-compose logs mysql

# Check MySQL is ready
docker-compose exec mysql mysql -u visante -psecret -e "SELECT 1"
```

### Permission denied
```bash
# Fix permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R visante:www-data storage bootstrap/cache
```

---

## 📖 Full Documentation

See **DOCKER_SETUP.md** for complete documentation.

---

## 🔗 Connect Mobile App

Update mobile app API URL to:
```typescript
// vimobile/lib/apiClient.ts
const DEFAULT_BASE_URL = 'http://localhost:8000/api';
```

For Android emulator use:
```typescript
const DEFAULT_BASE_URL = 'http://10.0.2.2:8000/api';
```

For physical device, use your computer's IP:
```bash
# Find your IP
ip addr show | grep "inet "

# Use in mobile app
const DEFAULT_BASE_URL = 'http://192.168.x.x:8000/api';
```

---

**Ready to code!** 🎉
