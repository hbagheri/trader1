# PhysicalMe WordPress - Complete Recovery Guide

## Overview

This document explains how to completely recover the PhysicalMe WordPress installation from a backup in any disaster scenario.

**Backup Contents:**
- `physicalme-db.sql.gz` - Complete MariaDB/MySQL database
- `uploads.tar.gz` - All uploaded files and media
- `wordpress-full.tar.gz` - Complete WordPress installation (files, plugins, themes, content)
- `wp-config.php.bak` - WordPress configuration backup

---

## Quick Recovery (Less than 5 minutes)

### Scenario 1: Database Corruption
If only the database is corrupted:

```bash
# Extract database dump
gunzip physicalme-db.sql.gz

# Restore to database container
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme < physicalme-db.sql

# Restart WordPress container
docker restart wp-physicalme
```

### Scenario 2: Lost Uploads/Media
If uploaded files are lost but WordPress is working:

```bash
# Stop WordPress to avoid file locks
docker stop wp-physicalme

# Backup current (damaged) uploads
mv wp-content/uploads wp-content/uploads.broken

# Extract uploads backup
tar -xzf uploads.tar.gz

# Restart WordPress
docker start wp-physicalme
```

### Scenario 3: Complete Site Failure
Full recovery from scratch:

```bash
# 1. Restore WordPress files
tar -xzf wordpress-full.tar.gz -C /path/to/physicalme/

# 2. Restore database
gunzip physicalme-db.sql.gz
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme < physicalme-db.sql

# 3. Restore uploads
rm -rf wp-content/uploads
tar -xzf uploads.tar.gz

# 4. Restore configuration
cp wp-config.php.bak wp-config.php

# 5. Update file permissions
sudo chown -R www-data:www-data wp-content/uploads

# 6. Restart services
docker restart wp-physicalme
```

---

## Advanced Recovery Scenarios

### Scenario A: Multi-Stage Recovery (Most Safe)

Use this if you want to verify each step:

**Step 1: Prepare Environment**
```bash
# Create a recovery working directory
mkdir -p recovery
cd recovery
cp /path/to/backup/* .

# Extract and verify file integrity
gunzip physicalme-db.sql.gz
tar -tzf wordpress-full.tar.gz | head -20  # Verify archive
tar -tzf uploads.tar.gz | head -20         # Verify archive
```

**Step 2: Restore Database**
```bash
# Connect to database container
docker exec -it wp-db mariadb -u root -pSaTuRn@3^0!

# Inside MySQL:
# DROP DATABASE physicalme;  # If needed
# CREATE DATABASE physicalme;
# EXIT;

# Restore from dump
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme < physicalme-db.sql

# Verify
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme -e "SELECT COUNT(*) FROM wp_posts;"
```

**Step 3: Restore Files**
```bash
# Backup current installation (safety)
tar -czf physicalme-broken-$(date +%s).tar.gz wp-content/

# Extract WordPress files
tar -xzf wordpress-full.tar.gz

# Extract uploads
tar -xzf uploads.tar.gz

# Fix permissions
sudo chown -R www-data:www-data wp-content/uploads
chmod -R 755 wp-content/uploads
```

**Step 4: Restore Configuration**
```bash
# Check differences
diff wp-config.php.bak wp-config.php

# If needed, restore from backup
cp wp-config.php.bak wp-config.php
```

**Step 5: Verify Services**
```bash
# Restart WordPress container
docker restart wp-physicalme

# Wait for startup
sleep 5

# Check logs
docker logs wp-physicalme | tail -20

# Test in browser
curl http://localhost:50081/
```

### Scenario B: Point-in-Time Recovery

If you have multiple backup timestamps:

```bash
# List available backups
ls -lh backups/*/BACKUP_INFO.txt

# View specific backup info
cat backups/2026-06-16_15-30-45/BACKUP_INFO.txt

# Choose which backup to restore from
BACKUP_DIR="backups/2026-06-16_15-30-45"

# Follow Scenario A using files from $BACKUP_DIR
```

### Scenario C: Parallel Environment Recovery

Restore to a different location for testing:

```bash
# Create recovery location
mkdir -p /tmp/physicalme-recovery
cd /tmp/physicalme-recovery

# Extract all backups
tar -xzf /path/to/wordpress-full.tar.gz
gunzip -c /path/to/physicalme-db.sql.gz > physicalme-db.sql

# Create temporary database for testing
docker exec wp-db mariadb -u root -pSaTuRn@3^0! -e "CREATE DATABASE physicalme_recovery;"
docker exec wp-db mariadb -u root -pSaTuRn@3^0! physicalme_recovery < physicalme-db.sql

# Update wp-config.php temporarily
sed -i "s/define('DB_NAME', 'physicalme');/define('DB_NAME', 'physicalme_recovery');/" wp-config.php

# Test in recovery environment
# Once verified, restore to production
```

---

## Database Credentials Reference

For recovery operations:

```
Root User:      root
Root Password:  SaTuRn@3^0!

PhysicalMe User:     physics_user
PhysicalMe Password: sAtURN2#6)1
PhysicalMe DB:       physicalme

Docker Containers:
  Database:  wp-db
  WordPress: wp-physicalme
```

---

## Recovery Verification Checklist

After recovery, verify everything works:

- [ ] WordPress homepage loads: `http://localhost:50081/`
- [ ] Admin login works: `http://localhost:50081/wp-admin/`
- [ ] Articles accessible: `http://localhost:50081/article/`
- [ ] Database has correct number of posts:
  ```bash
  docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme -e "SELECT COUNT(*) FROM wp_posts;"
  ```
- [ ] Uploads/media files present: `wp-content/uploads/` has subdirectories
- [ ] Elementor Pro active and functional
- [ ] Physics content (440+ articles) accessible

### Database Verification Query

```bash
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme << EOF
-- Verify database integrity
SELECT 'Total Posts' as Check_Name, COUNT(*) as Count FROM wp_posts;
SELECT 'Articles' as Check_Name, COUNT(*) as Count FROM wp_posts WHERE post_type='article';
SELECT 'Published Articles' as Check_Name, COUNT(*) as Count FROM wp_posts WHERE post_type='article' AND post_status='publish';
SELECT 'Uploads' as Check_Name, COUNT(*) as Count FROM wp_posts WHERE post_type='attachment';
EOF
```

---

## Automated Backups (Recommended)

To create regular backups automatically, use cron:

```bash
# Edit crontab
crontab -e

# Add daily backup at 3 AM
0 3 * * * /home/hassan/projects/personal/localWebHosting/wordpress/physicalme/backups/create-backup.sh

# Add weekly backup at 2 AM on Sunday
0 2 * * 0 /home/hassan/projects/personal/localWebHosting/wordpress/physicalme/backups/create-backup.sh

# List backups to verify
0 4 * * * ls -lh /home/hassan/projects/personal/localWebHosting/wordpress/physicalme/backups/ > /tmp/physicalme-backups.log
```

---

## Off-Site Backup (For Real Disaster Recovery)

For ultimate safety, sync backups to external storage:

```bash
# Copy to external drive
rsync -av backups/ /mnt/external-backup/physicalme-backups/

# Or to cloud storage (example: rclone)
rclone sync backups/ gdrive:physicalme-backups/

# Or compress and archive to secure location
tar -czf physicalme-complete-backup-$(date +%Y-%m-%d).tar.gz backups/
scp physicalme-complete-backup-*.tar.gz backup-server:/secure/backups/
```

---

## Recovery Time Estimate

| Scenario | Time | Notes |
|----------|------|-------|
| Database only | 2-3 min | Fastest, database dump + restore |
| Uploads only | 1-2 min | Extract archive |
| Full recovery | 5-10 min | Extract all archives + database restore |
| With verification | 10-15 min | Includes testing and verification |

---

## Emergency Contact

**In case of critical issues:**
- Check Docker logs: `docker logs wp-db` and `docker logs wp-physicalme`
- Verify disk space: `df -h`
- Check MariaDB status: `docker exec wp-db mariadb -u root -p -e "SHOW PROCESSLIST;"`
- Review backup file sizes to ensure they're complete

---

**Last Updated:** 2026-06-16  
**Backup System:** PhysicalMe WordPress DR Plan v1.0
