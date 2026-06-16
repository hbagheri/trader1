# PhysicalMe WordPress Backup & Recovery System

## 📦 Overview

Complete disaster recovery system for PhysicalMe WordPress site. Everything needed to restore the site from complete failure.

## 🗂️ Directory Structure

```
backups/
├── RECOVERY.md                    ← Read this first for recovery instructions
├── README.md                      ← This file
├── create-backup.sh               ← Script to create new backups
├── wp-config.template.php         ← Template for wp-config.php
└── YYYY-MM-DD_HH-MM-SS/          ← Timestamped backup directories
    ├── BACKUP_INFO.txt            ← Backup metadata
    ├── physicalme-db.sql.gz       ← Database dump (compressed)
    ├── uploads.tar.gz             ← All uploaded files
    ├── wordpress-full.tar.gz      ← Complete WordPress installation
    └── wp-config.php.bak          ← Configuration backup
```

## 🚀 Quick Start

### Creating a New Backup

```bash
cd /home/hassan/projects/personal/localWebHosting/wordpress/physicalme
./backups/create-backup.sh
```

This creates a new timestamped directory with:
- Complete database dump (compressed)
- All uploaded files and media
- Full WordPress installation
- Configuration files

### Recovering from Backup

**Read:** `backups/RECOVERY.md` for complete recovery procedures

**Quick recovery (database only):**
```bash
cd backups/2026-06-16_12-17-46
gunzip physicalme-db.sql.gz
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme < physicalme-db.sql
docker restart wp-physicalme
```

## 📊 Backup Contents

Each backup contains:

### 1. **physicalme-db.sql.gz** (1.6M)
- Complete MariaDB database dump
- All WordPress posts, pages, articles
- All user accounts and settings
- All plugin/theme data

**Recovery:**
```bash
gunzip physicalme-db.sql.gz
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme < physicalme-db.sql
```

### 2. **uploads.tar.gz** (28M)
- All uploaded media files
- Elementor generated assets
- Generated thumbnails
- Contact form attachments

**Recovery:**
```bash
tar -xzf uploads.tar.gz
sudo chown -R www-data:www-data wp-content/uploads
```

### 3. **wordpress-full.tar.gz** (154M)
- Complete WordPress installation
- All themes (hello-elementor, etc.)
- All plugins (Elementor Pro, custom plugins)
- Physics content files (440+ articles)
- Custom code and configurations

**Recovery:**
```bash
tar -xzf wordpress-full.tar.gz
```

### 4. **wp-config.php.bak**
- WordPress configuration backup
- Database connection settings
- SSL settings
- Domain routing configuration

## 🔧 Automatic Backups

Set up automatic daily backups using cron:

```bash
# Edit your crontab
crontab -e

# Add this line for daily backups at 3 AM
0 3 * * * /home/hassan/projects/personal/localWebHosting/wordpress/physicalme/backups/create-backup.sh

# Save and exit
```

Verify cron job is active:
```bash
crontab -l
```

## 📋 Backup Verification

Check backup integrity:

```bash
# List all backups with sizes
ls -lh backups/*/

# View backup metadata
cat backups/2026-06-16_12-17-46/BACKUP_INFO.txt

# Verify database dump can be read
gunzip -t backups/2026-06-16_12-17-46/physicalme-db.sql.gz

# Verify archive integrity
tar -tzf backups/2026-06-16_12-17-46/wordpress-full.tar.gz > /dev/null && echo "✓ WordPress archive OK"
tar -tzf backups/2026-06-16_12-17-46/uploads.tar.gz > /dev/null && echo "✓ Uploads archive OK"
```

## 🌍 Off-Site Backups

For ultimate safety, sync to external storage:

### Google Drive (using rclone)
```bash
# First setup: rclone config
rclone config

# Sync backups to cloud
rclone sync backups/ gdrive:physicalme-backups/ -v

# Schedule automatic sync
# Add to crontab:
# 0 4 * * * rclone sync /path/to/backups/ gdrive:physicalme-backups/ -v
```

### External Drive
```bash
# Manual sync to USB/external drive
rsync -av backups/ /mnt/external-backup/physicalme/ --delete

# Or tar and copy
tar -czf physicalme-backup-$(date +%Y-%m-%d).tar.gz backups/
cp physicalme-backup-*.tar.gz /mnt/external-backup/
```

### SSH/SFTP
```bash
# Copy to secure remote server
scp -r backups/ remote-server:/backups/physicalme/

# Or use rsync
rsync -av --delete backups/ user@server:/backups/physicalme/
```

## 🔐 Security Considerations

⚠️ **Important:**
- Database backups contain database credentials in dump file
- Store backups securely with restricted access
- Don't commit to public git repositories
- Encrypt backups for off-site storage
- Verify backup permissions

```bash
# Restrict backup directory access
chmod 700 backups/
chmod 600 backups/*/physicalme-db.sql.gz
```

## 📊 Backup Retention Policy

Recommended:
- **Daily backups**: Keep for 7 days
- **Weekly backups**: Keep for 4 weeks
- **Monthly backups**: Keep for 12 months

To clean old backups:
```bash
# Remove backups older than 30 days
find backups/ -maxdepth 1 -type d -mtime +30 -exec rm -rf {} \;

# Remove backups older than 7 days (keep weekly)
find backups/ -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;
```

## 🆘 Disaster Recovery Timeline

| Scenario | Recovery Time | Notes |
|----------|---------------|-------|
| Lost database | 2-3 min | Restore from SQL dump |
| Lost uploads | 1-2 min | Extract uploads.tar.gz |
| Lost WordPress files | 3-5 min | Extract wordpress-full.tar.gz |
| Complete failure | 10-15 min | Restore all components |

## 📝 Database Credentials (For Recovery)

Used during restoration:

```
MySQL Root:        root
MySQL Root Pass:   SaTuRn@3^0!

PhysicalMe User:   physics_user
PhysicalMe Pass:   sAtURN2#6)1
PhysicalMe DB:     physicalme

Docker Containers:
  Database:        wp-db
  WordPress:       wp-physicalme
```

## ✅ Recovery Checklist

After any recovery, verify:
- [ ] WordPress loads at `http://localhost:50081/`
- [ ] Admin panel accessible at `/wp-admin/`
- [ ] Articles accessible at `/article/`
- [ ] Database integrity verified
- [ ] Uploads/media files present
- [ ] Elementor Pro working
- [ ] Physics content (440+ articles) accessible

```bash
# Quick verification script
docker exec wp-db mariadb -u physics_user -psAtURN2#6)1 physicalme -e \
  "SELECT COUNT(*) as total_posts FROM wp_posts; \
   SELECT COUNT(*) as articles FROM wp_posts WHERE post_type='article';"
```

## 🔗 Related Documentation

- **RECOVERY.md** - Detailed recovery procedures for different scenarios
- **wp-config.template.php** - Template for WordPress configuration
- **create-backup.sh** - Automated backup script

## 📞 Support

For recovery issues:
1. Read **RECOVERY.md** first
2. Check Docker logs: `docker logs wp-db` and `docker logs wp-physicalme`
3. Verify disk space: `df -h`
4. Verify backup file integrity with verification commands above

---

**System Created:** 2026-06-16  
**Version:** 1.0  
**Status:** ✅ Operational
