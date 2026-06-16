#!/bin/bash

# PhysicalMe WordPress - Comprehensive Backup Script
# Creates timestamped backups of database, uploads, and configuration

set -e

BACKUP_DIR="$(dirname "$0")/$(date +%Y-%m-%d_%H-%M-%S)"
mkdir -p "$BACKUP_DIR"

echo "📦 Creating PhysicalMe backup at: $BACKUP_DIR"
echo "=================================================="

# Database credentials
DB_USER="physics_user"
DB_PASS="sAtURN2#6)1"
DB_NAME="physicalme"
DB_CONTAINER="wp-db"

# 1. Create database dump
echo "1️⃣  Creating database dump..."
docker exec "$DB_CONTAINER" mariadb-dump \
  -u "$DB_USER" \
  -p"$DB_PASS" \
  "$DB_NAME" > "$BACKUP_DIR/physicalme-db.sql"

gzip "$BACKUP_DIR/physicalme-db.sql"
DB_SIZE=$(du -h "$BACKUP_DIR/physicalme-db.sql.gz" | cut -f1)
echo "   ✓ Database dumped and compressed: $DB_SIZE"

# 2. Archive uploads directory
echo "2️⃣  Archiving uploads directory..."
cd "$(dirname "$0")/.." && \
tar -czf "$BACKUP_DIR/uploads.tar.gz" wp-content/uploads/ 2>/dev/null
UPLOADS_SIZE=$(du -h "$BACKUP_DIR/uploads.tar.gz" | cut -f1)
echo "   ✓ Uploads archived: $UPLOADS_SIZE"

# 3. Archive entire WordPress directory (excluding cache/uploads already backed up)
echo "3️⃣  Creating full WordPress archive..."
tar -czf "$BACKUP_DIR/wordpress-full.tar.gz" \
  --exclude='wp-content/uploads' \
  --exclude='wp-content/cache' \
  --exclude='backups' \
  --exclude='.git' \
  --exclude='node_modules' \
  . 2>/dev/null
FULL_SIZE=$(du -h "$BACKUP_DIR/wordpress-full.tar.gz" | cut -f1)
echo "   ✓ WordPress archived: $FULL_SIZE"

# 4. Backup wp-config.php
echo "4️⃣  Backing up configuration files..."
cp wp-config.php "$BACKUP_DIR/wp-config.php.bak"
echo "   ✓ wp-config.php backed up"

# 5. Create backup metadata
cat > "$BACKUP_DIR/BACKUP_INFO.txt" << 'EOF'
PhysicalMe WordPress Backup
============================

Backup Contents:
  1. physicalme-db.sql.gz      - Complete database dump (compressed)
  2. uploads.tar.gz            - All uploaded files and media
  3. wordpress-full.tar.gz     - Complete WordPress installation
  4. wp-config.php.bak         - WordPress configuration file
  5. BACKUP_INFO.txt           - This file

Size Summary:
EOF

echo "  - Database: $DB_SIZE" >> "$BACKUP_DIR/BACKUP_INFO.txt"
echo "  - Uploads: $UPLOADS_SIZE" >> "$BACKUP_DIR/BACKUP_INFO.txt"
echo "  - WordPress Full: $FULL_SIZE" >> "$BACKUP_DIR/BACKUP_INFO.txt"
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo "  - Total: $TOTAL_SIZE" >> "$BACKUP_DIR/BACKUP_INFO.txt"

echo "" >> "$BACKUP_DIR/BACKUP_INFO.txt"
echo "Backup Timestamp: $(date)" >> "$BACKUP_DIR/BACKUP_INFO.txt"
echo "Backup Path: $BACKUP_DIR" >> "$BACKUP_DIR/BACKUP_INFO.txt"

echo ""
echo "✅ Backup Complete!"
echo "=================================================="
echo "Location: $BACKUP_DIR"
echo "Total Size: $TOTAL_SIZE"
echo ""
echo "This backup contains everything needed for complete recovery."
