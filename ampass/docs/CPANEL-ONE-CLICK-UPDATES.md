# AMPass — cPanel One-Click Updates

## Security Warning

AMPass one-click updater requires professional security audit before real credential storage.

## Overview

AMPass can update itself from GitHub with one click. No SSH, Git, or Composer required. Works on cPanel shared hosting.

## How It Works

1. Admin clicks "One-Click Update AMPass" on the Updates page
2. AMPass creates an encrypted pre-update backup
3. Downloads the latest code as a ZIP from GitHub
4. Validates ZIP safety (no path traversal, no symlinks)
5. Copies updated files with rollback tracking
6. Runs pending database migrations
7. Syncs version info from GitHub API
8. Disables maintenance mode
9. Shows success with new version number

## Setup

### 1. Set Update Source

Go to **Admin → Updates → Update Settings**

For development/latest commits:
- Source: **Latest Branch ZIP**
- Branch: `main`

For production (when releases exist):
- Source: **Stable Releases**

### 2. Required PHP Extensions

- PDO MySQL
- cURL
- ZipArchive
- OpenSSL
- Sodium (recommended, not required)

### 3. Required Folder Permissions

These directories must be writable by the web server:
- `app/`
- `public/`
- `database/migrations/`
- `app_storage/backups/`
- `app_storage/temp/`

On cPanel, these should be 755 by default. If not:
```
chmod -R 755 app/ public/ database/ app_storage/
```

### 4. Backup Password

Options:
- **Ask every time** (default, safest): Enter backup password when clicking update
- **Store encrypted**: Save a backup password encrypted with APP_SECRET for truly one-click updates

## Preflight Checks

Before updating, AMPass runs automatic checks:
- PHP version ≥ 8.1
- Required extensions available
- Directories writable
- APP_SECRET defined
- Sufficient disk space
- HTTPS active (warning if not)

If any check is a "blocker", the update button is disabled.

## What Gets Updated

Updated:
- `app/` (controllers, models, views, services, core)
- `public/` (CSS, JS, images)
- `database/migrations/` (new migrations)
- `docs/`
- `index.php`, `sw.js`, `manifest.webmanifest`

Never overwritten:
- `config/config.php`
- `config/.install_lock`
- `app_storage/` (backups, releases, uploads)
- `.htaccess`
- `install/`

## Rollback

If the update fails (file copy error, migration failure):
- Overwritten files are restored from rollback snapshot
- Newly created files are deleted
- Empty directories are removed
- Maintenance mode is disabled
- Update is marked as "failed" in history

## Troubleshooting

### "Preflight checks failed"
- Check which items show ✗ (blocker)
- Fix permissions: `chmod 755 app/ public/`
- Ensure APP_SECRET is defined in config.php

### "Backup failed"
- Check if sodium extension is available: `php -m | grep sodium`
- If not, AMPass uses AES-256-GCM fallback (requires OpenSSL)
- Ensure `app_storage/backups/` is writable

### "GitHub unreachable"
- Check if cURL can reach github.com
- Add GitHub token in settings for higher rate limits
- Check if hosting firewall blocks outbound HTTPS

### "ZIP validation failed"
- The downloaded ZIP contained unsafe paths
- This should not happen with official GitHub archives
- Report as a security issue if it occurs

### "Migration failed"
- Check error details in update history
- Failed migrations are NOT marked as applied (safe to retry)
- Fix the issue and run "Run Pending Migrations"

### "File permission denied"
- Web server cannot write to app directories
- Fix: `chmod -R 755 app/ public/ database/`
- On cPanel: use File Manager to set permissions

## Why Branch ZIP vs Releases

**Branch ZIP** (recommended for development):
- Detects any new commit on main branch
- Always gets latest code
- No need to create GitHub releases
- Shows "V1.63" based on commit count

**Stable Releases** (recommended for production):
- Only updates when a new tagged release exists
- More controlled update cycle
- Requires creating GitHub releases with version tags

## Security Notes

- Pre-update backup is always created (encrypted)
- ZIP entries validated before extraction
- No shell commands used (no shell_exec, no exec)
- No Git binary required
- No Composer required
- Maintenance mode active during update
- Rollback on any failure
- Download SHA-256 logged for audit
- APP_SECRET required for backup encryption
