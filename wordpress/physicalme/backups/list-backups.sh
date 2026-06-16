#!/bin/bash

# PhysicalMe - List and verify all backups

echo "📦 PhysicalMe WordPress Backups"
echo "========================================"
echo ""

if [ ! -d "backups" ] && [ ! -f "RECOVERY.md" ]; then
    echo "❌ Error: Run this script from the PhysicalMe WordPress root directory"
    exit 1
fi

cd backups 2>/dev/null || { cd ../backups 2>/dev/null || { echo "❌ Cannot find backups directory"; exit 1; }; }

# Count backups
BACKUP_COUNT=$(find . -maxdepth 1 -type d -name "*_*" | wc -l)
echo "📊 Total backups: $BACKUP_COUNT"
echo ""

# List all backups
for backup_dir in $(ls -dt */ 2>/dev/null | head -20); do
    backup_name="${backup_dir%/}"
    backup_date=$(echo "$backup_name" | sed 's/_/ at /; s/_/:/g')
    backup_size=$(du -sh "$backup_name" 2>/dev/null | cut -f1)

    # Count files in backup
    file_count=$(ls -1 "$backup_name" 2>/dev/null | wc -l)

    # Get timestamp of backup
    timestamp=$(stat -c %y "$backup_name" 2>/dev/null | cut -d' ' -f1,2 || stat -f '%Sm' "$backup_name" 2>/dev/null)

    echo "🗂️  $backup_name"
    echo "   Size: $backup_size | Files: $file_count"

    # Show backup file sizes
    if [ -f "$backup_name/BACKUP_INFO.txt" ]; then
        echo "   Files:"
        grep -E "^\s+-" "$backup_name/BACKUP_INFO.txt" | while read line; do
            echo "     $line"
        done
    fi
    echo ""
done

echo "========================================"
echo ""
echo "💡 To restore from a backup:"
echo "   1. Read: backups/RECOVERY.md"
echo "   2. Choose backup: backups/YYYY-MM-DD_HH-MM-SS/"
echo "   3. Follow recovery procedure for your scenario"
echo ""
echo "🔄 To create a new backup:"
echo "   Run: ./backups/create-backup.sh"
echo ""
