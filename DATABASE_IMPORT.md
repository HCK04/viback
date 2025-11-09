# Database Import Guide

## 📊 Importing Existing Database

You have an existing database dump (`visante.sql`) that contains your production data. Here's how to import it.

---

## 🚀 Quick Import

### Option 1: Automated Import (Recommended)

The `setup.sh` script will automatically detect and import `visante.sql` if it exists:

```bash
./setup.sh
```

### Option 2: Standalone Import Script

If containers are already running, use the dedicated import script:

```bash
./import-db.sh
```

This script will:
1. ✅ Check if MySQL is running
2. ✅ Create backup of existing data
3. ✅ Drop and recreate database
4. ✅ Import visante.sql
5. ✅ Verify import success
6. ✅ Show database statistics

---

## 📋 Manual Import Steps

If you prefer to import manually:

```bash
# 1. Start MySQL container
docker-compose up -d mysql

# 2. Wait for MySQL to be ready (30 seconds)
sleep 30

# 3. Import database
docker-compose exec -T mysql mysql -u visante -psecret visante < visante.sql

# 4. Verify import
docker-compose exec mysql mysql -u visante -psecret visante -e "SHOW TABLES;"
```

---

## 🔍 Database Information

### Current Database Dump

- **File:** `visante.sql`
- **Size:** ~1782 lines
- **Source:** phpMyAdmin export
- **Server:** MariaDB 10.4.32
- **Export Date:** October 26, 2025

### Tables Included

The database contains tables for:
- `users` - User accounts
- `roles` - User roles
- `annonces` - Announcements
- `appointments` - Appointment bookings
- `medecin_profiles` - Doctor profiles
- `patient_profiles` - Patient profiles
- `clinique_profiles` - Clinic profiles
- `pharmacie_profiles` - Pharmacy profiles
- `parapharmacie_profiles` - Parapharmacy profiles
- `centre_radiologie_profiles` - Radiology center profiles
- `labo_analyse_profiles` - Lab profiles
- `notifications` - User notifications
- And more...

---

## ⚠️ Important Notes

### Character Set

The database uses `utf8mb4_unicode_ci` collation, which supports:
- ✅ Full Unicode (including emojis)
- ✅ French characters (é, è, à, etc.)
- ✅ Arabic characters
- ✅ Special symbols

### JSON Fields

Many tables use JSON fields for flexible data storage:
- `availability` - Doctor availability schedules
- `images` - Image galleries
- `services` - Service lists
- `moyens_paiement` - Payment methods
- `moyens_transport` - Transportation options
- `jours_disponibles` - Available days

Laravel handles these automatically with JSON casting.

---

## 🔄 Re-importing Database

If you need to re-import the database:

```bash
# Using import script (creates backup)
./import-db.sh

# Or manually
docker-compose exec -T mysql mysql -u root -proot -e "DROP DATABASE IF EXISTS visante; CREATE DATABASE visante;"
docker-compose exec -T mysql mysql -u visante -psecret visante < visante.sql
```

---

## 💾 Backup Current Database

Before making changes, create a backup:

```bash
# Create timestamped backup
docker-compose exec mysql mysqldump -u visante -psecret visante > backup_$(date +%Y%m%d_%H%M%S).sql

# Or with compression
docker-compose exec mysql mysqldump -u visante -psecret visante | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

---

## 🧪 Verify Import

After importing, verify the database:

```bash
# Check table count
docker-compose exec mysql mysql -u visante -psecret visante -e "SHOW TABLES;"

# Check user count
docker-compose exec mysql mysql -u visante -psecret visante -e "SELECT COUNT(*) as user_count FROM users;"

# Check database size
docker-compose exec mysql mysql -u visante -psecret visante -e "
    SELECT 
        table_schema as 'Database',
        COUNT(*) as 'Tables',
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as 'Size (MB)'
    FROM information_schema.TABLES 
    WHERE table_schema = 'visante'
    GROUP BY table_schema;
"
```

---

## 🔧 Troubleshooting

### Import Fails with "Access Denied"

```bash
# Check MySQL is ready
docker-compose exec mysql mysql -u visante -psecret -e "SELECT 1"

# If fails, wait longer for MySQL to start
sleep 30
```

### Import Hangs or Times Out

```bash
# Check MySQL logs
docker-compose logs mysql

# Increase max_allowed_packet if needed
docker-compose exec mysql mysql -u root -proot -e "SET GLOBAL max_allowed_packet=67108864;"
```

### Character Encoding Issues

```bash
# Ensure database uses utf8mb4
docker-compose exec mysql mysql -u root -proot -e "
    ALTER DATABASE visante CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
```

### Import Creates Wrong Database Name

The SQL dump should contain:
```sql
-- Database: `visante`
```

If it creates a different database, edit `visante.sql` and change the database name.

---

## 📊 Database Statistics

After import, check statistics:

```bash
# Table sizes
docker-compose exec mysql mysql -u visante -psecret visante -e "
    SELECT 
        table_name,
        table_rows,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'Size (MB)'
    FROM information_schema.TABLES 
    WHERE table_schema = 'visante'
    ORDER BY (data_length + index_length) DESC
    LIMIT 10;
"

# Record counts
docker-compose exec mysql mysql -u visante -psecret visante -e "
    SELECT 
        (SELECT COUNT(*) FROM users) as users,
        (SELECT COUNT(*) FROM appointments) as appointments,
        (SELECT COUNT(*) FROM annonces) as annonces,
        (SELECT COUNT(*) FROM notifications) as notifications;
"
```

---

## 🔐 Security Notes

### Production Data

If `visante.sql` contains production data:
- ⚠️ **Do not commit to Git** (already in .gitignore)
- ⚠️ **Sanitize sensitive data** for development
- ⚠️ **Use separate credentials** for production

### Sanitizing Production Data

To create a sanitized dump for development:

```bash
# Export structure only (no data)
docker-compose exec mysql mysqldump -u visante -psecret --no-data visante > schema_only.sql

# Or anonymize sensitive data
docker-compose exec mysql mysql -u visante -psecret visante -e "
    UPDATE users SET 
        email = CONCAT('user', id, '@example.com'),
        password = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        phone = CONCAT('06', LPAD(id, 8, '0'))
    WHERE role_id = 1;
"
```

---

## 🔄 Migrations vs Import

### When to Use Import

✅ Use `visante.sql` import when:
- You have existing production data
- You want to preserve all records
- Database structure is already correct

### When to Use Migrations

✅ Use Laravel migrations when:
- Starting fresh
- No existing data
- Want version-controlled schema changes

### Hybrid Approach

You can use both:

```bash
# 1. Import existing database
./import-db.sh

# 2. Run new migrations for schema updates
docker-compose exec app php artisan migrate
```

---

## 📝 Next Steps

After importing the database:

1. ✅ Database imported
2. 🔧 Test API endpoints
3. 🔧 Verify data in phpMyAdmin (http://localhost:8080)
4. 🔧 Test authentication with existing users
5. 🔧 Connect mobile app

---

## 🆘 Need Help?

If import fails:

1. Check MySQL logs: `docker-compose logs mysql`
2. Verify file exists: `ls -lh visante.sql`
3. Check file encoding: `file visante.sql`
4. Try manual import with verbose output:
   ```bash
   docker-compose exec -T mysql mysql -u visante -psecret visante -v < visante.sql
   ```

---

**Database Ready!** 🎉

Your existing database has been imported and is ready to use.

---

*Updated: November 5, 2025*
