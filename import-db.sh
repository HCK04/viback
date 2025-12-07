#!/bin/bash

# Vi-Santé Database Import Script
# Import existing database dump into Docker MySQL container

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "Vi-Santé Database Import"
echo "=========================================="
echo ""

# Check if SQL file exists
if [ ! -f visante.sql ]; then
    echo -e "${RED}Error: visante.sql not found${NC}"
    echo "Please place your database dump file as 'visante.sql' in the current directory"
    exit 1
fi

echo -e "${GREEN}✓ Found visante.sql${NC}"
echo ""

# Check if MySQL container is running
if ! docker-compose ps mysql | grep -q "Up"; then
    echo -e "${YELLOW}MySQL container is not running. Starting containers...${NC}"
    docker-compose up -d mysql
    echo "Waiting for MySQL to be ready..."
    sleep 15
fi

echo -e "${GREEN}✓ MySQL container is running${NC}"
echo ""

# Wait for MySQL to be fully ready
echo "Checking MySQL connection..."
for i in {1..30}; do
    if docker-compose exec -T mysql mysql -u visante -psecret -e "SELECT 1" &> /dev/null; then
        echo -e "${GREEN}✓ MySQL is ready${NC}"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}Error: MySQL failed to start${NC}"
        exit 1
    fi
    echo "Waiting for MySQL... ($i/30)"
    sleep 2
done
echo ""

# Get file size
FILE_SIZE=$(du -h visante.sql | cut -f1)
echo "Database file size: $FILE_SIZE"
echo ""

# Backup existing database (if any)
echo "Creating backup of existing database (if any)..."
BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
docker-compose exec -T mysql mysqldump -u visante -psecret visante > "$BACKUP_FILE" 2>/dev/null || true
if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
    echo -e "${GREEN}✓ Backup created: $BACKUP_FILE${NC}"
else
    rm -f "$BACKUP_FILE"
    echo "No existing data to backup"
fi
echo ""

# Drop and recreate database to ensure clean import
echo "Preparing database..."
docker-compose exec -T mysql mysql -u root -proot -e "DROP DATABASE IF EXISTS visante; CREATE DATABASE visante CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo -e "${GREEN}✓ Database prepared${NC}"
echo ""

# Import database
echo "Importing database... (this may take a few minutes)"
if docker-compose exec -T mysql mysql -u visante -psecret visante < visante.sql; then
    echo -e "${GREEN}✓ Database imported successfully${NC}"
else
    echo -e "${RED}Error: Database import failed${NC}"
    if [ -f "$BACKUP_FILE" ]; then
        echo "Restoring from backup..."
        docker-compose exec -T mysql mysql -u visante -psecret visante < "$BACKUP_FILE"
        echo "Backup restored"
    fi
    exit 1
fi
echo ""

# Verify import
echo "Verifying import..."
TABLE_COUNT=$(docker-compose exec -T mysql mysql -u visante -psecret visante -e "SHOW TABLES;" | wc -l)
if [ "$TABLE_COUNT" -gt 1 ]; then
    echo -e "${GREEN}✓ Import verified: $((TABLE_COUNT - 1)) tables found${NC}"
else
    echo -e "${RED}Warning: No tables found after import${NC}"
fi
echo ""

# Show database info
echo "Database information:"
docker-compose exec -T mysql mysql -u visante -psecret visante -e "
    SELECT 
        COUNT(*) as table_count,
        SUM(data_length + index_length) / 1024 / 1024 as size_mb
    FROM information_schema.TABLES 
    WHERE table_schema = 'visante';
"
echo ""

echo "=========================================="
echo -e "${GREEN}Import Complete!${NC}"
echo "=========================================="
echo ""
echo "Database 'visante' has been imported successfully."
echo ""
echo "Next steps:"
echo "  1. Test API: curl http://localhost:8000/api/site-stats"
echo "  2. Access phpMyAdmin: http://localhost:8080"
echo "  3. Continue with setup.sh if you haven't run it yet"
echo ""
echo "=========================================="
